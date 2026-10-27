<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaidServiceRequest extends Model
{
    protected $fillable = [
        'product_unit_id',
        'vendor_id',
        'issue_description',
        'quoted_amount',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quoted_amount' => 'decimal:2',
        ];
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
