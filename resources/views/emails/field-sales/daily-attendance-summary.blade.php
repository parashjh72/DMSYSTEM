<x-mail::message>
# Team attendance — {{ $date->format('D, d M Y') }}

{{ \App\Support\NepaliDate::formatLong($date) }} BS · Hello {{ $manager->name }}, here is today's field attendance for your team.

@foreach ($totals as $label => $count)
**{{ $label }}:** {{ $count }}@if (! $loop->last) · @endif
@endforeach

<x-mail::table>
| Name | Status | In | Out | Km | Check-in point |
|:-----|:-------|:---|:----|---:|:---------------|
@foreach ($rows as $row)
| {{ $row['name'] }} | {{ $row['status'] }} | {{ $row['check_in'] ?? '—' }} | {{ $row['check_out'] ?? '—' }} | {{ number_format($row['km'], 1) }} | {{ $row['geofence'] ?? '—' }} |
@endforeach
</x-mail::table>

<x-mail::subcopy>
Sent automatically by DM System. Open Field Sales → Live Map or the Monthly Attendance report for details.
</x-mail::subcopy>
</x-mail::message>
