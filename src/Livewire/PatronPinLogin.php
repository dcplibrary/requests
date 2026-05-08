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

    /**
     * Inject PAPIClient dependency (called by Livewire on each request before the action method).
     */
    public function boot(PAPIClient $papiclient): void
    {
        $this->papiclient = $papiclient;
    }

    /**
     * Validate barcode + PIN, authenticate against Polaris PAPI, and start a session.
     * Rate-limited to 5 attempts per IP per 60 seconds.
     *
     * @return void
     */
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

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function render()
    {
        return view('requests::livewire.patron-pin-login');
    }
}
