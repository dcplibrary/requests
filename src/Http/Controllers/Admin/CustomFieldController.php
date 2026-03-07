<?php

namespace Dcplibrary\Sfp\Http\Controllers\Admin;

use Dcplibrary\Sfp\Http\Controllers\Controller;
use Dcplibrary\Sfp\Models\CustomField;

class CustomFieldController extends Controller
{
    public function index()
    {
        return view('sfp::staff.custom-fields.index');
    }

    public function edit(CustomField $field)
    {
        return view('sfp::staff.custom-fields.edit', compact('field'));
    }
}

