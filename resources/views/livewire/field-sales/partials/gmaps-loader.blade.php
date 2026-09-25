{{-- Shared Google Maps JS loader (same contract as the Retailer Map / map picker). --}}
<script>
    window.__gmapsKey = window.__gmapsKey || @json(\App\Support\MapConfig::apiKey());
    window.__gmapsCentre = window.__gmapsCentre || @json(\App\Support\MapConfig::defaultCentre());
    window.__loadGmaps = window.__loadGmaps || function () {
        if (window.__gmapsPromise) return window.__gmapsPromise;
        if (! window.__gmapsKey) return Promise.reject(new Error('no-key'));
        window.__gmapsPromise = new Promise((resolve, reject) => {
            window.__gmapsReject = reject;
            window.__gmapsReady = () => resolve();
            window.gm_authFailure = () => reject(new Error('auth'));
            const s = document.createElement('script');
            s.src = 'https://maps.googleapis.com/maps/api/js?key='
                + encodeURIComponent(window.__gmapsKey)
                + '&loading=async&callback=__gmapsReady';
            s.async = true;
            s.onerror = () => reject(new Error('load-failed'));
            document.head.appendChild(s);
        });
        return window.__gmapsPromise;
    };
    window.__gmapsErrorText = window.__gmapsErrorText || function (e) {
        return {
            'no-key': 'No Google Maps key configured — a Super Admin sets one in Settings → Map settings.',
            'auth': 'Google rejected the Maps key. Enable the Maps JavaScript API and billing, and allow this site in the key’s HTTP-referrer list.',
            'load-failed': 'Google Maps could not load (network or blocked script).',
        }[e && e.message] || 'Google Maps failed to load.';
    };
</script>
