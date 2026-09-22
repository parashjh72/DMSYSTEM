<div @if ($polling) wire:poll.3s @endif class="space-y-6">
    {{-- Header --}}
    <div>
        <div class="flex items-center gap-2">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Export Center</h1>
            @if ($polling)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-2.5 py-0.5 text-[11px] font-semibold text-blue-700 ring-1 ring-inset ring-blue-600/20">
                    <span class="h-1.5 w-1.5 rounded-full bg-blue-500 animate-pulse"></span>
                    Processing Exports
                </span>
            @endif
        </div>
        <p class="mt-1 text-xs text-slate-500">
            Background generated datasets and report downloads requested from Data Explorer, Stock, and Quick Reports.
        </p>
    </div>

    {{-- Exports Table --}}
    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-xs">
                <thead>
                    <tr class="bg-slate-50/80 text-slate-500">
                        <th class="th">Report Category</th>
                        <th class="th">Job Status</th>
                        <th class="th text-right">Rows</th>
                        <th class="th text-right">File Size</th>
                        <th class="th">Requested By</th>
                        <th class="th">Created</th>
                        <th class="th text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($exports as $e)
                        <tr wire:key="e-{{ $e->id }}" class="hover:bg-slate-50/70 transition-colors">
                            <td class="td font-bold text-slate-900">
                                <span>{{ ucwords(str_replace('_', ' ', $e->type)) }}</span>
                                <span class="badge-slate font-mono uppercase text-[10px] ml-1">{{ $e->format }}</span>
                            </td>
                            <td class="td">
                                <span class="badge {{ match($e->status) {
                                    'completed' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                                    'failed' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
                                    'expired' => 'bg-slate-100 text-slate-600 ring-slate-400/20',
                                    default => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
                                } }} text-[11px] font-semibold">
                                    {{ ucfirst($e->status) }}
                                </span>
                                @if ($e->status === 'processing')
                                    <span class="text-[11px] text-slate-400 ml-1">({{ number_format($e->processed_rows) }} rows)</span>
                                @endif
                            </td>
                            <td class="td text-right font-mono">{{ $e->total_rows !== null ? number_format($e->total_rows) : '—' }}</td>
                            <td class="td text-right font-mono text-slate-500">
                                {{ $e->file_size ? number_format($e->file_size / 1048576, 2).' MB' : '—' }}
                            </td>
                            <td class="td text-slate-600 font-medium">{{ $e->creator?->name ?? 'System / Scheduler' }}</td>
                            <td class="td text-slate-400">{{ $e->created_at->diffForHumans() }}</td>
                            <td class="td text-right">
                                @if ($e->status === 'completed')
                                    <button class="btn-primary !py-1 !px-2.5 text-xs font-semibold" wire:click="download('{{ $e->uuid }}')">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        <span>Download</span>
                                    </button>
                                @elseif ($e->status === 'failed')
                                    <span class="text-xs font-semibold text-rose-600" title="{{ $e->error_message }}">Failed</span>
                                @else
                                    <span class="text-xs text-slate-400">Processing...</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400 text-xs">
                                No export downloads have been generated yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>{{ $exports->links() }}</div>
</div>
