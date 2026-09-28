<div>
    <div class="flex flex-col gap-2">
        <a href="{{ route('explorer', ['f' => ['imei' => $record->imei]]) }}" wire:navigate class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Data Explorer
        </a>
        <div>
            <h1 class="text-xl font-bold tracking-tight text-gray-900">Edit Device <span class="font-mono">{{ $record->imei }}</span></h1>
            <p class="text-xs text-gray-500">{{ $record->model ?? 'Unknown model' }} &middot; sell-in {{ $record->sell_in_date?->format('d M Y') ?? '—' }}@if ($record->last_transfer_date) &middot; last transferred {{ $record->last_transfer_date->format('d M Y') }}@endif. Every change is saved to the audit log with your reason.</p>
        </div>
    </div>

    @if (session()->has('status'))
        <div class="mt-4 rounded-xl bg-emerald-50 p-3 border border-emerald-200 text-xs font-semibold text-emerald-900">{{ session('status') }}</div>
    @endif
    @error('form')
        <div class="mt-4 rounded-xl bg-rose-50 p-3 border border-rose-200 text-xs font-semibold text-rose-800">{{ $message }}</div>
    @enderror

    <div class="mt-6 grid gap-6 lg:grid-cols-5">
        <form wire:submit="save" class="card border border-gray-100 shadow-sm lg:col-span-2 space-y-3">
            <div>
                <label class="label text-xs font-semibold text-gray-700">TSO</label>
                <input type="text" class="input text-xs" list="tso-options" wire:model="tso">
                <datalist id="tso-options">@foreach ($tsoOptions as $name)<option value="{{ $name }}">@endforeach</datalist>
                @error('tso') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label text-xs font-semibold text-gray-700">RD Code</label>
                <input type="text" class="input text-xs font-mono" list="rd-options" wire:model="rd_code">
                <datalist id="rd-options">@foreach ($rdOptions as $rd)<option value="{{ $rd->code }}">{{ $rd->name }}</option>@endforeach</datalist>
                @error('rd_code') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label text-xs font-semibold text-gray-700">RT Code</label>
                <input type="text" class="input text-xs font-mono" list="rt-options" wire:model="rt_code">
                <datalist id="rt-options">@foreach ($rtOptions as $rt)<option value="{{ $rt->code }}">{{ $rt->name }}</option>@endforeach</datalist>
                @error('rt_code') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label text-xs font-semibold text-gray-700">ST Date</label>
                    <input type="date" class="input text-xs" wire:model="st_date">
                    @error('st_date') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label text-xs font-semibold text-gray-700">Activation Date</label>
                    <input type="date" class="input text-xs" wire:model="activation_date">
                    @error('activation_date') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="label text-xs font-semibold text-gray-700">Reason for change <span class="text-rose-500">*</span></label>
                <textarea class="input text-xs" rows="2" wire:model="reason" placeholder="e.g. Invoice corrected by RD on 25 Sep"></textarea>
                @error('reason') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn-primary text-xs" wire:loading.attr="disabled">Save Changes</button>
        </form>

        <div class="card border border-gray-100 shadow-sm lg:col-span-3">
            <h2 class="text-sm font-bold text-gray-900 pb-3 border-b border-gray-100">Change History</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-xs">
                    <thead>
                        <tr class="text-slate-500"><th class="th">When</th><th class="th">Field</th><th class="th">Old &rarr; New</th><th class="th">Source</th><th class="th">By / Reason</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($audits as $audit)
                            <tr wire:key="audit-{{ $audit->id }}">
                                <td class="td whitespace-nowrap">{{ $audit->created_at?->format('d M Y H:i') }}</td>
                                <td class="td font-mono">{{ $audit->field }}</td>
                                <td class="td font-mono"><span class="text-rose-600">{{ $audit->old_value ?? '∅' }}</span> &rarr; <span class="text-emerald-700">{{ $audit->new_value ?? '∅' }}</span></td>
                                <td class="td">
                                    {{ $audit->sourceLabel() }}
                                    @if ($audit->import_batch_id) <span class="text-slate-400">#{{ $audit->import_batch_id }}</span> @endif
                                </td>
                                <td class="td">{{ $audit->user?->name ?? 'System' }}@if ($audit->reason)<div class="text-slate-500">{{ $audit->reason }}</div>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-slate-400">No changes recorded for this device.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
