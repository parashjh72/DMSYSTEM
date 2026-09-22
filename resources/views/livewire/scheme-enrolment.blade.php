<div>
    <!-- Page Header -->
    <div class="flex items-center gap-3">
        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-white shadow-md shadow-indigo-500/20">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
            </svg>
        </div>
        <div>
            <h1 class="text-xl font-bold tracking-tight text-gray-900">Retailer Scheme Enrolment</h1>
            <p class="text-xs text-gray-500">Lookup any retail partner and manage active trade scheme participation.</p>
        </div>
    </div>

    <!-- Search Retailer Card -->
    <div class="card mt-6 border border-gray-100 shadow-sm">
        <label class="label text-xs font-semibold text-gray-700">Find Retailer to Enrol</label>
        <div class="relative max-w-xl">
            <input class="input pl-8 text-xs" wire:model.live.debounce.300ms="search" placeholder="Type RT code or retailer name…">
            <svg class="absolute left-2.5 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
        @if (count($searchResults))
            <div class="mt-1.5 max-w-xl max-h-48 overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-lg divide-y divide-gray-100">
                @foreach ($searchResults as $code => $label)
                    <button wire:key="sr-{{ $code }}" wire:click="pick('{{ $code }}')"
                            class="flex items-center justify-between w-full px-3.5 py-2 text-left text-xs hover:bg-indigo-50/75 transition">
                        <span class="font-medium text-gray-800">{{ $label }}</span>
                        <span class="font-semibold text-indigo-600">Select →</span>
                    </button>
                @endforeach
            </div>
        @endif

        @if ($rt)
            <div class="mt-4 rounded-xl bg-gradient-to-r from-indigo-50/70 to-blue-50/70 p-4 border border-indigo-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <div class="text-sm font-bold text-gray-900 flex items-center gap-2">
                        {{ $rt->name }}
                        <span class="font-mono text-xs px-2 py-0.5 rounded-md bg-white border border-indigo-200 text-indigo-700">{{ $rt->code }}</span>
                    </div>
                    <div class="mt-1 flex flex-wrap items-center gap-3 text-xs text-gray-600">
                        <span><strong>Distributor:</strong> {{ $rt->rd_code ?: '—' }}</span>
                        @if ($rt->area) <span>· <strong>Area:</strong> {{ $rt->area }}</span> @endif
                        @if ($rt->phone) <span>· <strong>Phone:</strong> {{ $rt->phone }}</span> @endif
                    </div>
                </div>
                <span class="badge bg-emerald-100 text-emerald-800 ring-1 ring-inset ring-emerald-200 self-start sm:self-auto">Active Partner</span>
            </div>
        @endif
    </div>

    @if ($rt)
        <!-- Enrol Form Card -->
        <div class="card mt-6 border border-gray-100 shadow-sm">
            <h2 class="text-sm font-bold text-gray-900 pb-3 border-b border-gray-100 flex items-center gap-2">
                <span class="flex h-2 w-2 rounded-full bg-indigo-600"></span>
                Enrol {{ $rt->name }} into a Trade Scheme
            </h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-4">
                <div class="sm:col-span-2">
                    <label class="label text-xs font-semibold text-gray-700">Scheme Program</label>
                    <select class="input text-xs" wire:model="enrolSchemeUuid">
                        <option value="">— Select active scheme —</option>
                        @foreach ($schemeOptions as $s)
                            <option value="{{ $s->uuid }}">{{ $s->name }} ({{ $s->effective_from->format('d M') }} – {{ $s->effective_to->format('d M Y') }})</option>
                        @endforeach
                    </select>
                    @error('enrolSchemeUuid') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label text-xs font-semibold text-gray-700">Plan</label>
                    <select class="input text-xs" wire:model="plan">
                        @foreach ($plans as $k => $l) <option value="{{ $k }}">{{ $l }}</option> @endforeach
                    </select>
                </div>
                <div>
                    <label class="label text-xs font-semibold text-gray-700">Category Tier</label>
                    <select class="input text-xs" wire:model="category">
                        @foreach ($categories as $k => $l) <option value="{{ $k }}">{{ $l }}</option> @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button class="btn-primary text-xs" wire:click="enrol">Confirm Enrolment</button>
            </div>
        </div>

        <!-- Current Enrolments Table -->
        <div class="card mt-6 overflow-hidden p-0 border border-gray-200/80 shadow-xs">
            <div class="px-5 py-3.5 bg-gray-50/50 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-xs font-bold uppercase tracking-wider text-gray-700">Participation History</h2>
                <span class="text-xs text-gray-400">{{ count($enrolments) }} Schemes</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-gray-50/75">
                        <tr>
                            <th class="th">Scheme Program</th>
                            <th class="th">Valid Window</th>
                            <th class="th">Enrolled On</th>
                            <th class="th">Assigned Plan</th>
                            <th class="th">Category</th>
                            <th class="th">Status</th>
                            <th class="th text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse ($enrolments as $e)
                        <tr wire:key="e-{{ $e->id }}" class="hover:bg-gray-50/75 transition {{ $e->isActive() ? '' : 'opacity-60 bg-gray-50/50' }}">
                            <td class="td font-bold text-gray-900">{{ $e->scheme?->name ?? '—' }}</td>
                            <td class="td text-gray-500">
                                {{ $e->effective_from?->format('d M Y') ?? '—' }} – {{ $e->effective_to?->format('d M Y') ?? '—' }}
                            </td>
                            <td class="td text-gray-500">{{ $e->enrolled_on?->format('d M Y') ?? '—' }}</td>
                            <td class="td font-medium text-gray-700">{{ $plans[$e->plan] ?? $e->plan }}</td>
                            <td class="td text-gray-600">{{ $categories[$e->category] ?? $e->category }}</td>
                            <td class="td">
                                <span class="badge {{ $e->isActive() ? 'bg-emerald-100 text-emerald-800 ring-1 ring-inset ring-emerald-200' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $e->isActive() ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="td text-right">
                                @if ($e->isActive())
                                    <button class="font-semibold text-amber-600 hover:text-amber-800 text-xs" wire:click="deactivate({{ $e->id }})">Deactivate</button>
                                @else
                                    <button class="font-semibold text-emerald-600 hover:text-emerald-800 text-xs" wire:click="reactivate({{ $e->id }})">Reactivate</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="td text-center text-gray-400 py-8" colspan="7">
                                This retailer is not enrolled in any incentive programs yet.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
