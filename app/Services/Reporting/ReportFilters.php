<?php

namespace App\Services\Reporting;

use App\Support\RecordScope;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Normalised, validated report/explorer filter set. Applies itself to a query
 * builder using only indexed columns (§7). All filtering is server-side.
 */
class ReportFilters
{
    public function __construct(
        public ?string $stDateFrom = null,
        public ?string $stDateTo = null,
        public ?string $activationDateFrom = null,
        public ?string $activationDateTo = null,
        public ?string $sellInDateFrom = null,
        public ?string $sellInDateTo = null,
        public ?string $tso = null,
        public ?string $rdCode = null,
        public ?string $rdName = null,
        public ?string $rtCode = null,
        public ?string $rtName = null,
        public ?string $model = null,
        public ?string $source = null,
        public ?string $imei = null,
        public ?string $activationStatus = null, // activated | not_activated | null
        public ?string $lifecycle = null,        // running | out | null(both) — stock reports
        public array $rtCodes = [],              // multi-select retailers — stock reports
        public ?string $valueFrom = null,        // value report date range
        public ?string $valueTo = null,
        public ?string $valueBasis = null,       // activation_date | st_date
        public ?string $schemeUuid = null,       // scheme achievement export
        public bool $schemeEnrolledOnly = false,
        public ?int $importBatch = null,         // records touched by one import batch
        public array $tsoScope = [],             // TSO-user row scope — always enforced, not user-set
    ) {}

    public static function fromArray(array $data): self
    {
        $clean = fn (?string $k) => isset($data[$k]) && trim((string) $data[$k]) !== ''
            ? trim((string) $data[$k]) : null;

        return new self(
            stDateFrom: $clean('st_date_from'),
            stDateTo: $clean('st_date_to'),
            activationDateFrom: $clean('activation_date_from'),
            activationDateTo: $clean('activation_date_to'),
            sellInDateFrom: $clean('sell_in_date_from'),
            sellInDateTo: $clean('sell_in_date_to'),
            tso: $clean('tso'),
            rdCode: $clean('rd_code'),
            rdName: $clean('rd_name'),
            rtCode: $clean('rt_code'),
            rtName: $clean('rt_name'),
            model: $clean('model'),
            source: $clean('source'),
            imei: $clean('imei'),
            activationStatus: $clean('activation_status'),
            lifecycle: $clean('lifecycle'),
            rtCodes: array_values(array_filter((array) ($data['rt_codes'] ?? []), fn ($v) => trim((string) $v) !== '')),
            valueFrom: $clean('value_from'),
            valueTo: $clean('value_to'),
            valueBasis: $clean('value_basis'),
            schemeUuid: $clean('scheme_uuid'),
            schemeEnrolledOnly: (bool) ($data['scheme_enrolled_only'] ?? false),
            importBatch: isset($data['import_batch']) && $data['import_batch'] !== '' ? (int) $data['import_batch'] : null,
            // Row scope for TSO users. Taken from the stored payload when present
            // (queued exports run without an authenticated user), otherwise from
            // the current request's user. Never comes from user input.
            tsoScope: array_values(array_filter(
                (array) ($data['tso_scope'] ?? RecordScope::tsos() ?? []),
                fn ($v) => trim((string) $v) !== '',
            )),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'st_date_from' => $this->stDateFrom,
            'st_date_to' => $this->stDateTo,
            'activation_date_from' => $this->activationDateFrom,
            'activation_date_to' => $this->activationDateTo,
            'sell_in_date_from' => $this->sellInDateFrom,
            'sell_in_date_to' => $this->sellInDateTo,
            'tso' => $this->tso,
            'rd_code' => $this->rdCode,
            'rd_name' => $this->rdName,
            'rt_code' => $this->rtCode,
            'rt_name' => $this->rtName,
            'model' => $this->model,
            'source' => $this->source,
            'imei' => $this->imei,
            'activation_status' => $this->activationStatus,
            'lifecycle' => $this->lifecycle,
            'rt_codes' => $this->rtCodes ?: null,
            'value_from' => $this->valueFrom,
            'value_to' => $this->valueTo,
            'value_basis' => $this->valueBasis,
            'scheme_uuid' => $this->schemeUuid,
            'scheme_enrolled_only' => $this->schemeEnrolledOnly ?: null,
            'import_batch' => $this->importBatch,
            'tso_scope' => $this->tsoScope ?: null,
        ], fn ($v) => $v !== null);
    }

    public function apply(Builder $query): Builder
    {
        $query
            ->when($this->stDateFrom, fn ($q, $v) => $q->where('st_date', '>=', $v))
            ->when($this->stDateTo, fn ($q, $v) => $q->where('st_date', '<=', $v))
            ->when($this->activationDateFrom, fn ($q, $v) => $q->where('activation_date', '>=', $v))
            ->when($this->activationDateTo, fn ($q, $v) => $q->where('activation_date', '<=', $v))
            ->when($this->sellInDateFrom, fn ($q, $v) => $q->where('sell_in_date', '>=', $v))
            ->when($this->sellInDateTo, fn ($q, $v) => $q->where('sell_in_date', '<=', $v))
            ->when($this->tsoScope !== [], fn ($q) => $q->whereIn('tso', $this->tsoScope))
            ->when($this->tso, fn ($q, $v) => $q->where('tso', $v))
            ->when($this->rdCode, fn ($q, $v) => $q->where('rd_code', $v))
            ->when($this->rdName, fn ($q, $v) => $q->where('rd_name', 'like', $v.'%'))
            ->when($this->rtCode, fn ($q, $v) => $q->where('rt_code', $v))
            ->when($this->rtCodes !== [], fn ($q) => $q->whereIn('rt_code', $this->rtCodes))
            ->when($this->rtName, fn ($q, $v) => $q->where('rt_name', 'like', $v.'%'))
            ->when($this->model, fn ($q, $v) => $q->where('model', $v))
            ->when($this->source, fn ($q, $v) => $q->where('source', $v))
            ->when($this->imei, fn ($q, $v) => $q->where('imei', $v)) // exact only — indexed
            ->when($this->activationStatus === 'activated', fn ($q) => $q->where('is_activated', 1))
            ->when($this->activationStatus === 'not_activated', fn ($q) => $q->where('is_activated', 0))
            ->when($this->importBatch, fn ($q, $v) => $q->where('last_import_batch_id', $v));

        return $query;
    }

    public function isEmpty(): bool
    {
        return $this->toArray() === [];
    }
}
