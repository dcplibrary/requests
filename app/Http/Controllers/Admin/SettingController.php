<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingController extends Controller
{
    public function index()
    {
        return view('staff.settings.index', [
            'settings' => Setting::allGrouped(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'settings'         => 'required|array',
            'settings.*.key'   => 'required|string|exists:settings,key',
            'settings.*.value' => 'nullable|string|max:5000',
        ]);

        foreach ($data['settings'] as $item) {
            Setting::set($item['key'], $item['value']);
        }

        return back()->with('success', 'Settings saved.');
    }
}
