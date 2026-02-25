<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestStatusHistory extends Model
{
    protected $table = 'request_status_history';

    protected $fillable = ['request_id', 'request_status_id', 'user_id', 'note'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(SfpRequest::class, 'request_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(RequestStatus::class, 'request_status_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
