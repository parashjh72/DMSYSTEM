{{--
    Background GPS breadcrumbs for a checked-in TSO (public/js/field-tracker.js).
    Fixes are stored on the phone first and synced whenever there is data —
    including by the service worker after the app is closed. Stops at check-out.
--}}
@php
    $todayRecord = auth()->user()?->todayAttendance;
    $onDuty = config('tracking.enabled') && $todayRecord && ! $todayRecord->isCheckedOut();
    $trackerVersion = @filemtime(public_path('js/field-tracker.js')) ?: 1;
@endphp
<script src="{{ asset('js/field-tracker.js') }}?v={{ $trackerVersion }}"></script>
<script>
(function boot() {
    if (! window.DmsTracker) { setTimeout(boot, 50); return; }
    window.DmsTracker.boot({
        url: @json(route('tracking.pings')),
        csrf: document.querySelector('meta[name="csrf-token"]')?.content || '',
        userId: @json(auth()->id()),
        active: @json($onDuty),
        interval: @json((int) config('tracking.interval_seconds')),
        minMove: @json((int) config('tracking.min_move_metres')),
        flush: @json((int) config('tracking.flush_seconds')),
    });
})();
</script>
