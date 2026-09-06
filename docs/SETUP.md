# DM System — setup & operations

## Requirements

- PHP 8.3+ (built and verified on 8.5)
- MySQL 8 / MariaDB 10.6+ (verified on MySQL 9.7)
- Composer 2, Node 20+
- Redis (optional in dev — see Queues)

## First run

```bash
cp .env.example .env          # already done; set DB_* if not root/no-password
composer install
npm install && npm run build
php artisan key:generate
php artisan migrate --seed    # creates schema + roles + Super Admin
php artisan storage:link
php artisan serve
```

Sign in at `/login` with **admin@dmsystem.local** / **password** (change immediately
via the Users screen).

## Queues

Dev default is `QUEUE_CONNECTION=database` — no Redis needed, and jobs survive a
crash in the `jobs` table. Run a worker:

```bash
php artisan queue:work --queue=imports,summaries,exports,default
```

Production: set `QUEUE_CONNECTION=redis`, install Redis, and run the worker under
Supervisor (or `composer require laravel/horizon` once Redis is present). Keep the
`imports` queue on its own worker so a 10-lakh file cannot starve interactive
exports.

## Scheduler

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Runs: nightly `reports:rebuild-summaries` (safety net; imports refresh summaries
incrementally already), `import:reap-stale` every 5 min, `exports:prune` nightly.

## Importing

- **UI:** Imports → upload → review the auto-detected column mapping → pick mode +
  chunk size → Start. The detail page shows live progress and lets you retry
  failed chunks.
- **CLI:** `php artisan records:import /abs/path/file.csv --mode=upsert [--chunk=25000] [--sync]`

### First massive historical load

For a one-off multi-million-row load into an empty table:

```bash
php artisan records:indexes --drop
php artisan records:import /abs/path/history.csv --mode=insert_new --chunk=50000
php artisan queue:work --queue=imports --stop-when-empty      # or run inline with --sync
php artisan records:indexes --restore
php artisan reports:rebuild-summaries
```

Tune `IMPORT_CHUNK_SIZE` / `IMPORT_INSERT_BATCH` in `.env` to the server
(guidance in `config/import.php` and `docs/ARCHITECTURE.md §3`).

## Exports

Queued CSV only. Start from Data Explorer ("Export filtered → CSV") or Reports.
Files land in `storage/app/exports/`, expire after 7 days, download from the
Exports screen.

## Roles

| Role | Can |
|---|---|
| Super Admin | everything, incl. user management |
| Admin | everything except user management |
| Manager | dashboard, reports, explorer, exports, view imports |
| Report User | dashboard, reports, view exports |
| Import User | dashboard, run imports |

## What is verified

- 50k and 8k row imports end-to-end (sync **and** database-queue paths), 0 failed jobs
- multi-format date parsing (Y-m-d, d/m/Y, Excel serials), IMEI cleaning/validation
- in-file + cross-file duplicate handling, all four import modes
- summary rebuild (full + incremental), all 6 report types, IMEI search, exports
- every UI route renders with real data

## Not yet built (documented backlog)

- XLSX export format (CSV only for now — the scalable choice)
- Downloadable per-category error CSVs from the import detail page (data is
  captured in `import_row_errors`; wire it through `ExportDefinition`)
- FK normalization of RD/RT/TSO/Model onto the master tables (tables + sync exist;
  raw table stays denormalized by design)
- Table partitioning by `st_date` (migration path documented in ARCHITECTURE §2)
- System Settings screen for the configurable business rules (lag buckets etc.)
