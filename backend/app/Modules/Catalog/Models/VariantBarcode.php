<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VariantBarcode extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'variant_id',
        'unit_id',
        'barcode',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}