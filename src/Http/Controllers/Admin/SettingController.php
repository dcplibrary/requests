<?php

namespace Dcplibrary\Sfp\Http\Controllers\Admin;

use Dcplibrary\Sfp\Http\Controllers\Controller;
use Dcplibrary\Sfp\Models\FormField;
use Dcplibrary\Sfp\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        return view('sfp::staff.settings.index', [
            // Catalog/ISBNdb/Syndetics settings live on the dedicated Catalog tab.
            // Notification settings live on the dedicated Notifications tab.
            'settings' => Setting::allGrouped()->except(['catalog', 'isbndb', 'syndetics', 'notifications', 'backup']),
        ]);
    }

    public function notifications()
    {
        // Core tokens always available in notification templates.
        $coreTokens = [
            '{title}', '{author}',
            '{patron_name}', '{patron_first_name}', '{patron_email}', '{patron_phone}',
            '{material_type}', '{audience}', '{status}',
            '{submitted_date}', '{request_url}',
        ];

        // Dynamic tokens from form fields flagged as token sources.
        $fieldTokens = FormField::tokenFields()
            ->pluck('key')
            ->map(fn (string $k) => "{{$k}}")
            ->all();

        return view('sfp::staff.settings.notifications', [
            'settings'        => Setting::allGrouped()->only(['notifications']),
            'availableTokens' => array_merge($coreTokens, $fieldTokens),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'settings'         => 'required|array',
            'settings.*.key'   => 'required|string|exists:settings,key',
            'settings.*.value' => 'nullable|string|max:65535',
        ]);

        foreach ($data['settings'] as $item) {
            Setting::set($item['key'], $item['value']);
        }

        return back()->with('success', 'Settings saved.');
    }
}
