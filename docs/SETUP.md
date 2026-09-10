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

## Field force

**Hierarchy** — `users.reports_to_id` (self-FK): a TSO reports to an ASM, an ASM
to an NSM. Set it in **Users** (the "Reports to" picker appears for TSO/ASM
roles). Backfilled from RD-code overlap on first migrate. PJP approval routing
freezes `asm_id`/`nsm_id` onto each PJP at submit, resolved from this chain
(falls back to RD-code overlap when the link is missing).

**Retailer fields** — `retailers` now has `area`, `address`, `phone`,
`latitude`, `longitude` (all nullable), edited on **Master Data → Retailers**
(a "Pick location on map" button drops a pin).

**Maps** — a Super Admin enters a Google Maps JavaScript API key under
**Settings → Map settings** (`App\Support\MapConfig`, `settings` table key
`maps`; `GOOGLE_MAPS_API_KEY` in `.env` is the fallback). The key is used
client-side, so restrict it by HTTP referrer in Google Cloud (enable the Maps
JavaScript API and turn on billing too, or the map stays blank and the page
shows why).

**How a retailer gets its coordinates:**
- A **TSO's first visit check-in** with GPS stamps the retailer's
  `latitude`/`longitude` automatically (`PjpService::logVisit` — only when the
  fields are still null). That is the TSO's one shot.
- **Admin / NSM / Super Admin** (`retailer_location.review`) edit any retailer's
  location directly on **Retailer Map** or Master Data.
- A **TSO** wanting to move an already-set location uses **request change** on
  Retailer Map → a row in **Location Requests** (`retailer_location.request`)
  that an Admin approves (applies the coordinates) or rejects.

The **PJP** day planner no longer deals with locations at all — it is a plain
territory-scoped retailer checklist.

**Retailer Map** (`reports.view`, also linked from the Dashboard) plots every
RD-scoped retailer that has coordinates and lists them all, filterable by
distributor / TSO / area / RT code-name, each row with a **View** (TSO visit
timeline) and the location action for the viewer's role. Without a Maps key the
list, filters and location actions still work (coordinates typed by hand).

### Attendance

**Attendance** (`attendance.check` — TSO only): a mobile-first check-in / check-out
that captures GPS via the browser Geolocation API (coordinates can't be typed).
One record per user per day. Duration is computed at check-out. Errors
(permission denied / unavailable / timeout / unsupported) show a plain message.
**Attendance Report** (`attendance.view_all` — Admin/NSM/ASM) filters by date
range / TSO / ASM / RD / status with map links + CSV; ASMs see only their own TSOs.

### PJP (Planned Journey Plan)

**PJP** (`pjp.access`) — one page, tabs by permission:

- **My Plan** (TSO, `pjp.create`) — pick a month → calendar → per-day editor:
  day status (Planned / Leave / Weekly Off / Holiday / No Plan), notes, and a
  territory-scoped retailer multi-select (RD scope + search on code / name /
  phone). **Save Draft**, then **Submit**. Locked from editing once submitted.
- **ASM Review** (`pjp.asm_review`) — queue of the ASM's TSOs' submitted plans;
  open one → **Approve & forward to NSM** (comment optional) or **Request
  revision** (comment required, returns to the TSO).
- **NSM Final Approval** (`pjp.nsm_final_approve`) — queue of ASM-approved plans;
  **Final approve** (locks the PJP — nobody can edit), **Reject**, or **Request
  revision**. *ASM approval is never final.*
- **Reports** (`pjp.report`) — one row per PJP: planned days / planned visits /
  actual visits / achievement % / status / revision count.

After final approval the TSO marks each planned retailer **visited** with a GPS
tap (`pjp_visits`); achievement % = visited ÷ planned. Every transition is in
`pjp_events` (full history) and emails the next actor via the SMTP settings
(best-effort — the in-app queue is the source of truth).

## Promoters (RA)

**Promoters (RA)** (sidebar, `promoters.manage` — Admin / NSM / Super Admin).
Add a promoter with a **type** (Conditional RA / Real RA), an assigned
**retailer**, and a **monthly unit target**. The table then shows, for the
selected month, each promoter's **achieved** = their retailer's activations that
month (`config('promoters.achievement_basis')`, default `activation_date`;
switch to `st_date` for sell-through), the **attainment %**, and a status badge
(Target hit / On track ≥70% / Behind). Filter by month, type or distributor;
export to CSV / Excel. One grouped query drives the whole page.

### RA Requests (TSO → ASM → NSM)

The **RA Requests** tab inside **Promoters (RA)** is the approval workflow for
placing a new promoter. A TSO (who can't see the roster) lands straight on this
tab; managers get both tabs.

- A **TSO** (`promoter_requests.create`) picks an RA type and a retailer in
  their territory. That retailer's **last 3 whole months** of activations /
  sell-through load automatically and are frozen onto the request. Optional
  proposed name, monthly target and note, then Submit.
- **ASM** (`promoter_requests.approve_asm`) sees requests for retailers in
  their RD scope and Approves (→ NSM) or Rejects.
- **NSM** (`promoter_requests.approve_nsm`) gives final approval — which
  **creates the Promoter**, linked back on the request — or Rejects.

Super Admin holds every level; Admin is not in the chain. Status runs
`Awaiting ASM → Awaiting NSM → Approved` (or `Rejected (ASM/NSM)`); the TSO
sees their own requests with each reviewer's note.

## Returns

**Returns** (sidebar) is a two-sided approval flow:

- An **RD** login pastes IMEIs currently at a retailer, in its own
  distributor(s), and submits a return request (`returns.request`). IMEIs that
  are missing, belong to another distributor, aren't at a retailer, or already
  have a pending request are skipped and listed back.
- **Admin / NSM / Super Admin** (`returns.review`) see the pending queue and
  Approve / Reject each request. On **approve**, every still-eligible IMEI has
  its `rt_code`, `rt_name` and `st_date` cleared — it drops back to unassigned
  stock under the same `rd_code`. `activation_date` is left as-is.

Every step writes to **`device_events`**, an immutable per-IMEI log. In
**IMEI Search**, each result row has a **View** button that opens the device
**timeline** — the record's own dates (received / assigned / activated) merged
with the events (return requested / approved / rejected, transfers), oldest
first.

## Annual Contracts

**Contracts → Annual Contracts** (`contracts.manage` — Admin / NSM / Super
Admin) is a CRUD list of sales-volume commitments: pick a retailer, a target
volume (units), an incentive %, and a start / end date. Each row shows the
retailer's **activations within the contract dates** as *Achieved* and the
attainment %. Contracts can be edited, **Closed**, or deleted;
`status` is `active` / `closed` / `cancelled`.

## WOD Coverage

**Reports → WOD Coverage** (`reports.view`) measures *width of distribution* —
for each RD (or TSO territory) and model, the number of **distinct retailers
currently holding unsold stock** of that model. A retailer that has since
activated every unit no longer counts; only live shelf stock
(`is_activated = 0`, sitting at a retailer) is measured. Example: a model that
sits at five retailers with quantities 1, 2, 5, 0, 0 → coverage 3.

- **RD-wise / TSO-wise** tabs — one row per RD or TSO, one column per model.
- Filter by a single **distributor**, a single **model**, and model status
  (**running / out / both**).
- Row *Total RTs* and the column / grand totals are their own
  `COUNT(DISTINCT rt_code)` figures, so they are **not** the sum of the cells
  (a retailer stocking three models is one retailer, not three).
- **Export CSV** streams the current pivot (respects every filter), RD- or
  TSO-scoped. RD-scoped users see only their own distributors' rows.

## Scheduled reports (auto-emailed)

**Reports → Scheduled Reports** (Super Admin only) lets you define a
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
