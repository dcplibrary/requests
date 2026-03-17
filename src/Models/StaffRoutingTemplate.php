<?php

namespace Dcplibrary\Requests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Staff routing email for new requests, scoped to one selector group.
 *
 * When a new request matches a group, that group's template (if enabled) is used;
 * otherwise the global default from notification settings applies.
 *
 * @property int         $id
 * @property int         $selector_group_id
 * @property string      $name
 * @property bool        $enabled
 * @property string      $subject
 * @property string|null $body
 */
class StaffRoutingTemplate extends Model
{
    protected $fillable = ['selector_group_id', 'name', 'enabled', 'subject', 'body'];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function selectorGroup(): BelongsTo
    {
        return $this->belongsTo(SelectorGroup::class, 'selector_group_id');
    }
}
