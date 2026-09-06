# DM System — Large-Scale Sales / Activation Data Management

Laravel 13 · MySQL 8/9 · Redis (optional, DB queue fallback) · Livewire 4 · OpenSpout

Design target: **millions of rows from day one**. 10L fully supported, 20L supported,
50L practical with proper server resources, 1cr with a clear scaling path (partitioning
+ summary tables) that does **not** require an application rewrite.

---

## 1. Domain model

Three concerns are kept strictly separate:

| Layer | Tables | Purpose |
|---|---|---|
| **Raw data** | `sales_activation_records` | One canonical row per IMEI. The source of truth. |
| **Import pipeline** | `import_batches`, `import_batch_chunks`, `import_staging_rows`, `import_row_errors` | File ingestion, progress, fail-safe retry, error capture. |
| **Reporting** | `daily_activation_summary`, `rd_summary`, `rt_summary`, `model_summary`, `tso_summary` | Pre-aggregated numbers for the dashboard and standard reports. |

Future sales stages (ND→RD sell-in, RD→RT sell-thru, RT→customer sell-out) get their
**own** raw tables following the same pattern. They are never mixed into
`sales_activation_records`. Shared entities (RD, RT, TSO, Model) normalize into master
tables (`retail_distributors`, `retailers`, `territory_officers`, `device_models`) —
scaffolded in Phase 1 as lookup tables, wired in as FKs in a later phase once the raw
import is proven. The raw table keeps the denormalized text columns regardless, so a
report never needs a join to render RD/RT/TSO/Model names.

---

## 2. `sales_activation_records`

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `imei` | VARCHAR(20) | **UNIQUE**. Stored as string: preserves leading zeros, tolerates IMEISV/16-digit and non-numeric junk without silent truncation. 15–16 real digits. |
| `model` | VARCHAR(100) | |
| `tso` | VARCHAR(120) | |
| `rd_code` | VARCHAR(40) | |
| `rd_name` | VARCHAR(191) | |
| `rt_code` | VARCHAR(40) | |
| `rt_name` | VARCHAR(191) | |
| `st_date` | DATE NULL | Sell-through transaction date. |
| `activation_date` | DATE NULL | Device activation date. |
| `source` | VARCHAR(40) | Manual / Excel / API / System … never overwritten with a blank. |
| `activation_days` | SMALLINT NULL | **MySQL STORED generated column** = `DATEDIFF(activation_date, st_date)`. Bucket reports (0 / 1–7 / 8–15 / 16–30 / 31+) run straight off an index, zero PHP. |
| `is_activated` | TINYINT(1) | **STORED generated column** = `activation_date IS NOT NULL`. Lets "activated vs not" aggregate off an index. |
| `first_import_batch_id` | BIGINT UNSIGNED | Audit: which batch first created this IMEI. |
| `last_import_batch_id` | BIGINT UNSIGNED | Audit: which batch last touched it. |
| `created_at` / `updated_at` | TIMESTAMP | |

### Indexes — every one justified

| Index | Serves |
|---|---|
| `UNIQUE (imei)` | Instant IMEI search **and** the upsert dedupe key. Non-negotiable. |
| `(st_date)` | Date-wise report, dashboard daily sell-in, date-range filters. |
| `(activation_date)` | Activation report, daily activation. |
| `(rd_code, st_date)` | RD-wise report, optionally date-bounded. Leftmost prefix also covers RD-only filters. |
| `(rt_code, st_date)` | RT-wise report. |
| `(model, st_date)` | Model-wise report. |
| `(tso, st_date)` | TSO-wise report. |
| `(is_activated, st_date)` | "Sold but not activated" trended over time — the core business KPI. |
| `(activation_days)` | ST→activation lag bucket report. |
| `(last_import_batch_id)` | Batch audit and targeted correction / rollback. |

**Deliberately not indexed:** `rd_name`, `rt_name`, `source`, `model` alone (covered by
composite leftmost), `created_at`. Names are functionally dependent on their codes —
reports `GROUP BY code, name`. Adding an index per column would roughly double write
cost on a 10M-row load for query paths that don't exist.

### Write-cost trade-off

Ten secondary indexes materially slow a bulk load. Mitigation, built as
`php artisan records:indexes {--drop|--restore}`:

- For the **first** massive historical load into an empty table: drop all non-unique
  indexes, run the import, `ALTER TABLE … ADD INDEX` once at the end (single sort per
  index beats incremental B-tree maintenance across 10M inserts — typically 3–5× faster).
- For **routine** incremental imports (daily files, tens of thousands of rows): keep
  indexes live; the maintenance cost is noise.

### Partitioning — the 1cr+ path

Not applied now (adds operational weight, and the UNIQUE-on-`imei`-only constraint is
incompatible with `RANGE` partitioning unless `imei` joins the partition key). When the
table crosses ~2–3 cr and date-bounded reports dominate:

- `PARTITION BY RANGE (YEAR(st_date))` (or `TO_DAYS` monthly), with `imei` uniqueness
  enforced by a **separate lookup table** (`imei_index(imei PK, record_id)`) or moved to
  application-level dedupe against that lookup.
- Old partitions become cheap to archive/drop. Reports with an `st_date` bound prune to
  one or two partitions.

This is a migration, not a rewrite — the model, imports and reports are unchanged.

---

## 3. Import pipeline

```
Upload ─▶ validate file, hash, detect type, sniff headers
      ─▶ create import_batch (status=pending) + store file on disk
      ─▶ user reviews column mapping + import mode + duplicate strategy
      ─▶ dispatch PrepareImportJob
            • stream-count rows (no full load)
            • slice into chunk descriptors (import_batch_chunks, status=pending)
            • status=processing, fan out ImportChunkJob × N (batched)
      ─▶ ImportChunkJob (idempotent, retryable, one per chunk)
            • OpenSpout streams ONLY this chunk's row range
            • map + validate + normalise dates in PHP (cheap, in-memory per chunk)
            • bulk INSERT valid rows into import_staging_rows (this batch+chunk)
            • one INSERT…SELECT … ON DUPLICATE KEY UPDATE staging ─▶ records
            • bad rows ─▶ import_row_errors (never abort the chunk)
            • update chunk counters + batch counters atomically
      ─▶ FinalizeImportJob (chain barrier)
            • roll chunk counters into the batch, set final status
            • queue summary refresh for affected dates/dimensions
```

### Why these choices

- **OpenSpout, not PhpSpreadsheet/maatwebsite** — true row streaming, ~constant memory
  regardless of file size. maatwebsite's `WithChunkReading` still parses the whole XLSX
  into a spreadsheet model first.
- **Never one request** — `PrepareImportJob` and every `ImportChunkJob` are queued. The
  web request returns the moment the batch row + file are saved.
- **Staging table + `INSERT…SELECT…ON DUPLICATE KEY UPDATE`** — one DB round trip per
  chunk instead of one per row. Idempotent: re-running a chunk overwrites its own staging
  rows and re-applies the same upsert, so a mid-flight crash retry cannot double-insert.
  Laravel's `upsert()` builder is used under the hood where a raw statement isn't needed.
- **No per-row SELECT** for duplicate detection. The UNIQUE key does it in bulk. In-file
  duplicates are caught by deduping the chunk on `imei` before staging (last occurrence
  wins) and logging the earlier ones.
- **Chunk-level progress** — a 10L import is ~20–100 chunks. If the worker dies at chunk
  63, chunks 0–62 stay `completed`, 63 goes back to `pending` on retry, nothing else
  reprocesses. `records` is never corrupted because every chunk apply is idempotent.

### Chunk size — configurable, not hard-coded

`IMPORT_CHUNK_SIZE` (rows read + staged per job) and `IMPORT_INSERT_BATCH` (rows per
bulk INSERT statement into staging) in `.env`.

| Server profile | CHUNK_SIZE | Reasoning |
|---|---|---|
| Shared / 1–2 GB PHP, 1–2 workers | 2,000–5,000 | Keeps per-job memory < ~30 MB, job < ~30 s, safe under `max_execution_time`. |
| Dedicated / 4 GB+, 4+ workers | 10,000–25,000 | Fewer job dispatches and DB round trips; throughput-bound. |
| Batch/import box, 8 GB+, tuned MySQL | 50,000–100,000 | Maximise INSERT…SELECT batch size; watch `innodb_buffer_pool` and redo log. |

Default shipped: **5,000** — the safe middle that works on modest hardware. One
`ImportChunkJob` at 5,000 rows holds well under 50 MB and finishes in a few seconds.

### High-speed path for the first historical load

`php artisan records:import {file} --engine=load-data` — for a one-off multi-million-row
CSV into a near-empty table:

1. `records:indexes --drop`
2. `LOAD DATA LOCAL INFILE` into a raw staging table (fastest ingest MySQL offers).
3. `INSERT … SELECT … ON DUPLICATE KEY UPDATE` staging ▶ `records` (dates normalised via
   SQL `STR_TO_DATE`, or pre-normalised in a quick stream pass).
4. `records:indexes --restore`.

Default engine stays the queued chunk pipeline — it's resumable, observable, and safe on
a live table. `load-data` is an explicit ops tool.

### Duplicate / mode matrix

`import_mode` × `duplicate_strategy` resolve to one upsert behaviour:

| Mode | Existing IMEI | New IMEI |
|---|---|---|
| `insert_new` | skip (count skipped) | insert |
| `skip_existing` | skip | insert |
| `update_existing` | update mapped columns | skip (count skipped) |
| `upsert` | update | insert |

`update` never nulls a column from a blank cell — only non-empty incoming values
overwrite. `source` and `first_import_batch_id` are preserved on update;
`last_import_batch_id` and `updated_at` always advance.

### Import summary (per batch)

Total / Valid / Invalid / New / Updated / Skipped / In-file duplicates / Failed —
each a column on `import_batches`, summed from `import_batch_chunks` at finalize.
Downloadable CSVs: failed rows, duplicate rows, invalid rows (built from
`import_row_errors`, queued export if large).

---

## 4. Reporting

Rules: server-side filter + paginate always; SQL aggregation only
(`COUNT`, `SUM`, `CASE WHEN`, `COUNT(DISTINCT)`, `GROUP BY`, date functions); never
`get()`/`fetch()`/`Collection::groupBy()` on the raw table; `EXPLAIN` every report query
in tests to confirm index use.

Standard reports (RD-wise, RT-wise, TSO-wise, Model-wise, Date-wise, Activation, RD+RT,
IMEI detail, IMEI search) each map to one indexed aggregation query. `is_activated` and
`activation_days` generated columns keep the `CASE WHEN` count and bucket logic on
indexes instead of expressions.

### Summary tables

Populated incrementally at import finalize (only affected dates/dimensions) and fully
rebuildable via `php artisan reports:rebuild-summaries {--from=} {--to=}` (scheduled
nightly as a safety net).

| Table | Grain | Feeds |
|---|---|---|
| `daily_activation_summary` | `st_date` × `model` | Dashboard time series, Date-wise report, activation-rate trend |
| `rd_summary` | `rd_code` (+ `rd_name`) | RD-wise report, RD leaderboard |
| `rt_summary` | `rt_code` (+ `rd_code`) | RT-wise report |
| `model_summary` | `model` | Model-wise report |
| `tso_summary` | `tso` | TSO-wise report |

Each row carries `total_imei`, `activated`, `not_activated`, and lag-bucket counts.
The dashboard reads **only** summary tables → sub-100 ms regardless of raw table size.
Ad-hoc Data Explorer queries the raw table directly (indexed, paginated).

---

## 5. Exports

Queued always. Filters ▶ `export_jobs` row ▶ `BuildExportJob` streams the filtered query
with `->chunkById()` (keyset, not `OFFSET`) into a CSV written to disk in append mode ▶
status/progress polled by Livewire ▶ download link when ready. CSV is the default for
large exports (XLSX only for < ~100k rows). Never build a file in a web request.

---

## 6. Queues & workers

- Dev: `QUEUE_CONNECTION=database` (no Redis dependency; jobs survive a crash in the
  `jobs` table — inherently fail-safe).
- Prod: Redis + `php artisan queue:work --queue=imports,exports,summaries,default`
  under Supervisor (or Horizon once Redis is present). Dedicated `imports` queue so a
  10L file can't starve interactive exports.
- Scheduler: nightly `reports:rebuild-summaries`, `import:reap-stale` (batches stuck in
  `processing` with no chunk progress → mark failed, release chunks).

---

## 7. Security (Phase 6)

`spatie/laravel-permission`. Roles: Super Admin, Admin, Manager, Report User, Import
User. Permissions gate features independently — `imports.*`, `reports.*`, `exports.*`,
`masterdata.*`, `settings.*`, `users.*`. Import permission is separate from report
permission by design.

---

## 8. Build phases

1. **Schema + models + config** ← current
2. Import pipeline (batches, staging, chunked jobs, errors, column mapping, dates)
3. Reporting services + summary tables + rebuild command
4. Dashboard (Livewire, summary-backed)
5. Data Explorer + queued exports
6. Auth, roles, permissions
7. UI shell, layout, polish; Horizon/Supervisor + deployment notes
