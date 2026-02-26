<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogFormatLabel extends Model
{
    protected $fillable = ['format_code', 'label'];

    /**
     * Return a keyed array of format_code => label for use in views.
     */
    public static function map(): array
    {
        return static::pluck('label', 'format_code')->all();
    }
}
