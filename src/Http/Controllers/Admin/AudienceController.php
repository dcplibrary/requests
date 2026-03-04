<?php

namespace Dcplibrary\Sfp\Http\Controllers\Admin;

use Dcplibrary\Sfp\Http\Controllers\Controller;
use Dcplibrary\Sfp\Models\Audience;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AudienceController extends Controller
{
    public function index()
    {
        return view('sfp::staff.audiences.index', [
            'audiences' => Audience::orderBy('sort_order')->get(),
        ]);
    }

    public function create()
    {
        return view('sfp::staff.audiences.form', ['audience' => new Audience()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                => 'required|string|max:100',
            'bibliocommons_value' => 'required|string|max:50',
            'sort_order'          => 'required|integer|min:0',
            'active'              => 'boolean',
        ]);

        Audience::create(array_merge($data, ['slug' => Str::slug($data['name'])]));

        return redirect()->route('sfp.staff.audiences.index')->with('success', 'Audience created.');
    }

    public function edit(Audience $audience)
    {
        return view('sfp::staff.audiences.form', ['audience' => $audience]);
    }

    public function update(Request $request, Audience $audience)
    {
        $data = $request->validate([
            'name'                => 'required|string|max:100',
            'bibliocommons_value' => 'required|string|max:50',
            'sort_order'          => 'required|integer|min:0',
            'active'              => 'boolean',
        ]);

        $audience->update($data);

        return redirect()->route('sfp.staff.audiences.index')->with('success', 'Audience updated.');
    }

    public function destroy(Audience $audience)
    {
        $request = request();
        $sfpUser = $this->currentSfpUser($request);
        if (! $sfpUser || ! $sfpUser->isAdmin()) {
            abort(403);
        }

        $requestsCount = $audience->requests()->count();
        $groupsCount   = $audience->selectorGroups()->count();

        if ($requestsCount > 0 || $groupsCount > 0) {
            $data = $request->validate([
                'reassign_to_id' => 'required|integer|exists:audiences,id|different:' . $audience->id,
            ]);

            DB::transaction(function () use ($audience, $data) {
                DB::table('requests')
                    ->where('audience_id', $audience->id)
                    ->update(['audience_id' => (int) $data['reassign_to_id']]);

                $groupIds = DB::table('selector_group_audience')
                    ->where('audience_id', $audience->id)
                    ->pluck('selector_group_id')
                    ->all();

                foreach ($groupIds as $groupId) {
                    DB::table('selector_group_audience')->insertOrIgnore([
                        'selector_group_id' => (int) $groupId,
                        'audience_id'       => (int) $data['reassign_to_id'],
                    ]);
                }

                DB::table('selector_group_audience')
                    ->where('audience_id', $audience->id)
                    ->delete();

                $audience->delete();
            });

            return redirect()->route('sfp.staff.audiences.index')->with('success', 'Audience deleted and records reassigned.');
        }

        return redirect()->route('sfp.staff.audiences.index')->with('success', 'Audience deleted.');
    }

    public function confirmDelete(Audience $audience)
    {
        $request = request();
        $sfpUser = $this->currentSfpUser($request);
        if (! $sfpUser || ! $sfpUser->isAdmin()) {
            abort(403);
        }

        $requestsCount = $audience->requests()->count();
        $groupsCount   = $audience->selectorGroups()->count();

        $requestPreview = $audience->requests()
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'submitted_title'])
            ->map(fn ($r) => [
                'mono'  => "#{$r->id}",
                'label' => (string) ($r->submitted_title ?? 'Request'),
                'href'  => route('sfp.staff.requests.show', $r->id),
            ])->all();

        $groupPreview = $audience->selectorGroups()
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name'])
            ->map(fn ($g) => [
                'mono'  => "#{$g->id}",
                'label' => (string) $g->name,
                'href'  => route('sfp.staff.groups.edit', $g->id),
            ])->all();

        $options = Audience::query()
            ->whereKeyNot($audience->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Audience $a) => ['id' => $a->id, 'label' => $a->name])
            ->values()
            ->all();

        if (empty($options) && ($requestsCount > 0 || $groupsCount > 0)) {
            return back()->withErrors(['error' => 'You must create another audience before deleting this one (records need a reassignment target).']);
        }

        return view('sfp::staff.settings.reassign-delete', [
            'title'       => 'Delete Audience',
            'itemLabel'   => $audience->name,
            'impacts'     => [
                "{$requestsCount} request(s) will be reassigned",
                "{$groupsCount} selector group assignment(s) will be updated",
            ],
            'previews'    => [
                ['title' => 'Requests', 'count' => $requestsCount, 'count_label' => 'total', 'items' => $requestPreview],
                ['title' => 'Selector groups', 'count' => $groupsCount, 'count_label' => 'total', 'items' => $groupPreview],
            ],
            'options'     => $options,
            'deleteAction'=> route('sfp.staff.audiences.destroy', $audience),
            'cancelHref'  => route('sfp.staff.audiences.index'),
            'extraFields' => [],
        ]);
    }
}
