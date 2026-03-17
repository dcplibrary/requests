<?php

namespace Dcplibrary\Requests\Livewire;

use Dcplibrary\Requests\Models\Patron;
use Dcplibrary\Requests\Models\Setting;
use Dcplibrary\Requests\Models\PatronRequest;
use Dcplibrary\Requests\Services\NotificationService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Patron-facing list of the signed-in patron's submitted requests (session barcode).
 */
#[Layout('requests::layouts.requests')]
class PatronRequests extends Component
{
    public function mount(): void
    {
        if (! session()->has('requests_authenticated_barcode')) {
            $this->redirect(route('request.form'));
        }
    }

    public function logout(): void
    {
        session()->forget('requests_authenticated_barcode');
        $this->redirect(route('request.form'));
    }

    public function convertToIll(int $requestId): void
    {
        $barcode = session('requests_authenticated_barcode');
        if (! $barcode) {
            $this->redirect(route('request.form'));
            return;
        }

        $patron = Patron::where('barcode', $barcode)->first();
        if (! $patron) {
            $this->redirect(route('request.form'));
            return;
        }

        /** @var PatronRequest|null $req */
        $req = PatronRequest::whereKey($requestId)
            ->where('patron_id', $patron->id)
            ->first();

        if (! $req) {
            return;
        }

        if (($req->request_kind ?? PatronRequest::KIND_SFP) === PatronRequest::KIND_ILL) {
            return;
        }

        $req->update([
            'request_kind'  => PatronRequest::KIND_ILL,
            'ill_requested' => true,
        ]);

        $req->statusHistory()->create([
            'request_status_id' => $req->request_status_id,
            'user_id'           => null,
            'note'              => 'Converted workflow: sfp → ill (by patron).',
        ]);

        app(NotificationService::class)->notifyStaffNewRequest($req->fresh());

        session()->flash('success', 'Request converted to Interlibrary Loan.');
    }

    public function render()
    {
        $barcode      = session('requests_authenticated_barcode');
        $patron       = $barcode ? Patron::where('barcode', $barcode)->first() : null;
        $limitReached = $patron?->hasReachedLimit() ?? false;
        $limitUntil   = $limitReached ? $patron->nextAvailableDate() : null;
        $requests     = $patron
            ? PatronRequest::with(['status', 'materialType'])
                ->where('patron_id', $patron->id)
                ->latest()
                ->get()
            : collect();

        return view('requests::livewire.patron-requests', [
            'patron'       => $patron,
            'requests'     => $requests,
            'limitReached' => $limitReached,
            'limitUntil'   => $limitUntil,
            'limitCount'   => (int) Setting::get('sfp_limit_count', 5),
        ]);
    }
}
