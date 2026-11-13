<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    protected $table = 'goods_receipt_items';

    protected $fillable = [
        'goods_receipt_id',
        'variant_id',
        'unit_id',
        'qty',
        'conversion_factor',
        'base_qty',
        'unit_cost',
        'base_unit_cost',
        'line_total',
        'batch_no',
        'expired_date',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'conversion_factor' => 'decimal:3',
            'base_qty' => 'decimal:3',
            'unit_cost' => 'integer',
            'base_unit_cost' => 'integer',
            'line_total' => 'integer',
            'expired_date' => 'date',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}