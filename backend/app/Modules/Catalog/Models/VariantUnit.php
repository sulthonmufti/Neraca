<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VariantUnit extends Model
{
    protected $fillable = [
        'variant_id',
        'unit_id',
        'factor',
        'sell_price',
    ];

    protected function casts(): array
    {
        return [
            'factor' => 'decimal:3',
            'sell_price' => 'integer',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}