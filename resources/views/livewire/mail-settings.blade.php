<div>
    <div class="flex items-center gap-2">
        <a href="{{ route('settings.index') }}" wire:navigate class="text-sm text-indigo-600">&larr; Settings</a>
    </div>
    <h1 class="mt-1 text-xl font-semibold tracking-tight">Mail settings (SMTP)</h1>
    <p class="mt-1 text-sm text-gray-500">
        Used for password-reset emails and system notifications. Leave blank to keep using the server's
        <code>.env</code> mail configuration.
    </p>

    <div class="card mt-6 max-w-xl space-y-4">
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="label">SMTP host</label>
                <input class="input" wire:model="host" placeholder="smtp.hostinger.com">
                @error('host')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Port</label>
                <input type="number" class="input" wire:model="port" placeholder="587">
                @error('port')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Encryption</label>
                <select class="input" wire:model="encryption">
                    <option value="tls">STARTTLS (587)</option>
                    <option value="ssl">SSL / TLS (465)</option>
                    <option value="none">None</option>
                </select>
            </div>
            <div>
                <label class="label">Username</label>
                <input class="input" wire:model="username" autocomplete="off" placeholder="noreply@yourdomain.com">
            </div>
            <div>
                <label class="label">Password</label>
                <input type="password" class="input" wire:model="password" autocomplete="new-password"
                       placeholder="{{ $password === '********' ? 'unchanged — type to replace' : '' }}">
            </div>
            <div>
                <label class="label">From address</label>
                <input class="input" wire:model="from_address" placeholder="noreply@yourdomain.com">
                @error('from_address')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">From name</label>
                <input class="input" wire:model="from_name">
                @error('from_name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="flex gap-3">
            <button class="btn-primary" wire:click="save" wire:loading.attr="disabled">Save settings</button>
        </div>
    </div>

    <div class="card mt-4 max-w-xl space-y-3">
        <h2 class="text-sm font-semibold">Send a test email</h2>
        <div class="flex gap-2">
            <input class="input flex-1" wire:model="testTo" placeholder="you@example.com">
            <button class="btn-ghost" wire:click="sendTest" wire:loading.attr="disabled">Send test</button>
        </div>
        <p wire:loading wire:target="sendTest" class="text-xs text-gray-500">Sending…</p>
        @error('testTo')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
</div>
