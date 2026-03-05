<?php

namespace Dcplibrary\Sfp\Livewire;

use Dcplibrary\Sfp\Models\Patron;
use Dcplibrary\Sfp\Models\Setting;
use Dcplibrary\Sfp\Models\SfpRequest;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('sfp::layouts.sfp')]
class PatronRequests extends Component
{
    public function mount(): void
    {
        if (! session()->has('sfp_authenticated_barcode')) {
            $this->redirect(route('sfp.form'));
        }
    }

    public function logout(): void
    {
        session()->forget('sfp_authenticated_barcode');
        $this->redirect(route('sfp.form'));
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
