<?php

namespace Dcplibrary\Sfp\Livewire\Admin;

use Dcplibrary\Sfp\Models\Form;
use Dcplibrary\Sfp\Models\FormCustomField;
use Dcplibrary\Sfp\Models\FormFormField;
use Illuminate\Support\Facades\URL;
use Livewire\Component;

/**
 * Admin component for managing field order and conditions per form.
 * Two separate lists (Suggest for Purchase, Interlibrary Loan) so each can be controlled independently.
 */
class FormFields extends Component
{
    /** @var array<int, array<string, mixed>> SFP form fields (from form_form_fields pivot) */
    public array $sfpFields = [];

    /** @var array<int, array<string, mixed>> ILL form fields + custom fields */
    public array $illFields = [];

    /** Active tab: 'sfp' | 'ill' */
    public string $activeFormTab = 'sfp';

    public function mount(): void
    {
        $tab = request()->get('tab');
        if (in_array($tab, ['sfp', 'ill'], true)) {
            $this->activeFormTab = $tab;
        }
        $this->loadFromDb();
    }

    private function loadFromDb(): void
    {
        $formSfp = Form::bySlug('sfp');
        $formIll = Form::bySlug('ill');
        if (! $formSfp || ! $formIll) {
            $this->sfpFields = [];
            $this->illFields = [];
            return;
        }

        $sfpFormPivots = FormFormField::where('form_id', $formSfp->id)
            ->with('formField')
            ->orderBy('sort_order')
            ->get();
        $sfpCustomPivots = FormCustomField::where('form_id', $formSfp->id)
            ->with('customField')
            ->orderBy('sort_order')
            ->get();

        $sfpRows = $sfpFormPivots->map(fn (FormFormField $p) => $this->formFieldRow($p, 'sfp'))->values()->all();
        foreach ($sfpCustomPivots as $p) {
            $cf = $p->customField;
            if (! $cf) {
                continue;
            }
            $sfpRows[] = [
                'pivot_id'           => $p->id,
                'source'             => 'custom',
                'form_scope'         => null,
                'id'                 => $cf->id,
                'key'                => $cf->key,
                'label'              => $cf->label,
                'type'               => $cf->type,
                'active'             => (bool) $p->visible,
                'required'           => (bool) $p->required,
                'include_as_token'   => (bool) $cf->include_as_token,
                'has_condition'      => ! empty($p->conditional_logic['rules'] ?? $cf->condition['rules'] ?? null),
                'condition'          => $p->conditional_logic ?? $cf->condition ?? ['match' => 'all', 'rules' => []],
                'sort_order'         => $p->sort_order,
                'edit_url'           => $this->customFieldEditUrl($cf->id, 'sfp'),
            ];
        }
        $this->sfpFields = collect($sfpRows)->sortBy('sort_order')->values()->toArray();

        $formPivots = FormFormField::where('form_id', $formIll->id)
            ->with('formField')
            ->orderBy('sort_order')
            ->get();
        $customPivots = FormCustomField::where('form_id', $formIll->id)
            ->with('customField')
            ->orderBy('sort_order')
            ->get();

        $illRows = $formPivots->map(fn (FormFormField $p) => $this->formFieldRow($p, 'ill'))->values()->all();
        foreach ($customPivots as $p) {
            $cf = $p->customField;
            if (! $cf) {
                continue;
            }
            $illRows[] = [
                'pivot_id'           => $p->id,
                'source'             => 'custom',
                'form_scope'         => null,
                'id'                 => $cf->id,
                'key'                => $cf->key,
                'label'              => $cf->label,
                'type'               => $cf->type,
                'active'             => (bool) $p->visible,
                'required'           => (bool) $p->required,
                'include_as_token'   => (bool) $cf->include_as_token,
                'has_condition'      => ! empty($p->conditional_logic['rules'] ?? $cf->condition['rules'] ?? null),
                'condition'          => $p->conditional_logic ?? $cf->condition ?? ['match' => 'all', 'rules' => []],
                'sort_order'         => $p->sort_order,
                'edit_url'           => $this->customFieldEditUrl($cf->id, 'ill'),
            ];
        }
        $this->illFields = collect($illRows)->sortBy('sort_order')->values()->toArray();
    }

    private function formFieldRow(FormFormField $pivot, string $form): array
    {
        $f = $pivot->formField;
        $condition = $pivot->conditional_logic ?? $f->condition ?? ['match' => 'all', 'rules' => []];

        $row = [
            'pivot_id'          => $pivot->id,
            'source'            => 'form',
            'id'                => $f->id,
            'key'               => $f->key,
            'label'             => $pivot->label_override !== null && $pivot->label_override !== '' ? $pivot->label_override : $f->label,
            'type'              => $f->type ?? 'text',
            'form_scope'        => $f->form_scope ?? 'global',
            'active'            => (bool) $pivot->visible,
            'required'          => (bool) $pivot->required,
            'include_as_token'  => (bool) $f->include_as_token,
            'has_condition'     => ! empty($condition['rules']),
            'condition'         => $condition,
            'sort_order'        => $pivot->sort_order,
        ];
        $row['edit_url'] = $this->formFieldEditForFormUrl($f->id, $form);

        return $row;
    }

    /** Build URL for per-form form field edit (avoids relying on named route). */
    private function formFieldEditForFormUrl(int $fieldId, string $form): string
    {
        $prefix = trim(config('sfp.route_prefix', 'request'), '/');
        $path = '/' . $prefix . '/staff/settings/form-fields/' . $fieldId . '/form/' . $form . '/edit';

        return URL::to($path);
    }

    /** Build URL for per-form custom field edit (avoids relying on named route). */
    private function customFieldEditUrl(int $fieldId, string $formSlug): string
    {
        $prefix = trim(config('sfp.route_prefix', 'request'), '/');
        $path = '/' . $prefix . '/staff/settings/custom-fields/' . $fieldId . '/form/' . $formSlug . '/edit';

        return URL::to($path);
    }

    public function moveUpSfp(int $index): void
    {
        if ($index <= 0) {
            return;
        }
        $this->sfpFields = array_values($this->sfpFields);
        [$this->sfpFields[$index - 1], $this->sfpFields[$index]] =
            [$this->sfpFields[$index], $this->sfpFields[$index - 1]];
        $this->sfpFields = array_values($this->sfpFields);
        $this->persistSfp();
    }

    public function moveDownSfp(int $index): void
    {
        if ($index >= count($this->sfpFields) - 1) {
            return;
        }
        $this->sfpFields = array_values($this->sfpFields);
        [$this->sfpFields[$index + 1], $this->sfpFields[$index]] =
            [$this->sfpFields[$index], $this->sfpFields[$index + 1]];
        $this->sfpFields = array_values($this->sfpFields);
        $this->persistSfp();
    }

    public function moveUpIll(int $index): void
    {
        if ($index <= 0) {
            return;
        }
        $this->illFields = array_values($this->illFields);
        [$this->illFields[$index - 1], $this->illFields[$index]] =
            [$this->illFields[$index], $this->illFields[$index - 1]];
        $this->illFields = array_values($this->illFields);
        $this->persistIll();
    }

    public function moveDownIll(int $index): void
    {
        if ($index >= count($this->illFields) - 1) {
            return;
        }
        $this->illFields = array_values($this->illFields);
        [$this->illFields[$index + 1], $this->illFields[$index]] =
            [$this->illFields[$index], $this->illFields[$index + 1]];
        $this->illFields = array_values($this->illFields);
        $this->persistIll();
    }

    private function persistSfp(): void
    {
        $rows = array_values($this->sfpFields);
        foreach ($rows as $i => $row) {
            $order = $i + 1;
            $source = $row['source'] ?? '';
            if ($source === 'form') {
                FormFormField::where('id', $row['pivot_id'])->update(['sort_order' => $order]);
            } elseif ($source === 'custom') {
                FormCustomField::where('id', $row['pivot_id'])->update(['sort_order' => $order]);
            }
            // Update the in-memory sort_order so subsequent moves stay in sync
            $this->sfpFields[$i]['sort_order'] = $order;
        }
        // Do NOT reload from DB here — keeping the swapped in-memory array lets the user
        // make multiple sequential reorders without Livewire state getting confused.
    }

    private function persistIll(): void
    {
        $rows = array_values($this->illFields);
        foreach ($rows as $i => $row) {
            $order = $i + 1;
            $source = $row['source'] ?? '';
            if ($source === 'form') {
                FormFormField::where('id', $row['pivot_id'])->update(['sort_order' => $order]);
            } elseif ($source === 'custom') {
                FormCustomField::where('id', $row['pivot_id'])->update(['sort_order' => $order]);
            }
            // Update the in-memory sort_order so subsequent moves stay in sync
            $this->illFields[$i]['sort_order'] = $order;
        }
        // Do NOT reload from DB here — keeping the swapped in-memory array lets the user
        // make multiple sequential reorders without Livewire state getting confused.
    }

    public function render()
    {
        return view('sfp::livewire.admin.form-fields');
    }
}
