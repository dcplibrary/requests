<?php

namespace Dcplibrary\Requests\Livewire;

use Blashbrook\PAPIClient\PAPIClient;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Patron barcode + PIN login against Polaris PAPI (rate-limited).
 */
class PatronPinLogin extends Component
{
    public string $barcode = '';
    public string $pin     = '';
    public bool   $failed  = false;

    private mixed $papiclient;

    public function boot(PAPIClient $papiclient): void
    {
        $this->papiclient = $papiclient;
    }

    public function login(): void
    {
        $key = 'requests-pin-login:' . request()->ip();
        if (! RateLimiter::attempt($key, 5, fn () => true, 60)) {
            $this->addError('pin', 'Too many attempts. Please wait a minute and try again.');
            return;
        }

        $this->validate([
            'barcode' => 'required|min:5|max:20',
            'pin'     => 'required|digits_between:4,6',
        ]);

        $this->failed = false;

        try {
            $barcode  = trim($this->barcode);
            $response = $this->papiclient
                ->method('POST')
                ->uri('authenticator/patron')
                ->params([
                    'Barcode'  => $barcode,
                    'Password' => $this->pin,
                ])
                ->execRequest();

            if (($response['PAPIErrorCode'] ?? -1) !== 0) {
                $this->failed = true;
                return;
            }

            session(['requests_authenticated_barcode' => $barcode]);

            $this->redirect(route('request.patron.requests'));
        } catch (GuzzleException) {
            $this->failed = true;
        }
    }

    public function render()
    {
        return view('requests::livewire.patron-pin-login');
    }
}
