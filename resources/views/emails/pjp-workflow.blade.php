<x-mail::message>
# PJP — {{ $pjp->monthLabel() }}

{{ $body }}

**TSO:** {{ $pjp->tso?->name ?? '—' }}
**Status:** {{ $pjp->statusLabel() }}
**Planned days:** {{ $pjp->planned_days }} · **Planned visits:** {{ $pjp->planned_visits }}

<x-mail::subcopy>
Sent automatically by DM System. Open the PJP in the app to act on it.
</x-mail::subcopy>
</x-mail::message>
