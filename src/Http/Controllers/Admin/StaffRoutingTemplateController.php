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

    public function preview(StaffRoutingTemplate $staffRoutingTemplate): \Illuminate\View\View
    {
        $rendered = app(NotificationService::class)->renderStaffTemplateForPreview(
            $staffRoutingTemplate->subject,
            (string) ($staffRoutingTemplate->body ?? '')
        );

        return view('requests::mail.notification', ['body' => $rendered['body']]);
    }

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
            Mail::to($data['email'])->send(new RequestMail('[Test] ' . $rendered['subject'], $rendered['body']));

            return back()->with('test_success', "Test email sent to {$data['email']}.");
        } catch (\Throwable $e) {
            return back()->with('test_error', 'Failed to send: ' . $e->getMessage());
        }
    }

    public function confirmDelete(StaffRoutingTemplate $staffRoutingTemplate)
    {
        return view('requests::staff.staff-routing-templates.delete', [
            'template' => $staffRoutingTemplate->load('selectorGroup'),
        ]);
    }

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
