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
# development — reloads code on every job, so edits take effect without a restart
php artisan queue:listen --queue=imports,summaries,exports,default --timeout=3600

# production — faster, but holds code in memory: run `php artisan queue:restart`
# after every deploy or the workers keep running the old code
php artisan queue:work --queue=imports,summaries,exports,default
```

Production: set `QUEUE_CONNECTION=redis`, install Redis, and run the worker under
Supervisor (or `composer require laravel/horizon` once Redis is present). Keep the
`imports` queue on its own worker so a 10-lakh file cannot starve interactive
exports. **Deploy step:** `php artisan queue:restart` — `queue:work` caches code in
memory and will otherwise run stale jobs/services after a release.

### Shared hosting (Hostinger etc.) — no persistent process

If you cannot keep a worker running, **an import will sit in "Queued" forever**.
Add a per-minute cron that drains the queue and exits:

```
* * * * * cd /home/USER/domains/dms.parashojha.com/public_html && \
  /usr/bin/php artisan queue:work --stop-when-empty --max-time=50 \
  --queue=imports,summaries,exports,default >> /dev/null 2>&1
```

To clear a job that is already stuck: run that same `queue:work --stop-when-empty`
once over SSH. `php artisan import:reap-stale` marks batches abandoned after 30 min
so they show as **Failed** (retry from the batch page) rather than hanging.

## Scheduler

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Runs: nightly `reports:rebuild-summaries` (safety net; imports refresh summaries
incrementally already), `import:reap-stale` every 5 min, `exports:prune` nightly,
a per-minute `queue:work --stop-when-empty` (**drains the queue — no separate
worker cron needed on shared hosting**), and per-minute **scheduler + queue
heartbeats** that drive the **System status** card on the dashboard (visible to
`settings.manage` users) — green when the cron and the queue worker are both
alive, red otherwise.

### If cron can only run curl/wget (not a PHP command)

Some panels only let a cron job hit a URL. Set a secret in the production `.env`:

```
CRON_TOKEN=<long-random-string>
```

then `php artisan config:clear`. A per-minute cron of:

```
* * * * * curl -s "https://dms.parashojha.com/cron/<long-random-string>" >/dev/null 2>&1
```

hits `GET /cron/{token}`, which runs `schedule:run` (and therefore the queue
drain) when the token matches. Any other token — or an unset `CRON_TOKEN` —
returns 404, so the endpoint is inert until you configure it.

## Importing

- **UI:** Imports → upload → review the auto-detected column mapping → pick mode +
  chunk size → Start. The detail page shows live progress and lets you retry
  failed chunks.
- **CLI:** `php artisan records:import /abs/path/file.csv --mode=upsert [--chunk=25000] [--sync]`

### Upload size limits

Browser uploads are capped at `IMPORT_MAX_UPLOAD_KB` (default 512 MB, in
`config/import.php` + `config/livewire.php`). The real ceiling is also:

- PHP: `upload_max_filesize` and `post_max_size` (raise both in `php.ini`)
- Web server: nginx `client_max_body_size` / Apache `LimitRequestBody`

`php artisan serve` for dev is started with raised limits in this repo's run
notes:

```bash
php -d upload_max_filesize=512M -d post_max_size=512M -d memory_limit=1G artisan serve
```

For multi-GB files or millions of rows, skip the browser and use
`php artisan records:import` — no upload limit applies.

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

Six-level hierarchy (`database/seeders/RolesAndPermissionsSeeder.php`):

| Role | Can | Data |
|---|---|---|
| Super Admin | everything, incl. user management | all |
| Admin | everything except user management (National Distributor level) | all |
| NSM | same as Admin | all |
| ASM | reports, IMEI search, exports | **only assigned RD codes** |
| TSO | reports, IMEI search, exports | **only assigned RD codes** |
| RD | reports, IMEI search, exports | **only assigned RD codes** |

Re-run `php artisan db:seed --class=RolesAndPermissionsSeeder --force` after a
deploy to apply changes; it also **deletes** any role not in the list above, so
users on an old role (Manager / Report User / Import User) must be re-assigned.

### RD-scoped users (ASM / TSO / RD)

Under **Settings → Users** set the role to **ASM**, **TSO** or **RD** and tick
one or more distributor (RD) codes. That user then sees only
`sales_activation_records` whose `rd_code` is in their list — on every report,
the stock/sellout pivots, IMEI search, Data Explorer and all exports (the scope
is frozen into each queued export and scheduled report so the worker applies it
too). They have no dashboard, master data, or settings access and land on the
Stock Report after signing in. The scope is enforced server-side in
`App\Support\RecordScope`; the three roles differ only by org position — an ASM
is simply assigned more RD codes than a single RD login. Leave the list empty
(Super Admin / Admin / NSM) for unrestricted access.

**Sell-through import:** these roles get the `imports.sell_through` permission,
so **Imports** appears in their sidebar showing only the **Sell-through (RD → RT)**
type — they upload IMEI / RTCode / ST Date to assign a retailer and invoice
date to their own devices. Rows for an IMEI outside their distributors are
rejected ("belongs to another distributor"). Their RD scope is frozen onto the
batch (`import_batches.scope_rd_codes`) so the queue worker enforces it. They
see only their own import batches.

**Creating an RD login:** in **Master Data → Distributors**, "Add distributor"
has an *Also create a login* option (visible to `users.manage` users) that
makes the distributor and an **RD** account scoped to it in one step.

## Scheduled reports (auto-emailed)

**Reports → Scheduled Reports** lets any user with `exports.create` define a
report that is built and emailed on a schedule:

- pick the report (RD/RT/TSO/Model/Date/Stock/Sellout/raw records) and format
  (Excel or CSV);
- a rolling data window — *yesterday*, *last 7 / 30 days*, *last full month*,
  *month to date* — on a chosen date basis (ST / activation / sell-in). Stock
  reports ignore the window (always a live snapshot);
- daily, weekly (a weekday) or monthly (a day 1–28), at a time in
  `config('reports.timezone')` (default **Asia/Kathmandu**);
- one or more recipient emails.

`reports:dispatch-scheduled` runs every minute from the scheduler, queues a
`SendScheduledReportJob` for anything due (once per day, guarded), which builds
the file through the normal export pipeline and emails it as an attachment via
the configured SMTP. An RD-scoped creator's row-scope is frozen into the
schedule, so their emailed file only ever contains their distributors. "Run now" on the list
sends immediately for testing. Needs the scheduler cron **and** working mail.

## Mail (SMTP) & password reset

Users can reset their own password from the **Forgot password?** link on the
sign-in page — this needs working outgoing mail.

Configure SMTP under **Settings → Mail (SMTP)** (admin only): host, port,
encryption, username, password, from-address. Settings are stored in the
`settings` table (password encrypted with `APP_KEY`) and applied over the
`.env` mail config at boot, so no redeploy is needed. Use **Send test email**
to check it. If nothing is configured there, the `MAIL_*` values in `.env`
are used unchanged.

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
