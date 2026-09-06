<?php

namespace App\Services\Reporting;

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
            ->when($this->tso, fn ($q, $v) => $q->where('tso', $v))
            ->when($this->rdCode, fn ($q, $v) => $q->where('rd_code', $v))
            ->when($this->rdName, fn ($q, $v) => $q->where('rd_name', 'like', $v.'%'))
            ->when($this->rtCode, fn ($q, $v) => $q->where('rt_code', $v))
            ->when($this->rtName, fn ($q, $v) => $q->where('rt_name', 'like', $v.'%'))
            ->when($this->model, fn ($q, $v) => $q->where('model', $v))
            ->when($this->source, fn ($q, $v) => $q->where('source', $v))
            ->when($this->imei, fn ($q, $v) => $q->where('imei', $v)) // exact only — indexed
            ->when($this->activationStatus === 'activated', fn ($q) => $q->where('is_activated', 1))
            ->when($this->activationStatus === 'not_activated', fn ($q) => $q->where('is_activated', 0));

        return $query;
    }

    public function isEmpty(): bool
    {
        return $this->toArray() === [];
    }
}
