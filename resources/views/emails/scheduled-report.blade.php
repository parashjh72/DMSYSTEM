<x-mail::message>
# {{ $report->name }}

Your scheduled **{{ config('reports.schedulable.'.$report->export_type.'.0', $report->export_type) }}** is attached.

@if ($window['from'])
**Period covered:** {{ \Illuminate\Support\Carbon::parse($window['from'])->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($window['to'])->format('d M Y') }}
@else
**Period covered:** current snapshot
@endif

**Rows:** {{ number_format($rowCount) }}
**Schedule:** {{ $report->scheduleLabel() }}

<x-mail::subcopy>
This report is sent automatically by DM System. To change recipients or the
schedule, open **Reports → Scheduled Reports**.
</x-mail::subcopy>
</x-mail::message>
