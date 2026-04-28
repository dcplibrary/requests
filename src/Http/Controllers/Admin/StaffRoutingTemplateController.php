<?php

namespace Dcplibrary\Requests\Http\Controllers\Admin;

use Dcplibrary\Requests\Http\Controllers\Concerns\ProvidesEmailTokens;
use Dcplibrary\Requests\Http\Controllers\Controller;
use Dcplibrary\Requests\Models\SelectorGroup;
use Dcplibrary\Requests\Mail\RequestMail;
use Dcplibrary\Requests\Models\StaffRoutingTemplate;
use Dcplibrary\Requests\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Per–selector-group staff routing emails for new requests.
 */
class StaffRoutingTemplateController extends Controller
{
    use ProvidesEmailTokens;

    /**
     * Show the form to create a staff routing email template for a selector group.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function create(): \Illuminate\View\View
    {
        $assigned = StaffRoutingTemplate::query()->pluck('selector_group_id')->all();
        $groups = SelectorGroup::query()->orderBy('name')->get()
            ->filter(fn (SelectorGroup $g) => ! in_array($g->id, $assigned, true));

        return view('requests::staff.staff-routing-templates.form', [
            'template'              => new StaffRoutingTemplate([
                'enabled' => true,
                'subject' => 'New request: {title}',
            ]),
            'selectorGroups'        => $groups,
            'groupLocked'           => false,
            'availableTokens'       => array_values(array_unique(array_merge(
                $this->availableTokens(),
                ['{action_buttons}', '{convert_to_ill_url}', '{convert_to_ill_link}'],
            ))),
            'subjectExcludedTokens' => $this->subjectExcludedTokens(),
        ]);
    }

    /**
     * Store a new staff routing template.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $this->validated($request, null);

        StaffRoutingTemplate::create([
            'selector_group_id' => $data['selector_group_id'],
            'name'              => $data['name'],
            'enabled'           => $request->boolean('enabled'),
            'subject'           => $data['subject'],
            'body'              => $data['body'] ?? null,
        ]);

        return redirect()->route('request.staff.settings.notifications', ['tab' => 'emails'])
            ->with('success', 'Staff routing template created.');
    }

    /**
     * Show the form to edit an existing staff routing template.
     *
     * @param  \Dcplibrary\Requests\Models\StaffRoutingTemplate  $staffRoutingTemplate
     * @return \Illuminate\Contracts\View\View
     */
    public function edit(StaffRoutingTemplate $staffRoutingTemplate): \Illuminate\View\View
    {
        $staffRoutingTemplate->load('selectorGroup');
        $assigned = StaffRoutingTemplate::query()
            ->where('id', '!=', $staffRoutingTemplate->id)
            ->pluck('selector_group_id')
            ->all();
        $groups = SelectorGroup::query()->orderBy('name')->get()
            ->filter(fn (SelectorGroup $g) => ! in_array($g->id, $assigned, true) || $g->id === $staffRoutingTemplate->selector_group_id);

        return view('requests::staff.staff-routing-templates.form', [
            'template'              => $staffRoutingTemplate,
            'selectorGroups'        => $groups,
            'groupLocked'           => false,
            'availableTokens'       => array_values(array_unique(array_merge(
                $this->availableTokens(),
                ['{action_buttons}', '{convert_to_ill_url}', '{convert_to_ill_link}'],
            ))),
            'subjectExcludedTokens' => $this->subjectExcludedTokens(),
        ]);
    }

    /**
     * Update the specified staff routing template.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Dcplibrary\Requests\Models\StaffRoutingTemplate  $staffRoutingTemplate
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, StaffRoutingTemplate $staffRoutingTemplate): \Illuminate\Http\RedirectResponse
    {
        $data = $this->validated($request, $staffRoutingTemplate);

        $staffRoutingTemplate->update([
            'selector_group_id' => $data['selector_group_id'],
            'name'              => $data['name'],
            'enabled'           => $request->boolean('enabled'),
            'subject'           => $data['subject'],
            'body'              => $data['body'] ?? null,
        ]);

        return redirect()->route('request.staff.settings.notifications', ['tab' => 'emails'])
            ->with('success', 'Staff routing template saved.');
    }

    /**
     * Render a browser preview of the template body as staff would see it in email.
     *
     * @param  \Dcplibrary\Requests\Models\StaffRoutingTemplate  $staffRoutingTemplate
     * @return \Illuminate\Contracts\View\View
     */
    public function preview(StaffRoutingTemplate $staffRoutingTemplate): \Illuminate\View\View
    {
        $rendered = app(NotificationService::class)->renderStaffTemplateForPreview(
            $staffRoutingTemplate->subject,
            (string) ($staffRoutingTemplate->body ?? '')
        );

        return view('requests::mail.notification', ['body' => $rendered['body']]);
    }

    /**
     * Send a test email using this template's subject and body to the given address.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Dcplibrary\Requests\Models\StaffRoutingTemplate  $staffRoutingTemplate
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendTest(Request $request, StaffRoutingTemplate $staffRoutingTemplate): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
        ]);

        $rendered = app(NotificationService::class)->renderStaffTemplateForPreview(
            $staffRoutingTemplate->subject,
            (string) ($staffRoutingTemplate->body ?? '')
        );

        try {
            Mail::to($data['email'])->send(new RequestMail('[Test] ' . $rendered['subject'], $rendered['body'], 'staff'));

            return back()->with('test_success', "Test email sent to {$data['email']}.");
        } catch (\Throwable $e) {
            return back()->with('test_error', 'Failed to send: ' . $e->getMessage());
        }
    }

    /**
     * Show delete confirmation for a staff routing template.
     *
     * @param  \Dcplibrary\Requests\Models\StaffRoutingTemplate  $staffRoutingTemplate
     * @return \Illuminate\Contracts\View\View
     */
    public function confirmDelete(StaffRoutingTemplate $staffRoutingTemplate)
    {
        return view('requests::staff.staff-routing-templates.delete', [
            'template' => $staffRoutingTemplate->load('selectorGroup'),
        ]);
    }

    /**
     * Delete the specified staff routing template.
     *
     * @param  \Dcplibrary\Requests\Models\StaffRoutingTemplate  $staffRoutingTemplate
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(StaffRoutingTemplate $staffRoutingTemplate)
    {
        $staffRoutingTemplate->delete();

        return redirect()->route('request.staff.settings.notifications', ['tab' => 'emails'])
            ->with('success', 'Staff routing template deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?StaffRoutingTemplate $existing): array
    {
        return $request->validate([
            'selector_group_id' => [
                'required',
                'integer',
                Rule::exists('selector_groups', 'id'),
                Rule::unique('staff_routing_templates', 'selector_group_id')->ignore($existing?->id),
            ],
            'name'              => 'required|string|max:150',
            'subject'           => 'required|string|max:500',
            'body'              => 'nullable|string|max:65535',
        ]);
    }
}
