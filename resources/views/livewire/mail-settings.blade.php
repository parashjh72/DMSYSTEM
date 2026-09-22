<div>
    <!-- Breadcrumb & Header -->
    <div class="flex flex-col gap-2">
        <a href="{{ route('settings.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Settings
        </a>
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-white shadow-md shadow-indigo-500/20">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
            </div>
            <div>
                <h1 class="text-xl font-bold tracking-tight text-gray-900">Mail Configuration (SMTP)</h1>
                <p class="text-xs text-gray-500">Outbound email settings for password resets, daily PJP route notifications, and scheduled reports.</p>
            </div>
        </div>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-3 max-w-5xl">
        <!-- SMTP Settings Form -->
        <div class="card lg:col-span-2 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <h2 class="text-sm font-bold text-gray-900">Server Credentials</h2>
                <span class="badge bg-indigo-50 text-indigo-700">Hostinger / Custom SMTP</span>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label text-xs font-semibold text-gray-700">SMTP Host</label>
                    <input class="input text-xs font-mono" wire:model="host" placeholder="smtp.hostinger.com">
                    @error('host')<p class="text-[11px] text-rose-600 mt-0.5">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label text-xs font-semibold text-gray-700">Port</label>
                    <input type="number" class="input text-xs font-mono" wire:model="port" placeholder="587">
                    @error('port')<p class="text-[11px] text-rose-600 mt-0.5">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label text-xs font-semibold text-gray-700">Encryption Protocol</label>
                    <select class="input text-xs" wire:model="encryption">
                        <option value="tls">STARTTLS (Port 587)</option>
                        <option value="ssl">SSL / TLS (Port 465)</option>
                        <option value="none">None (Insecure / Internal)</option>
                    </select>
                </div>
                <div>
                    <label class="label text-xs font-semibold text-gray-700">SMTP Username</label>
                    <input class="input text-xs font-mono" wire:model="username" autocomplete="off" placeholder="noreply@dms.parashojha.com">
                </div>
                <div>
                    <label class="label text-xs font-semibold text-gray-700">SMTP Password</label>
                    <input type="password" class="input text-xs font-mono" wire:model="password" autocomplete="new-password"
                           placeholder="{{ $password === '********' ? '•••••••• (unchanged)' : 'Enter password' }}">
                </div>
                <div>
                    <label class="label text-xs font-semibold text-gray-700">From Email Address</label>
                    <input class="input text-xs font-mono" wire:model="from_address" placeholder="noreply@dms.parashojha.com">
                    @error('from_address')<p class="text-[11px] text-rose-600 mt-0.5">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label text-xs font-semibold text-gray-700">From Display Name</label>
                    <input class="input text-xs" wire:model="from_name" placeholder="Distribution Management System">
                    @error('from_name')<p class="text-[11px] text-rose-600 mt-0.5">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-6 flex items-center justify-between border-t border-gray-100 pt-4">
                <p class="text-[11px] text-gray-400">Leave blank to fallback to local <code>.env</code> file.</p>
                <button class="btn-primary text-xs" wire:click="save" wire:loading.attr="disabled">
                    Save Mail Configuration
                </button>
            </div>
        </div>

        <!-- Diagnostic Test Widget -->
        <div class="space-y-4">
            <div class="card border border-gray-100 shadow-sm">
                <div class="flex items-center gap-2 pb-3 border-b border-gray-100">
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-gray-700">SMTP Handshake Test</h2>
                </div>
                <p class="mt-3 text-xs text-gray-500 leading-relaxed">
                    Verify connectivity, TLS certificate validation, and outbound delivery directly from this server.
                </p>

                <div class="mt-4 space-y-3">
                    <div>
                        <label class="label text-xs font-semibold text-gray-700">Recipient Email</label>
                        <input class="input text-xs" wire:model="testTo" placeholder="your-email@example.com">
                        @error('testTo')<p class="text-[11px] text-rose-600 mt-0.5">{{ $message }}</p>@enderror
                    </div>
                    <button class="btn-primary w-full text-xs flex items-center justify-center gap-1.5" wire:click="sendTest" wire:loading.attr="disabled">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        Send Test Dispatch
                    </button>
                    <div wire:loading wire:target="sendTest" class="flex items-center justify-center gap-2 text-xs font-medium text-indigo-600 py-1">
                        <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Connecting to mail exchanger…
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
