<?php

namespace Dcplibrary\Requests\Livewire\Admin;

use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\FieldOption;
use Dcplibrary\Requests\Models\RequestFieldValue;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Legacy admin CRUD for select/radio options on a field (slugs, names, sort order).
 */
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

    /**
     * @return void
     */
    private function loadItems(): void
    {
        $field = Field::findOrFail($this->fieldId);
        $this->lockedBy = $this->buildLockedMap($field);

        $this->items = $field->options()->ordered()->get()
            ->map(fn (FieldOption $o) => [
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
    /**
     * Lock option slugs referenced by conditional rules or existing submissions.
     *
     * @param  Field  $field
     * @return array<string, array<int, array{label: string, key: string}>>
     */
    private function buildLockedMap(Field $field): array
    {
        $map = [];

        // 1) Slugs referenced in conditional logic of other fields
        Field::whereNotNull('condition')->get()->each(function (Field $f) use (&$map, $field) {
            foreach ($f->condition['rules'] ?? [] as $rule) {
                if (($rule['field'] ?? '') !== $field->key) {
                    continue;
                }
                foreach ($rule['values'] ?? [] as $slug) {
                    $map[$slug][] = ['label' => $f->label, 'key' => $f->key];
                }
            }
        });

        // 2) Slugs already used in stored request field values
        $used = RequestFieldValue::query()
            ->where('field_id', $field->id)
            ->select('value')
            ->distinct()
            ->pluck('value')
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

        $field = Field::findOrFail($this->fieldId);

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

        $option = FieldOption::findOrFail($id);
        $field  = Field::findOrFail($this->fieldId);

        $original = $option->slug;
        if ($slug !== $original) {
            $lockedMap = $this->buildLockedMap($field);
            if (! empty($lockedMap[$original])) {
                $slug = $original;
            }
        }

        // Ensure uniqueness within the field.
        $conflict = FieldOption::where('field_id', $field->id)
            ->where('slug', $slug)
            ->where('id', '!=', $id)
            ->exists();
        if ($conflict) {
            $slug = $this->uniqueSlug($slug, $field->id, excludeId: $id);
        }

        $option->update(['name' => $name, 'slug' => $slug]);
        $this->loadItems();
    }

    /**
     * @param  int  $id
     * @return void
     */
    public function toggleActive(int $id): void
    {
        $option = FieldOption::findOrFail($id);
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

    /**
     * @param  int  $idA
     * @param  int  $idB
     * @return void
     */
    private function swapSortOrders(int $idA, int $idB): void
    {
        $a = FieldOption::findOrFail($idA);
        $b = FieldOption::findOrFail($idB);
        [$a->sort_order, $b->sort_order] = [$b->sort_order, $a->sort_order];
        $a->save();
        $b->save();
    }

    /**
     * @param  int  $id
     * @return void
     */
    public function deleteItem(int $id): void
    {
        FieldOption::destroy($id);
        $this->loadItems();
    }

    /**
     * @param  string    $base
     * @param  int       $fieldId
     * @param  int|null  $excludeId
     * @return string
     */
    private function uniqueSlug(string $base, int $fieldId, ?int $excludeId = null): string
    {
        $slug = $base;
        $i = 1;
        $query = fn (string $s) => FieldOption::where('field_id', $fieldId)
            ->where('slug', $s)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId));

        while ($query($slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function render()
    {
        $field = Field::findOrFail($this->fieldId);

        return view('requests::livewire.admin.custom-field-options-manager', [
            'field' => $field,
        ]);
    }
}

