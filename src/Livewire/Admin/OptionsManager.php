<?php

namespace Dcplibrary\Sfp\Livewire\Admin;

use Dcplibrary\Sfp\Models\FormField;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Generic inline CRUD manager for any simple option table.
 *
 * Accepts a fully-qualified Eloquent model class that has the columns:
 *   id, name, slug (unique), sort_order, active, timestamps
 *
 * Extra model-specific columns are declared via the $extraFields prop:
 *   ['key' => 'has_other_text',    'label' => 'Shows "other" input', 'type' => 'boolean']
 *   ['key' => 'bibliocommons_value','label' => 'BiblioCommons value', 'type' => 'text']
 *
 * - boolean extras: rendered as a persistent toggle (saves immediately)
 * - text extras:    rendered as additional inputs inside the edit row
 *
 * Reusable for Genre, Console, MaterialType, Audience, and any future
 * option-bearing model — no subclassing required.
 */
class OptionsManager extends Component
{
    /** @var class-string<Model> */
    public string $modelClass = '';

    public string $title = '';

    /**
     * Extra model-specific columns to expose in the UI.
     *
     * Each element: ['key' => string, 'label' => string, 'type' => 'boolean'|'text']
     *
     * @var array<int, array{key: string, label: string, type: string}>
     */
    public array $extraFields = [];

    /**
     * The field key used in sfp_form_fields condition rules that references
     * this model's slugs (e.g. 'material_type', 'audience').
     * Null means this model's slugs are not used in any condition rules.
     */
    public ?string $conditionField = null;

    /** @var array<int, array<string, mixed>> */
    public array $items = [];

    public string $newName = '';

    public ?string $savedMessage = null;

    // ── Lifecycle ────────────────────────────────────────────────────────────

    public function mount(
        string $modelClass,
        string $title = '',
        array $extraFields = [],
        ?string $conditionField = null,
    ): void {
        $this->modelClass     = $modelClass;
        $this->title          = $title ?: class_basename($modelClass) . ' Options';
        $this->extraFields    = $extraFields;
        $this->conditionField = $conditionField;
        $this->loadItems();
    }

    // ── Data ─────────────────────────────────────────────────────────────────

    private function loadItems(): void
    {
        $extra     = $this->extraFields;
        $lockedMap = $this->buildLockedMap();

        $this->items = ($this->modelClass)::ordered()
            ->get()
            ->map(function (Model $item) use ($extra, $lockedMap) {
                $row = [
                    'id'        => $item->id,
                    'name'      => $item->name,
                    'slug'      => $item->slug,
                    'active'    => (bool) $item->active,
                    'locked_by' => $lockedMap[$item->slug] ?? [],
                ];

                foreach ($extra as $ef) {
                    $row[$ef['key']] = $item->{$ef['key']};
                }

                return $row;
            })
            ->values()
            ->toArray();
    }

    /**
     * Build a map of slug → [{ label, key }, ...] for all form fields whose
     * conditional logic rules reference a slug belonging to this model.
     * Returns an empty array when $conditionField is null.
     *
     * @return array<string, array<int, array{label: string, key: string}>>
     */
    private function buildLockedMap(): array
    {
        if (! $this->conditionField) {
            return [];
        }

        $map = [];

        FormField::whereNotNull('condition')->get()->each(function (FormField $ff) use (&$map) {
            foreach ($ff->condition['rules'] ?? [] as $rule) {
                if (($rule['field'] ?? '') !== $this->conditionField) {
                    continue;
                }
                foreach ($rule['values'] ?? [] as $slug) {
                    $map[$slug][] = ['label' => $ff->label, 'key' => $ff->key];
                }
            }
        });

        return $map;
    }

    // ── Add ──────────────────────────────────────────────────────────────────

    public function addItem(): void
    {
        $name = trim($this->newName);
        if ($name === '') {
            return;
        }

        $attrs = [
            'name'       => $name,
            'slug'       => $this->uniqueSlug(Str::slug($name)),
            'sort_order' => (($this->modelClass)::max('sort_order') ?? 0) + 1,
            'active'     => true,
        ];

        // Seed boolean extra fields with a safe default
        foreach ($this->extraFields as $ef) {
            if ($ef['type'] === 'boolean') {
                $attrs[$ef['key']] = false;
            }
        }

        ($this->modelClass)::create($attrs);

        $this->newName = '';
        $this->loadItems();
    }

    // ── Inline edit (name, slug, text extras — called from Alpine via $wire) ──

    /**
     * @param array<string, string> $textExtras  Values for text-type extra fields
     */
    public function updateItem(int $id, string $name, string $slug, array $textExtras = []): void
    {
        $name = trim($name);
        $slug = trim($slug);

        if ($name === '' || $slug === '') {
            return;
        }

        // If the slug is referenced in conditional logic, keep the original slug
        // regardless of what was submitted — the UI prevents editing it, but we
        // defend on the server side too.
        $original = ($this->modelClass)::find($id)?->slug;
        if ($original && $slug !== $original) {
            $lockedMap = $this->buildLockedMap();
            if (! empty($lockedMap[$original])) {
                $slug = $original;
            }
        }

        $conflict = ($this->modelClass)::where('slug', $slug)
            ->where('id', '!=', $id)
            ->exists();

        if ($conflict) {
            $slug = $this->uniqueSlug($slug, $id);
        }

        $attrs = ['name' => $name, 'slug' => $slug];

        foreach ($this->extraFields as $ef) {
            if ($ef['type'] === 'text' && array_key_exists($ef['key'], $textExtras)) {
                $attrs[$ef['key']] = trim((string) $textExtras[$ef['key']]);
            }
        }

        ($this->modelClass)::where('id', $id)->update($attrs);

        $this->loadItems();
        $this->flash('Saved.');
    }

    // ── Toggle active ─────────────────────────────────────────────────────────

    public function toggleActive(int $id): void
    {
        $item = ($this->modelClass)::findOrFail($id);
        $item->update(['active' => ! $item->active]);
        $this->loadItems();
    }

    // ── Toggle boolean extra field ────────────────────────────────────────────

    public function toggleBoolField(int $id, string $field): void
    {
        // Guard: only allow declared boolean extra fields
        $allowed = collect($this->extraFields)
            ->where('type', 'boolean')
            ->pluck('key')
            ->all();

        if (! in_array($field, $allowed, true)) {
            return;
        }

        $item = ($this->modelClass)::findOrFail($id);
        $item->update([$field => ! $item->{$field}]);
        $this->loadItems();
    }

    // ── Reordering ───────────────────────────────────────────────────────────

    public function moveUp(int $id): void
    {
        $idx = collect($this->items)->search(fn ($i) => $i['id'] === $id);
        if ($idx <= 0) {
            return;
        }

        $this->swapSortOrders($this->items[$idx]['id'], $this->items[$idx - 1]['id']);
        $this->loadItems();
    }

    public function moveDown(int $id): void
    {
        $items = collect($this->items);
        $idx   = $items->search(fn ($i) => $i['id'] === $id);

        if ($idx === false || $idx >= $items->count() - 1) {
            return;
        }

        $this->swapSortOrders($this->items[$idx]['id'], $this->items[$idx + 1]['id']);
        $this->loadItems();
    }

    private function swapSortOrders(int $idA, int $idB): void
    {
        $a = ($this->modelClass)::findOrFail($idA);
        $b = ($this->modelClass)::findOrFail($idB);

        [$a->sort_order, $b->sort_order] = [$b->sort_order, $a->sort_order];

        $a->save();
        $b->save();
    }

    // ── Delete ───────────────────────────────────────────────────────────────

    public function deleteItem(int $id): void
    {
        ($this->modelClass)::destroy($id);
        $this->loadItems();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function uniqueSlug(string $base, ?int $excludeId = null): string
    {
        $slug  = $base;
        $i     = 1;
        $query = fn (string $s) => ($this->modelClass)::where('slug', $s)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId));

        while ($query($slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    private function flash(string $message): void
    {
        $this->savedMessage = $message;
    }

    public function clearFlash(): void
    {
        $this->savedMessage = null;
    }

    // ── Render ───────────────────────────────────────────────────────────────

    public function render()
    {
        return view('sfp::livewire.admin.options-manager');
    }
}
