<?php

namespace Dcplibrary\Sfp\Livewire;

use Dcplibrary\Sfp\Models\Patron;
use Dcplibrary\Sfp\Models\Setting;
use Dcplibrary\Sfp\Models\SfpRequest;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('sfp::layouts.sfp')]
class PatronLookup extends Component
{
    public string $barcode   = '';
    public string $last_name = '';

    public bool $searched  = false;
    public bool $notFound  = false;
    public ?int $patronId  = null;

    public function mount(): void
    {
        if (! Setting::get('patron_lookup_enabled', true)) {
            abort(404);
        }
    }

    public function lookup(): void
    {
        // Rate limiting: 10 attempts per minute per IP
        $key = 'sfp-patron-lookup:' . request()->ip();
        if (! RateLimiter::attempt($key, 10, fn () => true, 60)) {
            $this->addError('barcode', 'Too many lookup attempts. Please wait a minute and try again.');
            return;
        }

        $this->validate([
            'barcode'   => 'required|min:5|max:20',
            'last_name' => 'required|min:1|max:100',
        ]);

        // Match barcode + last name (case-insensitive) to verify identity
        $patron = Patron::where('barcode', trim($this->barcode))
            ->whereRaw('LOWER(name_last) = ?', [strtolower(trim($this->last_name))])
            ->first();

        $this->searched = true;

        if (! $patron) {
            $this->notFound  = true;
            $this->patronId  = null;
        } else {
            $this->notFound  = false;
            $this->patronId  = $patron->id;
        }
    }

    public function startOver(): void
    {
        $this->reset(['barcode', 'last_name', 'searched', 'notFound', 'patronId']);
        $this->resetValidation();
    }

    public function render()
    {
        $requests = null;

        if ($this->searched && ! $this->notFound && $this->patronId) {
            $requests = SfpRequest::with(['status', 'materialType'])
                ->where('patron_id', $this->patronId)
                ->latest()
                ->get();
        }

        return view('sfp::livewire.patron-lookup', [
            'requests' => $requests,
        ]);
    }
}
