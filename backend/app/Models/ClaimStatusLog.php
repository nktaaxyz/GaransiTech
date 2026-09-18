<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimStatusLog extends Model
{
    protected $table = 'claim_status_logs';

    protected $fillable = [
        'claim_id',
        'old_status',
        'new_status',
        'note',
        'changed_by',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Warranty_claims::class, 'claim_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
