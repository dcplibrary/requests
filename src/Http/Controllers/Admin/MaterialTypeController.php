<?php

namespace Dcplibrary\Sfp\Http\Controllers\Admin;

use Dcplibrary\Sfp\Http\Controllers\Controller;
use Dcplibrary\Sfp\Models\MaterialType;
use Illuminate\Http\Request;
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
        if ($materialType->requests()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete a type that has associated requests.']);
        }

        $materialType->delete();

        return redirect()->route('sfp.staff.material-types.index')->with('success', 'Material type deleted.');
    }
}
