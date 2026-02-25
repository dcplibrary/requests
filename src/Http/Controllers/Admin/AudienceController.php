<?php

namespace Dcplibrary\Sfp\Http\Controllers\Admin;

use Dcplibrary\Sfp\Http\Controllers\Controller;
use Dcplibrary\Sfp\Models\Audience;
use Illuminate\Http\Request;
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
        if ($audience->requests()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete an audience that has associated requests.']);
        }

        $audience->delete();

        return redirect()->route('sfp.staff.audiences.index')->with('success', 'Audience deleted.');
    }
}
