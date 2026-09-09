<div>
    <h1 class="text-xl font-semibold tracking-tight">Scheme Enrolment</h1>
    <p class="mt-1 text-sm text-gray-500">Enrol an existing retailer into one or more schemes. Deactivating keeps the history.</p>

    <div class="card mt-4">
        <label class="label">Retailer</label>
        <input class="input" wire:model.live.debounce.300ms="search" placeholder="Search RT code or name…">
        @if (count($searchResults))
            <div class="mt-1 max-h-48 overflow-y-auto rounded-lg ring-1 ring-gray-200">
                @foreach ($searchResults as $code => $label)
                    <button wire:key="sr-{{ $code }}" wire:click="pick('{{ $code }}')"
                            class="block w-full px-3 py-1.5 text-left text-xs hover:bg-indigo-50">{{ $label }}</button>
                @endforeach
            </div>
        @endif
        @if ($rt)
            <div class="mt-3 rounded-lg bg-gray-50 p-3 text-sm">
                <div class="font-semibold">{{ $rt->name }} <span class="font-mono text-xs text-gray-500">{{ $rt->code }}</span></div>
                <div class="text-xs text-gray-500">
                    RD {{ $rt->rd_code ?: '—' }}
                    @if ($rt->area) · {{ $rt->area }} @endif
                    @if ($rt->phone) · {{ $rt->phone }} @endif
                </div>
            </div>
        @endif
    </div>

    @if ($rt)
        <div class="card mt-4 space-y-3">
            <h2 class="text-sm font-semibold">Enrol into a scheme</h2>
            <div class="grid gap-3 sm:grid-cols-4">
                <div class="sm:col-span-2">
                    <label class="label">Scheme</label>
                    <select class="input" wire:model="enrolSchemeUuid">
                        <option value="">— choose —</option>
                        @foreach ($schemeOptions as $s)
                            <option value="{{ $s->uuid }}">{{ $s->name }} ({{ $s->effective_from->format('d M') }}–{{ $s->effective_to->format('d M Y') }})</option>
                        @endforeach
                    </select>
                    @error('enrolSchemeUuid') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Plan</label>
                    <select class="input" wire:model="plan">
                        @foreach ($plans as $k => $l) <option value="{{ $k }}">{{ $l }}</option> @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Category</label>
                    <select class="input" wire:model="category">
                        @foreach ($categories as $k => $l) <option value="{{ $k }}">{{ $l }}</option> @endforeach
                    </select>
                </div>
            </div>
            <button class="btn-primary" wire:click="enrol">Enrol</button>
        </div>

        <div class="card mt-4 overflow-x-auto p-0">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr>
                    <th class="th">Scheme</th><th class="th">Window</th><th class="th">Enrolled</th>
                    <th class="th">Plan</th><th class="th">Category</th><th class="th">Status</th><th class="th"></th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                @forelse ($enrolments as $e)
                    <tr wire:key="e-{{ $e->id }}" class="{{ $e->isActive() ? '' : 'opacity-50' }}">
                        <td class="td">{{ $e->scheme?->name ?? '—' }}</td>
                        <td class="td text-xs text-gray-500">
                            {{ $e->effective_from?->format('d M Y') ?? '—' }} – {{ $e->effective_to?->format('d M Y') ?? '—' }}
                        </td>
                        <td class="td text-xs text-gray-500">{{ $e->enrolled_on?->format('d M Y') ?? '—' }}</td>
                        <td class="td text-xs">{{ $plans[$e->plan] ?? $e->plan }}</td>
                        <td class="td text-xs">{{ $categories[$e->category] ?? $e->category }}</td>
                        <td class="td">
                            <span class="badge {{ $e->isActive() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                {{ $e->isActive() ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="td text-right text-xs">
                            @if ($e->isActive())
                                <button class="text-amber-600" wire:click="deactivate({{ $e->id }})">deactivate</button>
                            @else
                                <button class="text-emerald-600" wire:click="reactivate({{ $e->id }})">reactivate</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="td text-sm text-gray-400" colspan="7">Not enrolled in any scheme yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
