<?php

namespace Dcplibrary\Sfp\Http\Controllers\Admin;

use Dcplibrary\Sfp\Http\Controllers\Controller;
use Dcplibrary\Sfp\Models\MaterialType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MaterialTypeController extends Controller
{
    public function index()
    {
        return view('sfp::staff.material-types.index', [
            'types' => MaterialType::orderBy('sort_order')->get(),
        ]);
    }

    public function create()
    {
        return view('sfp::staff.material-types.form', ['type' => new MaterialType()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'sort_order'    => 'required|integer|min:0',
            'active'        => 'boolean',
            'has_other_text'=> 'boolean',
        ]);

        MaterialType::create(array_merge($data, ['slug' => Str::slug($data['name'])]));

        return redirect()->route('sfp.staff.material-types.index')->with('success', 'Material type created.');
    }

    public function edit(MaterialType $materialType)
    {
        return view('sfp::staff.material-types.form', ['type' => $materialType]);
    }

    public function update(Request $request, MaterialType $materialType)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'sort_order'    => 'required|integer|min:0',
            'active'        => 'boolean',
            'has_other_text'=> 'boolean',
        ]);

        $materialType->update($data);

        return redirect()->route('sfp.staff.material-types.index')->with('success', 'Material type updated.');
    }

    public function destroy(MaterialType $materialType)
    {
        $request = request();
        $sfpUser = $this->currentSfpUser($request);
        if (! $sfpUser || ! $sfpUser->isAdmin()) {
            abort(403);
        }

        $requestsCount  = $materialType->requests()->count();
        $materialsCount = $materialType->materials()->count();
        $groupsCount    = $materialType->selectorGroups()->count();

        if ($requestsCount > 0 || $materialsCount > 0 || $groupsCount > 0) {
            $data = $request->validate([
                'reassign_to_id' => 'required|integer|exists:material_types,id|different:' . $materialType->id,
            ]);

            DB::transaction(function () use ($materialType, $data) {
                DB::table('requests')
                    ->where('material_type_id', $materialType->id)
                    ->update(['material_type_id' => (int) $data['reassign_to_id']]);

                DB::table('materials')
                    ->where('material_type_id', $materialType->id)
                    ->update(['material_type_id' => (int) $data['reassign_to_id']]);

                $groupIds = DB::table('selector_group_material_type')
                    ->where('material_type_id', $materialType->id)
                    ->pluck('selector_group_id')
                    ->all();

                foreach ($groupIds as $groupId) {
                    DB::table('selector_group_material_type')->insertOrIgnore([
                        'selector_group_id' => (int) $groupId,
                        'material_type_id'  => (int) $data['reassign_to_id'],
                    ]);
                }

                DB::table('selector_group_material_type')
                    ->where('material_type_id', $materialType->id)
                    ->delete();

                $materialType->delete();
            });

            return redirect()->route('sfp.staff.material-types.index')->with('success', 'Material type deleted and records reassigned.');
        }

        return redirect()->route('sfp.staff.material-types.index')->with('success', 'Material type deleted.');
    }

    public function confirmDelete(MaterialType $materialType)
    {
        $request = request();
        $sfpUser = $this->currentSfpUser($request);
        if (! $sfpUser || ! $sfpUser->isAdmin()) {
            abort(403);
        }

        $requestsCount  = $materialType->requests()->count();
        $materialsCount = $materialType->materials()->count();
        $groupsCount    = $materialType->selectorGroups()->count();

        $requestPreview = $materialType->requests()
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'submitted_title'])
            ->map(fn ($r) => [
                'mono'  => "#{$r->id}",
                'label' => (string) ($r->submitted_title ?? 'Request'),
                'href'  => route('sfp.staff.requests.show', $r->id),
            ])->all();

        $materialPreview = $materialType->materials()
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'title'])
            ->map(fn ($m) => [
                'mono'  => "#{$m->id}",
                'label' => (string) ($m->title ?? 'Material'),
                'href'  => route('sfp.staff.titles.show', $m->id),
            ])->all();

        $groupPreview = $materialType->selectorGroups()
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name'])
            ->map(fn ($g) => [
                'mono'  => "#{$g->id}",
                'label' => (string) $g->name,
                'href'  => route('sfp.staff.groups.edit', $g->id),
            ])->all();

        $options = MaterialType::query()
            ->whereKeyNot($materialType->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (MaterialType $t) => ['id' => $t->id, 'label' => $t->name])
            ->values()
            ->all();

        if (empty($options) && ($requestsCount > 0 || $materialsCount > 0 || $groupsCount > 0)) {
            return back()->withErrors(['error' => 'You must create another material type before deleting this one (records need a reassignment target).']);
        }

        return view('sfp::staff.settings.reassign-delete', [
            'title'       => 'Delete Material Type',
            'itemLabel'   => $materialType->name,
            'impacts'     => [
                "{$requestsCount} request(s) will be reassigned",
                "{$materialsCount} material record(s) will be reassigned",
                "{$groupsCount} selector group assignment(s) will be updated",
            ],
            'previews'    => [
                ['title' => 'Requests', 'count' => $requestsCount, 'count_label' => 'total', 'items' => $requestPreview],
                ['title' => 'Materials', 'count' => $materialsCount, 'count_label' => 'total', 'items' => $materialPreview],
                ['title' => 'Selector groups', 'count' => $groupsCount, 'count_label' => 'total', 'items' => $groupPreview],
            ],
            'options'     => $options,
            'deleteAction'=> route('sfp.staff.material-types.destroy', $materialType),
            'cancelHref'  => route('sfp.staff.material-types.index'),
            'extraFields' => [],
        ]);
    }
}
