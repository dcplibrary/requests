<?php

namespace Dcplibrary\Requests\Http\Controllers\Admin;

use Dcplibrary\Requests\Http\Controllers\Controller;
use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\FieldOption;
use Dcplibrary\Requests\Models\Form;
use Dcplibrary\Requests\Models\FormFieldConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Manages field configurations within forms (SFP / ILL).
 */
class FormFieldController extends Controller
{
    /**
     * List all form fields.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('requests::staff.form-fields.index');
    }

    /**
     * Show the form to create a new field.
     *
     * @param  Request  $request
     * @return \Illuminate\View\View
     */
    public function create(Request $request)
    {
        $formSlug = in_array($request->query('form'), ['sfp', 'ill'], true)
            ? $request->query('form')
            : 'sfp';

        return view('requests::staff.form-fields.form', [
            'field'    => new Field(),
            'formSlug' => $formSlug,
        ]);
    }

    /**
     * Store a newly created field.
     *
     * Creates the Field row and the appropriate FormFieldConfig row(s)
     * based on the selected scope (sfp, ill, or both).
     *
     * @param  Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'label'    => 'required|string|max:100',
            'key'      => 'nullable|string|max:100|regex:/^[a-z][a-z0-9_]*$/|unique:fields,key',
            'type'     => ['required', Rule::in(['text', 'textarea', 'html', 'date', 'number', 'checkbox', 'select', 'radio'])],
            'scope'    => ['required', Rule::in(['sfp', 'ill', 'both'])],
            'required' => 'boolean',
            'active'   => 'boolean',
        ]);

        // Auto-generate key from label if not provided
        $key = trim($data['key'] ?? '');
        if ($key === '') {
            $key = Str::snake(Str::ascii($data['label']));
            // Ensure uniqueness by appending a suffix if needed
            $baseKey = $key;
            $suffix  = 1;
            while (Field::withTrashed()->where('key', $key)->exists()) {
                $key = $baseKey . '_' . $suffix++;
            }
        }

        $field = Field::create([
            'key'              => $key,
            'label'            => $data['label'],
            'type'             => $data['type'],
            'scope'            => $data['scope'],
            'required'         => $data['required'] ?? false,
            'active'           => $data['active'] ?? true,
            'sort_order'       => Field::max('sort_order') + 1,
            'include_as_token' => false,
        ]);

        // Determine which forms to attach to based on scope
        $formSlugs = $data['scope'] === 'both' ? ['sfp', 'ill'] : [$data['scope']];

        foreach ($formSlugs as $slug) {
            $formModel = Form::bySlug($slug);
            if (! $formModel) {
                continue;
            }

            $maxOrder = FormFieldConfig::where('form_id', $formModel->id)->max('sort_order') ?? 0;

            FormFieldConfig::create([
                'form_id'    => $formModel->id,
                'field_id'   => $field->id,
                'sort_order' => $maxOrder + 1,
                'required'   => $data['required'] ?? false,
                'visible'    => true,
            ]);
        }

        $tab = $data['scope'] === 'both' ? 'sfp' : $data['scope'];

        return redirect()
            ->route('request.staff.settings.form-fields', ['tab' => $tab])
            ->with('success', "Field '{$field->label}' created.");
    }

    /**
     * Edit this field's configuration for one form only (SFP or ILL).
     *
     * Edits the per-form config (required, visible, conditional_logic); the base
     * field definition is unchanged. Creates the config row if missing.
     *
     * @param  Field   $field
     * @param  string  $form  sfp|ill
     * @return \Illuminate\View\View
     */
    public function editForForm(Field $field, string $form)
    {
        $formModel = Form::bySlug($form);
        if (! $formModel) {
            abort(404);
        }

        $maxOrder = FormFieldConfig::where('form_id', $formModel->id)->max('sort_order') ?? 0;
        $pivot = FormFieldConfig::firstOrCreate(
            [
                'form_id'  => $formModel->id,
                'field_id' => $field->id,
            ],
            [
                'sort_order'        => $maxOrder + 1,
                'conditional_logic' => $field->condition,
                'required'          => $field->required,
                'visible'           => true,
            ]
        );

        $formLabel = $form === 'ill' ? 'Interlibrary Loan' : 'Suggest for Purchase';

        return view('requests::staff.form-fields.edit-for-form', [
            'field'     => $field,
            'pivot'     => $pivot,
            'formSlug'  => $form,
            'formLabel' => $formLabel,
        ]);
    }

    /**
     * Edit a single option's per-form configuration.
     *
     * Only valid for select/radio fields (material_type, audience, genre, etc.).
     *
     * @param  Field   $field
     * @param  string  $form  sfp|ill
     * @param  string  $slug  FieldOption slug
     * @return \Illuminate\View\View
     */
    public function editForFormOption(Field $field, string $form, string $slug)
    {
        abort_unless(in_array($form, ['sfp', 'ill'], true), 404);
        abort_unless(in_array($field->type, ['select', 'radio']), 404, 'This field does not have per-form option overrides.');

        $option = FieldOption::where('field_id', $field->id)->where('slug', $slug)->first();
        abort_unless($option !== null, 404);

        $formLabel  = $form === 'ill' ? 'Interlibrary Loan' : 'Suggest for Purchase';
        $optionName = $option->name;

        return view('requests::staff.form-fields.edit-option-for-form', [
            'field'      => $field,
            'form'       => $form,
            'formSlug'   => $form,
            'formLabel'  => $formLabel,
            'optionName' => $optionName,
            'optionSlug' => $slug,
        ]);
    }

    /**
     * Edit the base field (label, key, active, token) — shared across forms.
     *
     * @param  Field  $field
     * @return \Illuminate\View\View
     */
    public function edit(Field $field)
    {
        return view('requests::staff.form-fields.edit', compact('field'));
    }
}
