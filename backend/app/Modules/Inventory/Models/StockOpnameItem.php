<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameItem extends Model
{
    protected $table = 'stock_opname_items';

    protected $fillable = [
        'stock_opname_id',
        'variant_id',
        'system_qty',
        'physical_qty',
        'diff_qty',
        'unit_cost',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'system_qty' => 'decimal:3',
            'physical_qty' => 'decimal:3',
            'diff_qty' => 'decimal:3',
            'unit_cost' => 'integer',
        ];
    }

    public function stockOpname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class, 'stock_opname_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}