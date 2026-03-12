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
        return view('requests::staff.groups.index', array_merge(
            ['groups' => SelectorGroup::with(['fieldOptions.field', 'users'])->get()],
            $this->fieldOptionChoices()
        ));
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

        $group->fieldOptions()->sync($this->flattenFieldOptionIds($data));

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

        $group->fieldOptions()->sync($this->flattenFieldOptionIds($data));

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
            'name'                 => 'required|string|max:100',
            'description'          => 'nullable|string|max:500',
            'active'               => 'boolean',
            'notification_emails'  => 'nullable|string',
            'field_options'        => 'nullable|array',
            'field_options.*'      => 'nullable|array',
            'field_options.*.*'    => 'exists:field_options,id',
        ];
    }

    /**
     * Flatten the nested field_options input into a single array of IDs for sync.
     *
     * Input shape: ['material_type' => [1, 3], 'audience' => [5], 'genre' => [10, 11]]
     * Output:      [1, 3, 5, 10, 11]
     *
     * @param  array  $data  Validated request data.
     * @return array<int>
     */
    private function flattenFieldOptionIds(array $data): array
    {
        $ids = [];
        foreach ($data['field_options'] ?? [] as $fieldOptionIds) {
            if (is_array($fieldOptionIds)) {
                $ids = array_merge($ids, $fieldOptionIds);
            }
        }

        return array_map('intval', array_filter($ids));
    }

    /**
     * Build the filterable field + option collections for the group form.
     *
     * Returns all select/radio fields marked as filterable, each with their
     * active options. The group edit form renders a checkbox section per field.
     *
     * @return array{filterableFields: \Illuminate\Support\Collection}
     */
    private function fieldOptionChoices(): array
    {
        $fields = Field::query()
            ->where('filterable', true)
            ->whereIn('type', ['select', 'radio'])
            ->where('active', true)
            ->ordered()
            ->get();

        $fieldIds = $fields->pluck('id')->all();

        $optionsByFieldId = FieldOption::query()
            ->whereIn('field_id', $fieldIds)
            ->active()
            ->ordered()
            ->get()
            ->groupBy('field_id');

        $filterableFields = $fields->map(function (Field $field) use ($optionsByFieldId) {
            return (object) [
                'key'     => $field->key,
                'label'   => $field->label,
                'options' => $optionsByFieldId->get($field->id, collect()),
            ];
        });

        return ['filterableFields' => $filterableFields];
    }
}
