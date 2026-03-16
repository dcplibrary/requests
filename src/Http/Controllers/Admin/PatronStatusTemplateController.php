<?php

namespace Dcplibrary\Requests\Http\Controllers\Admin;

use Dcplibrary\Requests\Http\Controllers\Concerns\ProvidesEmailTokens;
use Dcplibrary\Requests\Http\Controllers\Controller;
use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\FieldOption;
use Dcplibrary\Requests\Models\PatronStatusTemplate;
use Dcplibrary\Requests\Models\RequestStatus;
use Illuminate\Http\Request;

/**
 * Manages patron status email templates.
 */
class PatronStatusTemplateController extends Controller
{
    use ProvidesEmailTokens;
    /**
     * List all patron status templates.
     *
     * @param  Request  $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $sortable  = ['name', 'enabled'];
        $sort      = $request->query('sort');
        $direction = strtolower($request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $query = PatronStatusTemplate::with(['requestStatuses', 'fieldOptions.field']);
        if ($sort && in_array($sort, $sortable, true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->ordered();
        }

        return view('requests::staff.patron-status-templates.index', [
            'templates' => $query->get(),
        ]);
    }

    /**
     * Show the create form for a new template.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $mtField = Field::where('key', 'material_type')->first();

        return view('requests::staff.patron-status-templates.form', [
            'template'        => new PatronStatusTemplate(['enabled' => true, 'subject' => 'Update on your suggestion: {title}']),
            'requestStatuses' => RequestStatus::active()->orderBy('sort_order')->get(),
            'materialTypes'   => $mtField ? FieldOption::where('field_id', $mtField->id)->ordered()->get() : collect(),
            'availableTokens'       => $this->availableTokens(includeStatusDescription: true),
            'subjectExcludedTokens' => $this->subjectExcludedTokens(),
        ]);
    }

    /**
     * Store a newly created template.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $data = $this->validateTemplate($request);
        if ($request->boolean('is_default')) {
            PatronStatusTemplate::query()->update(['is_default' => false]);
        }
        $template = PatronStatusTemplate::create([
            'name'       => $data['name'],
            'enabled'    => $request->boolean('enabled'),
            'subject'    => $data['subject'],
            'body'       => $data['body'],
            'is_default' => $request->boolean('is_default'),
            'sort_order' => PatronStatusTemplate::max('sort_order') + 1,
        ]);
        $template->requestStatuses()->sync($data['status_ids'] ?? []);
        $template->fieldOptions()->sync($data['material_type_ids'] ?? []);

        return redirect()->route('request.staff.settings.notifications', ['tab' => 'emails'])->with('success', 'Template created.');
    }

    /**
     * Show the edit form for a template.
     *
     * @param  PatronStatusTemplate  $patronStatusTemplate
     * @return \Illuminate\View\View
     */
    public function edit(PatronStatusTemplate $patronStatusTemplate)
    {
        $patronStatusTemplate->load(['requestStatuses', 'fieldOptions']);
        $mtField = Field::where('key', 'material_type')->first();

        return view('requests::staff.patron-status-templates.form', [
            'template'        => $patronStatusTemplate,
            'requestStatuses' => RequestStatus::active()->orderBy('sort_order')->get(),
            'materialTypes'   => $mtField ? FieldOption::where('field_id', $mtField->id)->ordered()->get() : collect(),
            'availableTokens'       => $this->availableTokens(includeStatusDescription: true),
            'subjectExcludedTokens' => $this->subjectExcludedTokens(),
        ]);
    }

    /**
     * Update the specified template.
     *
     * @param  Request               $request
     * @param  PatronStatusTemplate   $patronStatusTemplate
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, PatronStatusTemplate $patronStatusTemplate)
    {
        $data = $this->validateTemplate($request);
        if ($request->boolean('is_default')) {
            PatronStatusTemplate::whereKeyNot($patronStatusTemplate->id)->update(['is_default' => false]);
        }
        $patronStatusTemplate->update([
            'name'       => $data['name'],
            'enabled'    => $request->boolean('enabled'),
            'subject'    => $data['subject'],
            'body'       => $data['body'],
            'is_default' => $request->boolean('is_default'),
        ]);
        $patronStatusTemplate->requestStatuses()->sync($data['status_ids'] ?? []);
        $patronStatusTemplate->fieldOptions()->sync($data['material_type_ids'] ?? []);

        return redirect()->route('request.staff.settings.notifications', ['tab' => 'emails'])->with('success', 'Template updated.');
    }

    /**
     * Show delete confirmation for a template.
     *
     * @param  PatronStatusTemplate  $patronStatusTemplate
     * @return \Illuminate\View\View
     */
    public function confirmDelete(PatronStatusTemplate $patronStatusTemplate)
    {
        return view('requests::staff.patron-status-templates.delete', [
            'template' => $patronStatusTemplate,
        ]);
    }

    /**
     * Delete the specified template.
     *
     * @param  PatronStatusTemplate  $patronStatusTemplate
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(PatronStatusTemplate $patronStatusTemplate)
    {
        $patronStatusTemplate->delete();

        return redirect()->route('request.staff.settings.notifications', ['tab' => 'emails'])->with('success', 'Template deleted.');
    }

    /**
     * Shared validation rules for store and update.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    private function validateTemplate(Request $request): array
    {
        return $request->validate([
            'name'                 => 'required|string|max:255',
            'enabled'              => 'nullable|boolean',
            'is_default'           => 'nullable|boolean',
            'subject'              => 'required|string|max:500',
            'body'                 => 'nullable|string|max:65535',
            'status_ids'           => 'nullable|array',
            'status_ids.*'         => 'integer|exists:request_statuses,id',
            'material_type_ids'    => 'nullable|array',
            'material_type_ids.*'  => 'integer|exists:field_options,id',
        ]);
    }

}
