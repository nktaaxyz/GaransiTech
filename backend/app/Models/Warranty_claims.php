<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warranty_claims extends Model
{
    protected $table = 'warranty_claims';

    protected $fillable = [
        'claim_code',
        'warranty_id',
        'customer_id',
        'product_id',
        'vendor_id',
        'serial_number',
        'claim_date',
        'status',
        'forwarded_date',
        'vendor_reference_number',
        'resolution_date',
        'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'claim_date' => 'date',
            'forwarded_date' => 'date',
            'resolution_date' => 'date',
        ];
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ClaimStatusLog::class, 'claim_id');
    }

    public function warranty(): BelongsTo
    {
        return $this->belongsTo(Warranty::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
