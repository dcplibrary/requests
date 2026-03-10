<?php

namespace Dcplibrary\Sfp\Livewire\Admin;

use Dcplibrary\Sfp\Models\CustomField;
use Livewire\Component;

class CustomFields extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $fields = [];

    public function mount(): void
    {
        $this->loadFromDb();
    }

    private function loadFromDb(): void
    {
        $this->fields = CustomField::ordered()
            ->get()
            ->map(fn (CustomField $f) => [
                'id'              => $f->id,
                'key'             => $f->key,
                'label'           => $f->label,
                'type'            => $f->type,
                'step'            => $f->step,
                'request_kind'    => $f->request_kind,
                'active'          => (bool) $f->active,
                'required'        => (bool) $f->required,
                'include_as_token'=> (bool) $f->include_as_token,
                'filterable'      => (bool) $f->filterable,
                'has_condition'   => ! empty($f->condition['rules']),
            ])
            ->values()
            ->toArray();
    }

    public function moveUp(int $index): void
    {
        if ($index <= 0) return;
        $this->fields = array_values($this->fields);
        [$this->fields[$index - 1], $this->fields[$index]] = [$this->fields[$index], $this->fields[$index - 1]];
        $this->fields = array_values($this->fields);
        $this->persistOrder();
    }

    public function moveDown(int $index): void
    {
        if ($index >= count($this->fields) - 1) return;
        $this->fields = array_values($this->fields);
        [$this->fields[$index + 1], $this->fields[$index]] = [$this->fields[$index], $this->fields[$index + 1]];
        $this->fields = array_values($this->fields);
        $this->persistOrder();
    }

    private function persistOrder(): void
    {
        $rows = array_values($this->fields);
        foreach ($rows as $i => $field) {
            CustomField::whereKey($field['id'])->update(['sort_order' => $i + 1]);
        }
        // Do NOT reload from DB — keeping the swapped in-memory array lets the user
        // make multiple sequential reorders without Livewire state getting confused.
    }

    public function toggleActive(int $index): void
    {
        $this->fields[$index]['active'] = ! $this->fields[$index]['active'];
        CustomField::whereKey($this->fields[$index]['id'])->update(['active' => $this->fields[$index]['active']]);
        $this->loadFromDb();
    }

    public function render()
    {
        return view('sfp::livewire.admin.custom-fields');
    }
}

