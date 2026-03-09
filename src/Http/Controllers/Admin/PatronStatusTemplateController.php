<?php

namespace Dcplibrary\Sfp\Http\Controllers\Admin;

use Dcplibrary\Sfp\Http\Controllers\Controller;
use Dcplibrary\Sfp\Models\CustomField;
use Dcplibrary\Sfp\Models\FormField;
use Dcplibrary\Sfp\Models\MaterialType;
use Dcplibrary\Sfp\Models\PatronStatusTemplate;
use Dcplibrary\Sfp\Models\RequestStatus;
use Illuminate\Http\Request;

class PatronStatusTemplateController extends Controller
{
    public function index()
    {
        return view('sfp::staff.patron-status-templates.index', [
            'templates' => PatronStatusTemplate::with(['requestStatuses', 'materialTypes'])->ordered()->get(),
        ]);
    }

    public function create()
    {
        return view('sfp::staff.patron-status-templates.form', [
            'template' => new PatronStatusTemplate(['enabled' => true, 'subject' => 'Update on your suggestion: {title}']),
            'requestStatuses' => RequestStatus::active()->orderBy('sort_order')->get(),
            'materialTypes' => MaterialType::ordered()->get(),
            'availableTokens' => $this->availableTokens(),
            'subjectExcludedTokens' => $this->subjectExcludedTokens(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateTemplate($request);
        if ($request->boolean('is_default')) {
            PatronStatusTemplate::query()->update(['is_default' => false]);
        }
        $template = PatronStatusTemplate::create([
            'name' => $data['name'],
            'enabled' => $request->boolean('enabled'),
            'subject' => $data['subject'],
            'body' => $data['body'],
            'is_default' => $request->boolean('is_default'),
            'sort_order' => PatronStatusTemplate::max('sort_order') + 1,
        ]);
        $template->requestStatuses()->sync($data['status_ids'] ?? []);
        $template->materialTypes()->sync($data['material_type_ids'] ?? []);
        return redirect()->route('request.staff.settings.notifications', ['tab' => 'emails'])->with('success', 'Template created.');
    }

    public function edit(PatronStatusTemplate $patronStatusTemplate)
    {
        $patronStatusTemplate->load(['requestStatuses', 'materialTypes']);
        return view('sfp::staff.patron-status-templates.form', [
            'template' => $patronStatusTemplate,
            'requestStatuses' => RequestStatus::active()->orderBy('sort_order')->get(),
            'materialTypes' => MaterialType::ordered()->get(),
            'availableTokens' => $this->availableTokens(),
            'subjectExcludedTokens' => $this->subjectExcludedTokens(),
        ]);
    }

    public function update(Request $request, PatronStatusTemplate $patronStatusTemplate)
    {
        $data = $this->validateTemplate($request);
        if ($request->boolean('is_default')) {
            PatronStatusTemplate::whereKeyNot($patronStatusTemplate->id)->update(['is_default' => false]);
        }
        $patronStatusTemplate->update([
            'name' => $data['name'],
            'enabled' => $request->boolean('enabled'),
            'subject' => $data['subject'],
            'body' => $data['body'],
            'is_default' => $request->boolean('is_default'),
        ]);
        $patronStatusTemplate->requestStatuses()->sync($data['status_ids'] ?? []);
        $patronStatusTemplate->materialTypes()->sync($data['material_type_ids'] ?? []);
        return redirect()->route('request.staff.settings.notifications', ['tab' => 'emails'])->with('success', 'Template updated.');
    }

    public function confirmDelete(PatronStatusTemplate $patronStatusTemplate)
    {
        return view('sfp::staff.patron-status-templates.delete', [
            'template' => $patronStatusTemplate,
        ]);
    }

    public function destroy(PatronStatusTemplate $patronStatusTemplate)
    {
        $patronStatusTemplate->delete();
        return redirect()->route('request.staff.settings.notifications', ['tab' => 'emails'])->with('success', 'Template deleted.');
    }

    private function validateTemplate(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'enabled' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'subject' => 'required|string|max:500',
            'body' => 'nullable|string|max:65535',
            'status_ids' => 'nullable|array',
            'status_ids.*' => 'integer|exists:request_statuses,id',
            'material_type_ids' => 'nullable|array',
            'material_type_ids.*' => 'integer|exists:material_types,id',
        ]);
    }

    private function availableTokens(): array
    {
        $system = ['{patron_name}', '{patron_first_name}', '{patron_email}', '{patron_phone}', '{status}', '{status_description}', '{submitted_date}', '{request_url}'];
        $core = ['{title}', '{author}', '{material_type}', '{audience}'];
        $form = [];
        try {
            $form = FormField::ordered()->pluck('key')->map(fn ($k) => "{{$k}}")->all();
        } catch (\Throwable $e) {
        }
        $custom = [];
        try {
            $custom = CustomField::query()
                ->where('active', true)->where('include_as_token', true)
                ->ordered()->pluck('key')->map(fn ($k) => "{{$k}}")->all();
        } catch (\Throwable $e) {
        }
        return array_values(array_unique(array_merge($core, $system, $form, $custom)));
    }

    private function subjectExcludedTokens(): array
    {
        return [
            '{will_pay_up_to}', '{ill_requested}', '{prefer_email}', '{prefer_mail}', '{other_specify}',
            '{publisher}', '{periodical_title}', '{article_author}', '{article_title}', '{volume_number}', '{page_number}',
            '{director}', '{cast}', '{comments}', '{request_url}', '{genre}', '{isbn}', '{publish_date}',
            '{where_heard}', '{date_needed_by}', '{console}', '{patron_email}', '{patron_phone}', '{audience}', '{status_description}',
        ];
    }
}
