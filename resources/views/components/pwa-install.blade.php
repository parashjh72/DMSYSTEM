{{--
    "Install app" prompt: Android/desktop Chrome via beforeinstallprompt, and an
    Add-to-Home-Screen hint on iPhone Safari. Hidden once installed or dismissed.
--}}
<div x-data="{
        deferred: null,
        show: false,
        ios: false,
        init() {
            const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
            let dismissed = false;
            try { dismissed = localStorage.getItem('dms.installDismissed') === '1'; } catch (e) {}
            if (standalone || dismissed) return;

            this.ios = /iphone|ipad|ipod/i.test(navigator.userAgent) && ! /crios|fxios/i.test(navigator.userAgent);
            if (this.ios) { this.show = true; return; }

            window.__dmsInstallPrompt && (this.deferred = window.__dmsInstallPrompt, this.show = true);
            window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); window.__dmsInstallPrompt = e; this.deferred = e; this.show = true; });
            window.addEventListener('appinstalled', () => { this.show = false; });
        },
        async install() {
            if (! this.deferred) return;
            this.deferred.prompt();
            await this.deferred.userChoice.catch(() => null);
            this.deferred = null;
            window.__dmsInstallPrompt = null;
            this.show = false;
        },
        dismiss() {
            this.show = false;
            try { localStorage.setItem('dms.installDismissed', '1'); } catch (e) {}
        },
     }"
     x-show="show" x-cloak
     class="fixed inset-x-3 bottom-20 z-40 md:bottom-4 md:left-auto md:right-4 md:w-80 rounded-2xl bg-slate-900 text-white shadow-2xl p-3.5 flex items-center gap-3 print:hidden">
    <img src="{{ asset('icons/icon-192.png') }}" alt="" class="h-10 w-10 rounded-xl shrink-0">
    <div class="min-w-0 flex-1 text-xs">
        <div class="font-bold">Install the DMS app</div>
        <div class="text-slate-300" x-show="! ios">Faster access and location sync even after mobile data drops.</div>
        <div class="text-slate-300" x-show="ios">Tap <strong>Share</strong> then <strong>Add to Home Screen</strong>.</div>
    </div>
    <button type="button" x-show="! ios" @click="install()" class="shrink-0 rounded-xl bg-indigo-500 px-3 py-1.5 text-xs font-bold hover:bg-indigo-400">Install</button>
    <button type="button" @click="dismiss()" class="shrink-0 text-slate-400 hover:text-white text-lg leading-none px-1" aria-label="Dismiss">&times;</button>
</div>
