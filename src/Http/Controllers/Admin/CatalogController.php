<?php

namespace Dcplibrary\Requests\Http\Controllers\Admin;

use Dcplibrary\Requests\Http\Controllers\Controller;
use Dcplibrary\Requests\Models\CatalogFormatLabel;
use Dcplibrary\Requests\Models\Setting;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index()
    {
        $settings = Setting::allGrouped()
            ->only(['catalog', 'syndetics', 'isbndb']);

        return view('requests::staff.catalog.index', [
            'settings'     => $settings,
            'formatLabels' => CatalogFormatLabel::orderBy('format_code')->get(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'settings'         => 'sometimes|array',
            'settings.*.key'   => 'required|string|exists:settings,key',
            'settings.*.value' => 'nullable|string|max:65535',
            'format_labels'              => 'sometimes|array',
            'format_labels.*.id'         => 'required|integer|exists:catalog_format_labels,id',
            'format_labels.*.label'      => 'required|string|max:100',
        ]);

        foreach ($data['settings'] ?? [] as $item) {
            Setting::set($item['key'], $item['value']);
        }

        foreach ($data['format_labels'] ?? [] as $row) {
            CatalogFormatLabel::where('id', $row['id'])->update(['label' => $row['label']]);
        }

        return back()->with('success', 'Catalog settings saved.');
    }

    public function storeFormatLabel(Request $request)
    {
        $data = $request->validate([
            'format_code' => 'required|string|max:50|unique:catalog_format_labels,format_code',
            'label'       => 'required|string|max:100',
        ]);

        CatalogFormatLabel::create($data);

        return back()->with('success', 'Format label added.');
    }

    public function destroyFormatLabel(CatalogFormatLabel $catalogFormatLabel)
    {
        $catalogFormatLabel->delete();

        return back()->with('success', 'Format label removed.');
    }
}
