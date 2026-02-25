<?php

namespace Dcplibrary\Sfp\Http\Controllers\Admin;

use Dcplibrary\Sfp\Http\Controllers\Controller;
use Dcplibrary\Sfp\Models\SelectorGroup;
use Dcplibrary\Sfp\Models\User;
use Illuminate\Http\Request;

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

        $user->selectorGroups()->sync($data['groups'] ?? []);

        return redirect()->route('sfp.staff.users.index')->with('success', 'User updated.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth(config('sfp.guard'))->id()) {
            return back()->withErrors(['error' => 'You cannot delete your own account.']);
        }

        $user->delete();

        return redirect()->route('sfp.staff.users.index')->with('success', 'User removed.');
    }
}
