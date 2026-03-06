<?php

namespace Dcplibrary\Sfp\Http\Controllers\Admin;

use Dcplibrary\Sfp\Http\Controllers\Controller;
use Dcplibrary\Sfp\Models\FormField;

class FormFieldController extends Controller
{
    public function index()
    {
        return view('sfp::staff.form-fields.index');
    }

    public function edit(FormField $field)
    {
        return view('sfp::staff.form-fields.edit', compact('field'));
    }
}
