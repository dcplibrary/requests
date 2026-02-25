<?php

namespace Dcplibrary\Sfp\Http\Controllers\Admin;

use Dcplibrary\Sfp\Http\Controllers\Controller;
use Dcplibrary\Sfp\Models\RequestStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RequestStatusController extends Controller
{
    public function index()
    {
        return view('sfp::staff.statuses.index', [
            'statuses' => RequestStatus::orderBy('sort_order')->get(),
        ]);
    }

    public function create()
    {
        return view('sfp::staff.statuses.form', ['status' => new RequestStatus()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'color'       => 'required|string|max:20',
            'sort_order'  => 'required|integer|min:0',
            'active'      => 'boolean',
            'is_terminal' => 'boolean',
        ]);

        RequestStatus::create(array_merge($data, ['slug' => Str::slug($data['name'])]));

        return redirect()->route('sfp.staff.statuses.index')->with('success', 'Status created.');
    }

    public function edit(RequestStatus $status)
    {
        return view('sfp::staff.statuses.form', ['status' => $status]);
    }

    public function update(Request $request, RequestStatus $status)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'color'       => 'required|string|max:20',
            'sort_order'  => 'required|integer|min:0',
            'active'      => 'boolean',
            'is_terminal' => 'boolean',
        ]);

        $status->update($data);

        return redirect()->route('sfp.staff.statuses.index')->with('success', 'Status updated.');
    }

    public function destroy(RequestStatus $status)
    {
        if ($status->requests()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete a status that has associated requests.']);
        }

        $status->delete();

        return redirect()->route('sfp.staff.statuses.index')->with('success', 'Status deleted.');
    }
}
