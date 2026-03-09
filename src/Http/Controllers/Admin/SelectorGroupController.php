<?php

namespace Dcplibrary\Sfp\Http\Controllers\Admin;

use Dcplibrary\Sfp\Http\Controllers\Controller;
use Dcplibrary\Sfp\Models\Audience;
use Dcplibrary\Sfp\Models\MaterialType;
use Dcplibrary\Sfp\Models\SelectorGroup;
use Dcplibrary\Sfp\Models\Setting;
use Illuminate\Http\Request;

class SelectorGroupController extends Controller
{
    public function index()
    {
        return view('sfp::staff.groups.index', [
            'groups' => SelectorGroup::with(['materialTypes', 'audiences', 'users'])->get(),
        ]);
    }

    public function create()
    {
        return view('sfp::staff.groups.form', [
            'group'         => new SelectorGroup(),
            'materialTypes' => MaterialType::active()->get(),
            'audiences'     => Audience::active()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'               => 'required|string|max:100',
            'description'        => 'nullable|string|max:500',
            'active'             => 'boolean',
            'notification_emails'=> 'nullable|string',
            'material_types'     => 'nullable|array',
            'material_types.*'   => 'exists:material_types,id',
            'audiences'          => 'nullable|array',
            'audiences.*'        => 'exists:audiences,id',
        ]);

        $group = SelectorGroup::create([
            'name'                => $data['name'],
            'description'         => $data['description'] ?? null,
            'active'              => $data['active'] ?? true,
            'notification_emails' => $data['notification_emails'] ?? null,
        ]);

        $group->materialTypes()->sync($data['material_types'] ?? []);
        $group->audiences()->sync($data['audiences'] ?? []);

        return redirect()->route('request.staff.groups.index')->with('success', 'Group created.');
    }

    public function edit(SelectorGroup $group)
    {
        return view('sfp::staff.groups.form', [
            'group'         => $group->load(['materialTypes', 'audiences']),
            'materialTypes' => MaterialType::active()->get(),
            'audiences'     => Audience::active()->get(),
        ]);
    }

    public function update(Request $request, SelectorGroup $group)
    {
        $data = $request->validate([
            'name'               => 'required|string|max:100',
            'description'        => 'nullable|string|max:500',
            'active'             => 'boolean',
            'notification_emails'=> 'nullable|string',
            'material_types'     => 'nullable|array',
            'material_types.*'   => 'exists:material_types,id',
            'audiences'          => 'nullable|array',
            'audiences.*'        => 'exists:audiences,id',
        ]);

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

        $group->materialTypes()->sync($data['material_types'] ?? []);
        $group->audiences()->sync($data['audiences'] ?? []);

        return redirect()->route('request.staff.groups.index')->with('success', 'Group updated.');
    }

    public function destroy(SelectorGroup $group)
    {
        $illGroupId = (int) Setting::get('ill_selector_group_id', 0);
        if ($illGroupId && (int) $group->id === $illGroupId) {
            return back()->withErrors(['error' => 'The ILL access group cannot be deleted.']);
        }

        $group->delete();

        return redirect()->route('request.staff.groups.index')->with('success', 'Group deleted.');
    }
}
