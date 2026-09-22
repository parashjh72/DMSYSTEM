<div>
    <!-- Breadcrumb & Header -->
    <div class="flex flex-col gap-2">
        <a href="{{ route('schemes.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Schemes
        </a>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-gray-900">{{ $scheme->name }} — Enrolled Retailers</h1>
                <p class="text-xs text-gray-500">Manage enrolled retail partners, tiers, and default achievement plans for this program.</p>
            </div>
            <span class="badge bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-200 self-start sm:self-auto">
                {{ count($enrolled) }} Retailers Enrolled
            </span>
        </div>
    </div>

    <!-- Enrollment Controls Card -->
    <div class="card mt-6 border border-gray-100 shadow-sm">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 pb-4 border-b border-gray-100">
            <div>
                <label class="label text-xs font-semibold text-gray-700">Default Plan (For new adds)</label>
                <select class="input text-xs" wire:model="defaultPlan">
                    @foreach ($plans as $k => $l) <option value="{{ $k }}">{{ $l }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label text-xs font-semibold text-gray-700">Default Category</label>
                <select class="input text-xs" wire:model="defaultCategory">
                    @foreach ($categories as $k => $l) <option value="{{ $k }}">{{ $l }} (Min slab {{ $catMinSlab[$k] }})</option> @endforeach
                </select>
            </div>
        </div>

        <div class="mt-4 grid gap-6 lg:grid-cols-2">
            <!-- Single Search & Add -->
            <div>
                <label class="label text-xs font-semibold text-gray-700">Instant Search & Add</label>
                <div class="relative">
                    <input type="search" class="input text-xs pl-8" placeholder="Type RT code or shop name…"
                           wire:model.live.debounce.300ms="search">
                    <svg class="absolute left-2.5 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                @if ($searchResults)
                    <div class="mt-1 max-h-48 overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-lg divide-y divide-gray-100">
                        @foreach ($searchResults as $code => $label)
                            <button wire:key="sr-{{ $code }}" wire:click="add('{{ $code }}')"
                                    class="flex items-center justify-between w-full px-3.5 py-2 text-left text-xs text-gray-800 hover:bg-indigo-50/75 transition">
                                <span class="font-medium">{{ $label }}</span>
                                <span class="font-semibold text-indigo-600">+ Enrol</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Bulk Add -->
            <div>
                <label class="label text-xs font-semibold text-gray-700">Bulk Add Retailers</label>
                <textarea rows="3" class="input font-mono text-xs" wire:model="paste"
                          placeholder="Paste RT codes or names (one per line or comma-separated)"></textarea>
                <div class="mt-2 flex items-center justify-between">
                    <button class="btn-primary text-xs" wire:click="matchAndAdd">Match & Enrol All</button>
                    @if ($notMatched)
                        <span class="text-xs text-amber-600 font-medium">Unmatched: {{ \Illuminate\Support\Str::limit(implode(', ', $notMatched), 60) }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Retailers Table -->
    <div class="card mt-6 overflow-hidden p-0 border border-gray-200/80 shadow-xs">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                <thead class="bg-gray-50/75">
                    <tr>
                        <th class="th">RT Code</th>
                        <th class="th">Retailer Name</th>
                        <th class="th">Enrolled Date</th>
                        <th class="th">Status</th>
                        <th class="th">Assigned Plan</th>
                        <th class="th">Category</th>
                        <th class="th w-24">Min Slab</th>
                        <th class="th">Notes</th>
                        <th class="th text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($enrolled as $e)
                    <tr wire:key="en-{{ $e->id }}" class="hover:bg-gray-50/75 transition {{ $e->isActive() ? '' : 'opacity-60 bg-gray-50/50' }}">
                        <td class="td font-mono font-semibold text-gray-900">{{ $e->rt_code }}</td>
                        <td class="td font-medium text-gray-800">{{ $e->rt_name }}</td>
                        <td class="td text-gray-500">{{ $e->enrolled_on?->format('d M Y') ?? '—' }}</td>
                        <td class="td">
                            <span class="badge {{ $e->isActive() ? 'bg-emerald-100 text-emerald-800 ring-1 ring-inset ring-emerald-200' : 'bg-gray-100 text-gray-600' }}">
                                {{ $e->isActive() ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="td">
                            <select class="input py-1 text-xs" wire:change="updateRow({{ $e->id }}, 'plan', $event.target.value)">
                                @foreach ($plans as $k => $l) <option value="{{ $k }}" @selected($e->plan === $k)>{{ $l }}</option> @endforeach
                            </select>
                        </td>
                        <td class="td">
                            <select class="input py-1 text-xs" wire:change="updateRow({{ $e->id }}, 'category', $event.target.value)">
                                @foreach ($categories as $k => $l) <option value="{{ $k }}" @selected($e->category === $k)>{{ $l }}</option> @endforeach
                            </select>
                        </td>
                        <td class="td">
                            <input type="number" min="1" class="input w-16 py-1 text-xs font-mono text-center"
                                   value="{{ $e->min_slab }}"
                                   placeholder="{{ $catMinSlab[$e->category] ?? 1 }}"
                                   wire:change="updateMinSlab({{ $e->id }}, $event.target.value)">
                        </td>
                        <td class="td">
                            <input class="input py-1 text-xs" value="{{ $e->note }}"
                                   placeholder="Add note..."
                                   wire:change="updateRow({{ $e->id }}, 'note', $event.target.value)">
                        </td>
                        <td class="td whitespace-nowrap text-right space-x-2">
                            @if ($e->isActive())
                                <button class="font-semibold text-amber-600 hover:text-amber-800 text-xs" wire:click="deactivate({{ $e->id }})">Deactivate</button>
                            @else
                                <button class="font-semibold text-emerald-600 hover:text-emerald-800 text-xs" wire:click="reactivate({{ $e->id }})">Reactivate</button>
                            @endif
                            <span class="text-gray-300">·</span>
                            <button class="font-semibold text-rose-600 hover:text-rose-800 text-xs" wire:click="remove({{ $e->id }})" wire:confirm="Remove this retailer enrolment?">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="td text-center text-gray-400 py-10" colspan="9">
                            <svg class="mx-auto h-8 w-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            <p class="mt-2 text-xs">No retailers enrolled in this scheme yet.</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
