<?php

namespace Dcplibrary\Sfp\Http\Controllers\Admin;

use Dcplibrary\Sfp\Http\Controllers\Controller;
use Dcplibrary\Sfp\Models\SelectorGroup;
use Dcplibrary\Sfp\Models\User;
use Dcplibrary\Sfp\Models\RequestStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index()
    {
        return view('sfp::staff.users.index', [
            'users' => User::orderBy('name')->get(),
        ]);
    }

    public function edit(User $user)
    {
        return view('sfp::staff.users.form', [
            'user'   => $user,
            'groups' => SelectorGroup::active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'role'   => 'required|in:admin,selector',
            'active' => 'boolean',
            'groups' => 'nullable|array',
            'groups.*' => 'exists:selector_groups,id',
        ]);

        $user->update([
            'role'   => $data['role'],
            'active' => $data['active'] ?? false,
        ]);

        // Only selectors use selector groups (including the ILL group). Ensure admins don't retain group assignments.
        $user->selectorGroups()->sync($data['role'] === 'selector' ? ($data['groups'] ?? []) : []);

        return redirect()->route('request.staff.users.index')->with('success', 'User updated.');
    }

    public function destroy(User $user)
    {
        $request = request();
        $sfpUser = $this->currentSfpUser($request);
        if (! $sfpUser || ! $sfpUser->isAdmin()) {
            abort(403);
        }

        // If staff auth uses the package guard, the IDs will match. If the host
        // app authenticates with a different user model, map to the SFP user by
        // email so we can still prevent self-deletion.
        if ($sfpUser && $user->id === $sfpUser->id) {
            return back()->withErrors(['error' => 'You cannot delete your own account.']);
        }

        $historyCount = $user->statusHistories()->count();
        $groupsCount  = $user->selectorGroups()->count();

        if ($historyCount > 0 || $groupsCount > 0) {
            $data = $request->validate([
                'reassign_to_id' => 'required|integer|exists:sfp_users,id|different:' . $user->id,
                'transfer_history' => 'nullable|boolean',
                'transfer_groups'  => 'nullable|boolean',
            ]);

            $targetId = (int) $data['reassign_to_id'];
            $transferHistory = (bool) ($data['transfer_history'] ?? true);
            $transferGroups  = (bool) ($data['transfer_groups'] ?? true);

            DB::transaction(function () use ($user, $targetId, $transferHistory, $transferGroups) {
                if ($transferHistory) {
                    DB::table('request_status_history')
                        ->where('user_id', $user->id)
                        ->update(['user_id' => $targetId]);
                }

                if ($transferGroups) {
                    $groupIds = DB::table('selector_group_user')
                        ->where('user_id', $user->id)
                        ->pluck('selector_group_id')
                        ->all();

                    foreach ($groupIds as $groupId) {
                        DB::table('selector_group_user')->insertOrIgnore([
                            'selector_group_id' => (int) $groupId,
                            'user_id'           => $targetId,
                        ]);
                    }

                    DB::table('selector_group_user')
                        ->where('user_id', $user->id)
                        ->delete();
                }

                $user->delete();
            });

            return redirect()->route('request.staff.users.index')->with('success', 'User removed and records reassigned.');
        }

        $user->delete();

        return redirect()->route('request.staff.users.index')->with('success', 'User removed.');
    }

    public function confirmDelete(User $user)
    {
        $request = request();
        $sfpUser = $this->currentSfpUser($request);
        if (! $sfpUser || ! $sfpUser->isAdmin()) {
            abort(403);
        }

        if ($user->id === $sfpUser->id) {
            return back()->withErrors(['error' => 'You cannot delete your own account.']);
        }

        $historyCount = $user->statusHistories()->count();
        $groupsCount  = $user->selectorGroups()->count();

        $historyPreview = RequestStatusHistory::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'request_id'])
            ->map(fn (RequestStatusHistory $h) => [
                'mono'  => "#{$h->id}",
                'label' => "Request #{$h->request_id}",
                'href'  => route('request.staff.requests.show', $h->request_id),
            ])->all();

        $groupPreview = $user->selectorGroups()
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name'])
            ->map(fn ($g) => [
                'mono'  => "#{$g->id}",
                'label' => (string) $g->name,
                'href'  => route('request.staff.groups.edit', $g->id),
            ])->all();

        $options = User::query()
            ->whereKeyNot($user->id)
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => ['id' => $u->id, 'label' => "{$u->name} ({$u->email})"])
            ->values()
            ->all();

        if (empty($options) && ($historyCount > 0 || $groupsCount > 0)) {
            return back()->withErrors(['error' => 'You must create another user before removing this one (records need a reassignment target).']);
        }

        return view('sfp::staff.settings.reassign-delete', [
            'title'       => 'Remove User',
            'itemLabel'   => "{$user->name} ({$user->email})",
            'impacts'     => [
                "{$historyCount} status history entr(y/ies) may be reassigned",
                "{$groupsCount} selector group membership(s) may be transferred",
            ],
            'previews'    => [
                ['title' => 'Status history entries', 'count' => $historyCount, 'count_label' => 'total', 'items' => $historyPreview],
                ['title' => 'Selector groups', 'count' => $groupsCount, 'count_label' => 'total', 'items' => $groupPreview],
            ],
            'options'     => $options,
            'deleteAction'=> route('request.staff.users.destroy', $user),
            'cancelHref'  => route('request.staff.users.index'),
            'extraFields' => [
                ['name' => 'transfer_history', 'label' => 'Transfer status history entries to the selected user', 'default' => true],
                ['name' => 'transfer_groups',  'label' => 'Transfer selector group memberships to the selected user', 'default' => true],
            ],
        ]);
    }
}
