<?php

namespace Dcplibrary\Requests\Http\Controllers\Admin;

use Dcplibrary\Requests\Http\Controllers\Controller;
use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\FieldOption;
use Dcplibrary\Requests\Models\SelectorGroup;
use Dcplibrary\Requests\Models\Setting;
use Illuminate\Http\Request;

/**
 * Manages selector groups and their scoped field options.
 */
class SelectorGroupController extends Controller
{
    /**
     * List all selector groups.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('requests::staff.groups.index', [
            'groups' => SelectorGroup::with(['fieldOptions.field', 'users'])->get(),
        ]);
    }

    /**
     * Show the create form for a new selector group.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('requests::staff.groups.form', array_merge(
            ['group' => new SelectorGroup()],
            $this->fieldOptionChoices()
        ));
    }

    /**
     * Store a newly created selector group.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $data = $request->validate($this->validationRules());

        $group = SelectorGroup::create([
            'name'                => $data['name'],
            'description'         => $data['description'] ?? null,
            'active'              => $data['active'] ?? true,
            'notification_emails' => $data['notification_emails'] ?? null,
        ]);

        $group->fieldOptions()->sync(array_merge(
            $data['material_types'] ?? [],
            $data['audiences'] ?? []
        ));

        return redirect()->route('request.staff.groups.index')->with('success', 'Group created.');
    }

    /**
     * Show the edit form for a selector group.
     *
     * @param  SelectorGroup  $group
     * @return \Illuminate\View\View
     */
    public function edit(SelectorGroup $group)
    {
        return view('requests::staff.groups.form', array_merge(
            ['group' => $group->load('fieldOptions.field')],
            $this->fieldOptionChoices()
        ));
    }

    /**
     * Update the specified selector group.
     *
     * @param  Request        $request
     * @param  SelectorGroup  $group
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, SelectorGroup $group)
    {
        $data = $request->validate($this->validationRules());

        $illGroupId = (int) Setting::get('ill_selector_group_id', 0);
        if ($illGroupId && (int) $group->id === $illGroupId && ($data['active'] ?? false) === false) {
            return back()->withErrors(['error' => 'The ILL access group cannot be deactivated.']);
        }

        $group->update([
            'name'                => $data['name'],
            'description'         => $data['description'] ?? null,
            'active'              => $data['active'] ?? false,
            'notification_emails' => $data['notification_emails'] ?? null,
        ]);

        $group->fieldOptions()->sync(array_merge(
            $data['material_types'] ?? [],
            $data['audiences'] ?? []
        ));

        return redirect()->route('request.staff.groups.index')->with('success', 'Group updated.');
    }

    /**
     * Delete the specified selector group.
     *
     * @param  SelectorGroup  $group
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(SelectorGroup $group)
    {
        $illGroupId = (int) Setting::get('ill_selector_group_id', 0);
        if ($illGroupId && (int) $group->id === $illGroupId) {
            return back()->withErrors(['error' => 'The ILL access group cannot be deleted.']);
        }

        $group->delete();

        return redirect()->route('request.staff.groups.index')->with('success', 'Group deleted.');
    }

    /**
     * Shared validation rules for store and update.
     *
     * @return array<string, mixed>
     */
    private function validationRules(): array
    {
        return [
            'name'               => 'required|string|max:100',
            'description'        => 'nullable|string|max:500',
            'active'             => 'boolean',
            'notification_emails'=> 'nullable|string',
            'material_types'     => 'nullable|array',
            'material_types.*'   => 'exists:field_options,id',
            'audiences'          => 'nullable|array',
            'audiences.*'        => 'exists:field_options,id',
        ];
    }

    /**
     * Build the material type and audience option collections for the form.
     *
     * @return array{materialTypes: \Illuminate\Support\Collection, audiences: \Illuminate\Support\Collection}
     */
    private function fieldOptionChoices(): array
    {
        $mtField  = Field::where('key', 'material_type')->first();
        $audField = Field::where('key', 'audience')->first();

        return [
            'materialTypes' => $mtField
                ? FieldOption::where('field_id', $mtField->id)->active()->ordered()->get()
                : collect(),
            'audiences' => $audField
                ? FieldOption::where('field_id', $audField->id)->active()->ordered()->get()
                : collect(),
        ];
    }
}
