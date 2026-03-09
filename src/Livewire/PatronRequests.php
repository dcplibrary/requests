<?php

namespace Dcplibrary\Sfp\Livewire;

use Dcplibrary\Sfp\Models\Patron;
use Dcplibrary\Sfp\Models\Setting;
use Dcplibrary\Sfp\Models\SfpRequest;
use Dcplibrary\Sfp\Services\NotificationService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('sfp::layouts.sfp')]
class PatronRequests extends Component
{
    public function mount(): void
    {
        if (! session()->has('sfp_authenticated_barcode')) {
            $this->redirect(route('request.form'));
        }
    }

    public function logout(): void
    {
        session()->forget('sfp_authenticated_barcode');
        $this->redirect(route('request.form'));
    }

    public function convertToIll(int $requestId): void
    {
        $barcode = session('sfp_authenticated_barcode');
        if (! $barcode) {
            $this->redirect(route('request.form'));
            return;
        }

        $patron = Patron::where('barcode', $barcode)->first();
        if (! $patron) {
            $this->redirect(route('request.form'));
            return;
        }

        /** @var SfpRequest|null $req */
        $req = SfpRequest::whereKey($requestId)
            ->where('patron_id', $patron->id)
            ->first();

        if (! $req) {
            return;
        }

        if (($req->request_kind ?? 'sfp') === 'ill') {
            return;
        }

        $req->update([
            'request_kind'  => 'ill',
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
        $barcode      = session('sfp_authenticated_barcode');
        $patron       = $barcode ? Patron::where('barcode', $barcode)->first() : null;
        $limitReached = $patron?->hasReachedLimit() ?? false;
        $limitUntil   = $limitReached ? $patron->nextAvailableDate() : null;
        $requests     = $patron
            ? SfpRequest::with(['status', 'materialType'])
                ->where('patron_id', $patron->id)
                ->latest()
                ->get()
            : collect();

        return view('sfp::livewire.patron-requests', [
            'patron'       => $patron,
            'requests'     => $requests,
            'limitReached' => $limitReached,
            'limitUntil'   => $limitUntil,
            'limitCount'   => (int) Setting::get('sfp_limit_count', 5),
        ]);
    }
}
