<?php

namespace Dcplibrary\Sfp\Livewire\Admin;

use Dcplibrary\Sfp\Models\CustomField;
use Dcplibrary\Sfp\Models\CustomFieldOption;
use Dcplibrary\Sfp\Models\RequestCustomFieldValue;
use Illuminate\Support\Str;
use Livewire\Component;

class CustomFieldOptionsManager extends Component
{
    public int $fieldId;

    public string $newName = '';

    /** @var array<int, array<string, mixed>> */
    public array $items = [];

    /** @var array<string, array<int, array{label: string, key: string}>> */
    public array $lockedBy = [];

    public function mount(int $fieldId): void
    {
        $this->fieldId = $fieldId;
        $this->loadItems();
    }

    private function loadItems(): void
    {
        $field = CustomField::findOrFail($this->fieldId);
        $this->lockedBy = $this->buildLockedMap($field);

        $this->items = $field->options()->ordered()->get()
            ->map(fn (CustomFieldOption $o) => [
                'id'        => $o->id,
                'name'      => $o->name,
                'slug'      => $o->slug,
                'active'    => (bool) $o->active,
                'locked_by' => $this->lockedBy[$o->slug] ?? [],
            ])
            ->values()
            ->toArray();
    }

    /**
     * Lock option slugs if they are referenced by any custom field conditional rule
     * that targets this field's key, OR if they are already used in submissions.
     *
     * @return array<string, array<int, array{label: string, key: string}>>
     */
    private function buildLockedMap(CustomField $field): array
    {
        $map = [];

        // 1) Slugs referenced in conditional logic of other custom fields
        CustomField::whereNotNull('condition')->get()->each(function (CustomField $cf) use (&$map, $field) {
            foreach ($cf->condition['rules'] ?? [] as $rule) {
                if (($rule['field'] ?? '') !== $field->key) {
                    continue;
                }
                foreach ($rule['values'] ?? [] as $slug) {
                    $map[$slug][] = ['label' => $cf->label, 'key' => $cf->key];
                }
            }
        });

        // 2) Slugs already used in stored request values
        $used = RequestCustomFieldValue::query()
            ->where('custom_field_id', $field->id)
            ->whereNotNull('value_slug')
            ->select('value_slug')
            ->distinct()
            ->pluck('value_slug')
            ->all();
        foreach ($used as $slug) {
            $map[$slug][] = ['label' => 'Existing submissions', 'key' => $field->key];
        }

        return $map;
    }

    public function addItem(): void
    {
        $name = trim($this->newName);
        if ($name === '') return;

        $field = CustomField::findOrFail($this->fieldId);

        $slugBase = Str::slug($name);
        $slug = $this->uniqueSlug($slugBase, $field->id);

        $sort = (int) ($field->options()->max('sort_order') ?? 0) + 1;

        $field->options()->create([
            'name'       => $name,
            'slug'       => $slug,
            'sort_order' => $sort,
            'active'     => true,
        ]);

        $this->newName = '';
        $this->loadItems();
    }

    public function updateItem(int $id, string $name, string $slug): void
    {
        $name = trim($name);
        $slug = trim($slug);
        if ($name === '' || $slug === '') return;

        $option = CustomFieldOption::findOrFail($id);
        $field  = CustomField::findOrFail($this->fieldId);

        $original = $option->slug;
        if ($slug !== $original) {
            $lockedMap = $this->buildLockedMap($field);
            if (! empty($lockedMap[$original])) {
                $slug = $original;
            }
        }

        // Ensure uniqueness within the field.
        $conflict = CustomFieldOption::where('custom_field_id', $field->id)
            ->where('slug', $slug)
            ->where('id', '!=', $id)
            ->exists();
        if ($conflict) {
            $slug = $this->uniqueSlug($slug, $field->id, excludeId: $id);
        }

        $option->update(['name' => $name, 'slug' => $slug]);
        $this->loadItems();
    }

    public function toggleActive(int $id): void
    {
        $option = CustomFieldOption::findOrFail($id);
        $option->update(['active' => ! $option->active]);
        $this->loadItems();
    }

    public function moveUp(int $id): void
    {
        $this->items = array_values($this->items);
        $idx = collect($this->items)->search(fn ($i) => $i['id'] === $id);
        if ($idx <= 0) return;

        $this->swapSortOrders($this->items[$idx]['id'], $this->items[$idx - 1]['id']);
        [$this->items[$idx - 1], $this->items[$idx]] = [$this->items[$idx], $this->items[$idx - 1]];
        $this->items = array_values($this->items);
    }

    public function moveDown(int $id): void
    {
        $this->items = array_values($this->items);
        $items = collect($this->items);
        $idx   = $items->search(fn ($i) => $i['id'] === $id);
        if ($idx === false || $idx >= $items->count() - 1) return;

        $this->swapSortOrders($this->items[$idx]['id'], $this->items[$idx + 1]['id']);
        [$this->items[$idx + 1], $this->items[$idx]] = [$this->items[$idx], $this->items[$idx + 1]];
        $this->items = array_values($this->items);
    }

    private function swapSortOrders(int $idA, int $idB): void
    {
        $a = CustomFieldOption::findOrFail($idA);
        $b = CustomFieldOption::findOrFail($idB);
        [$a->sort_order, $b->sort_order] = [$b->sort_order, $a->sort_order];
        $a->save();
        $b->save();
    }

    public function deleteItem(int $id): void
    {
        CustomFieldOption::destroy($id);
        $this->loadItems();
    }

    private function uniqueSlug(string $base, int $fieldId, ?int $excludeId = null): string
    {
        $slug = $base;
        $i = 1;
        $query = fn (string $s) => CustomFieldOption::where('custom_field_id', $fieldId)
            ->where('slug', $s)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId));

        while ($query($slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    public function render()
    {
        $field = CustomField::findOrFail($this->fieldId);

        return view('sfp::livewire.admin.custom-field-options-manager', [
            'field' => $field,
        ]);
    }
}

