<div>
    <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-gray-900">Date Conflicts</h1>
            <p class="text-xs text-gray-500">Devices stored with an impossible date order. Fix the source file and re-import with overwrite, or correct one device with Edit.</p>
        </div>
        <button wire:click="download" class="btn-ghost text-xs">Download CSV</button>
    </div>

    <div class="mt-5 grid gap-3 sm:grid-cols-2">
        @foreach (['activation_before_st' => 'Activation before ST date', 'st_before_sell_in' => 'ST date before sell-in date'] as $key => $label)
            <button wire:click="$set('rule', '{{ $rule === $key ? 'all' : $key }}')"
                    class="card border text-left transition {{ $rule === $key ? 'border-indigo-500 ring-1 ring-indigo-500' : 'border-gray-100 hover:border-gray-300' }}">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">{{ $label }}</div>
                <div class="mt-1 text-2xl font-extrabold {{ $counts[$key] > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ number_format($counts[$key]) }}</div>
            </button>
        @endforeach
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-2">
        <select wire:model.live="rdCode" class="input text-xs w-auto">
            <option value="">All distributors</option>
            @foreach ($rdOptions as $rd)
                <option value="{{ $rd->code }}">{{ $rd->code }} — {{ $rd->name }}</option>
            @endforeach
        </select>
        @if ($rule !== 'all')
            <button wire:click="$set('rule', 'all')" class="text-xs font-semibold text-indigo-600">Show both rules</button>
        @endif
    </div>

    <div class="mt-4 rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-xs">
                <thead>
                    <tr class="bg-slate-50/80 text-slate-500">
                        <th class="th">IMEI</th><th class="th">Model</th><th class="th">RD</th><th class="th">RT</th>
                        <th class="th">Sell-in</th><th class="th">ST Date</th><th class="th">Activation</th><th class="th">Problem</th><th class="th"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $r)
                        @php
                            $sellIn = \App\Livewire\DateConflicts::date($r->sell_in_date);
                            $st = \App\Livewire\DateConflicts::date($r->st_date);
                            $act = \App\Livewire\DateConflicts::date($r->activation_date);
                        @endphp
                        <tr wire:key="dc-{{ $r->imei }}" class="hover:bg-slate-50/70">
                            <td class="td font-mono">{{ $r->imei }}</td>
                            <td class="td">{{ $r->model ?? '—' }}</td>
                            <td class="td font-mono">{{ $r->rd_code ?? '—' }}</td>
                            <td class="td font-mono">{{ $r->rt_code ?? '—' }}</td>
                            <td class="td font-mono {{ $st && $sellIn && $st < $sellIn ? 'text-rose-600 font-bold' : '' }}">{{ $sellIn ?? '—' }}</td>
                            <td class="td font-mono">{{ $st ?? '—' }}</td>
                            <td class="td font-mono {{ $act && $st && $act < $st ? 'text-rose-600 font-bold' : '' }}">{{ $act ?? '—' }}</td>
                            <td class="td text-rose-700">
                                @if ($act && $st && $act < $st) Activated {{ \Carbon\Carbon::parse($act)->diffInDays($st) }}d before ST @endif
                                @if ($st && $sellIn && $st < $sellIn) ST {{ \Carbon\Carbon::parse($st)->diffInDays($sellIn) }}d before sell-in @endif
                            </td>
                            <td class="td text-right">
                                <a href="{{ route('devices.edit', $r->imei) }}" wire:navigate class="font-semibold text-indigo-600 hover:text-indigo-800">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="py-12 text-center text-slate-400">No date conflicts. 🎉</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
</div>
