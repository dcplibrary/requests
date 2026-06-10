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
    /** @var string active|archived|all */
    public string $filter = 'active';

    /**
     * Redirect unauthenticated visitors back to the request form.
     *
     * @return void
     */
    public function mount(): void
    {
        if (! session()->has('requests_authenticated_barcode')) {
            $this->redirect(route('request.form'));
        }
    }

    /**
     * Forget the patron's authenticated barcode session and redirect to the form.
     *
     * @return void
     */
    public function logout(): void
    {
        session()->forget('requests_authenticated_barcode');
        $this->redirect(route('request.form'));
    }

    /**
     * Archive a request on behalf of the authenticated patron.
     *
     * @param  int  $requestId
     * @return void
     */
    public function archive(int $requestId): void
    {
        $req = $this->findPatronRequest($requestId);
        $req?->update(['patron_archived_at' => now()]);
    }

    /**
     * Unarchive a request on behalf of the authenticated patron.
     *
     * @param  int  $requestId
     * @return void
     */
    public function unarchive(int $requestId): void
    {
        $req = $this->findPatronRequest($requestId);
        $req?->update(['patron_archived_at' => null]);
    }

    /**
     * Find a request that belongs to the currently authenticated patron.
     *
     * @param  int  $requestId
     * @return PatronRequest|null
     */
    protected function findPatronRequest(int $requestId): ?PatronRequest
    {
        $barcode = session('requests_authenticated_barcode');
        if (! $barcode) {
            $this->redirect(route('request.form'));
            return null;
        }

        $patron = Patron::where('barcode', $barcode)->first();
        if (! $patron) {
            $this->redirect(route('request.form'));
            return null;
        }

        return PatronRequest::whereKey($requestId)
            ->where('patron_id', $patron->id)
            ->first();
    }

    /**
     * Convert an SFP request to an ILL request on behalf of the authenticated patron.
     * Guards that the request belongs to this patron and is not already ILL.
     *
     * @param  int  $requestId
     * @return void
     */
    public function convertToIll(int $requestId): void
    {
        $req = $this->findPatronRequest($requestId);

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

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function render()
    {
        $barcode      = session('requests_authenticated_barcode');
        $patron       = $barcode ? Patron::where('barcode', $barcode)->first() : null;
        $limitReached = $patron?->hasReachedLimit() ?? false;
        $limitUntil   = $limitReached ? $patron->nextAvailableDate() : null;

        $query = $patron
            ? PatronRequest::with(['status', 'fieldValues.field'])
                ->where('patron_id', $patron->id)
                ->latest()
            : PatronRequest::whereRaw('0 = 1');

        if ($this->filter === 'active') {
            $query->whereNull('patron_archived_at');
        } elseif ($this->filter === 'archived') {
            $query->whereNotNull('patron_archived_at');
        }

        return view('requests::livewire.patron-requests', [
            'patron'       => $patron,
            'requests'     => $query->get(),
            'limitReached' => $limitReached,
            'limitUntil'   => $limitUntil,
            'limitCount'   => (int) Setting::get('sfp_limit_count', 5),
        ]);
    }
}
