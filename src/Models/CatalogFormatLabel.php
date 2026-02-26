<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Human-readable label for a BiblioCommons format code.
 *
 * BiblioCommons returns abbreviated format codes in search results (e.g. `BK`,
 * `EAUDIOBOOK`, `LPRINT`). This table maps those codes to display labels shown
 * to patrons on the catalog results step of the SFP form. Labels are editable
 * by admins via Settings → Catalog.
 *
 * @property int    $id
 * @property string $format_code  e.g. 'BK', 'EBOOK', 'EAUDIOBOOK'
 * @property string $label        e.g. 'Book', 'eBook', 'eAudiobook'
 */
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
