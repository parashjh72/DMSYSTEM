<?php

namespace App\Livewire;

use App\Models\RetailerLocationRequest;
use App\Support\RecordScope;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Location Requests')]
class RetailerLocationRequests extends Component
{
    use WithPagination;

    /** pending | approved | rejected | mine */
    #[Url]
    public string $tab = '';

    public array $noteFor = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('retailer_location.request')
            || auth()->user()?->can('retailer_location.review'), 403);

        $this->tab = $this->tab ?: ($this->canReview() ? 'pending' : 'mine');
    }

    public function canReview(): bool
    {
        return (bool) auth()->user()?->can('retailer_location.review');
    }

    public function updatedTab(): void
    {
        $this->resetPage();
    }

    public function approve(int $id): void
    {
        abort_unless($this->canReview(), 403);

        $req = RetailerLocationRequest::pending()->findOrFail($id);

        DB::transaction(function () use ($req) {
            DB::table('retailers')->where('code', $req->rt_code)->update([
                'latitude' => $req->proposed_latitude,
                'longitude' => $req->proposed_longitude,
                'updated_at' => now(),
            ]);

            $req->update([
                'status' => 'approved',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'review_note' => trim((string) ($this->noteFor[$req->id] ?? '')) ?: null,
            ]);
        });

        unset($this->noteFor[$req->id]);
        session()->flash('status', "Location updated for {$req->rt_code}.");
    }

    public function reject(int $id): void
    {
        abort_unless($this->canReview(), 403);

        $req = RetailerLocationRequest::pending()->findOrFail($id);
        $req->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => trim((string) ($this->noteFor[$req->id] ?? '')) ?: null,
        ]);

        unset($this->noteFor[$req->id]);
        session()->flash('status', 'Request rejected.');
    }

    public function render()
    {
        $mine = $this->tab === 'mine' || ! $this->canReview();

        $rows = RetailerLocationRequest::query()
            ->with(['requester', 'reviewer', 'retailer'])
            ->when($mine, fn ($q) => $q->where('requested_by', auth()->id()))
            ->when(! $mine && $this->tab !== 'all', fn ($q) => $q->where('status', $this->tab))
            ->when(! $mine, fn ($q) => RecordScope::rdCodes()
                ? $q->whereIn('rt_code', fn ($sub) => $sub->select('code')->from('retailers')
                    ->whereIn('rd_code', RecordScope::rdCodes()))
                : $q)
            ->latest('id')
            ->paginate(20);

        return view('livewire.retailer-location-requests', [
            'rows' => $rows,
            'mine' => $mine,
            'pendingCount' => $this->canReview() ? RetailerLocationRequest::pending()->count() : 0,
        ]);
    }
}
