<?php

namespace App\Services;

use App\Models\DeviceEvent;
use App\Models\SalesActivationRecord;
use Illuminate\Support\Carbon;

/**
 * Per-device history. Combines the dates already on the record (received /
 * assigned / activated) with the immutable device_events log (returns,
 * transfers) into one date-sorted list for the IMEI Search timeline.
 */
class DeviceTimeline
{
    /**
     * Append one event to a device's history.
     *
     * @param  array<string,mixed>  $meta
     */
    public static function log(
        string $imei,
        string $event,
        string $description,
        array $meta = [],
        ?string $rdCode = null,
        ?string $rtCode = null,
        ?int $causedBy = null,
    ): void {
        DeviceEvent::create([
            'imei' => $imei,
            'event' => $event,
            'description' => mb_substr($description, 0, 255),
            'meta' => $meta ?: null,
            'rd_code' => $rdCode,
            'rt_code' => $rtCode,
            'caused_by' => $causedBy,
            'created_at' => now(),
        ]);
    }

    /**
     * @return list<array{when: string, at: ?string, title: string, detail: ?string, kind: string}>
     */
    public function for(string $imei): array
    {
        $record = SalesActivationRecord::query()->where('imei', $imei)->first();
        $entries = [];

        if ($record) {
            if ($record->sell_in_date) {
                $entries[] = $this->entry($record->sell_in_date, null,
                    'Received into distributor'.($record->rd_code ? " {$record->rd_code}" : ''),
                    $record->rd_name, 'received');
            }
            if ($record->st_date) {
                $entries[] = $this->entry($record->st_date, null,
                    'Assigned to retailer'.($record->rt_code ? " {$record->rt_code}" : ''),
                    $record->rt_name, 'assigned');
            }
            if ($record->activation_date) {
                $entries[] = $this->entry($record->activation_date, null, 'Activated', null, 'activated');
            }
        }

        foreach (DeviceEvent::query()->where('imei', $imei)->orderBy('created_at')->orderBy('id')->get() as $event) {
            $meta = $event->meta ?? [];
            $detail = match ($event->event) {
                'return_approved', 'return_requested' => isset($meta['from_rt_code'])
                    ? 'from retailer '.$meta['from_rt_code'] : null,
                'transferred' => isset($meta['to_rt_code']) ? 'to retailer '.$meta['to_rt_code'] : null,
                default => null,
            };

            // A return clears st_date, so reconstruct the original "assigned" moment
            // from what the approval snapshotted.
            if ($event->event === 'return_approved' && ! empty($meta['from_st_date'])) {
                $entries[] = $this->entry($meta['from_st_date'], null,
                    'Assigned to retailer'.(! empty($meta['from_rt_code']) ? ' '.$meta['from_rt_code'] : ''),
                    $meta['from_rt_name'] ?? null, 'assigned');
            }

            $entries[] = $this->entry(
                $event->created_at->toDateString(),
                $event->created_at,
                $event->description,
                $detail,
                $event->event,
            );
        }

        usort($entries, fn ($a, $b) => [$a['when'], $a['at'] ?? ''] <=> [$b['when'], $b['at'] ?? '']);

        return $entries;
    }

    /**
     * @return array{when: string, at: ?string, title: string, detail: ?string, kind: string}
     */
    private function entry(mixed $date, ?Carbon $at, string $title, ?string $detail, string $kind): array
    {
        return [
            'when' => $date instanceof Carbon ? $date->toDateString() : (string) $date,
            'at' => $at?->toDateTimeString(),
            'title' => $title,
            'detail' => $detail !== null && trim($detail) !== '' ? $detail : null,
            'kind' => $kind,
        ];
    }
}
