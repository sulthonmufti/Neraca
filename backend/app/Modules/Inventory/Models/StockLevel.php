<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLevel extends Model
{
    protected $table = 'stock_levels';
    
    // Karena primary key komposit, nonaktifkan incrementing & set key type
    public $incrementing = false;
    protected $primaryKey = ['outlet_id', 'variant_id'];
    protected $keyType = 'array';

    protected $fillable = [
        'outlet_id',
        'variant_id',
        'qty',
        'reorder_point',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'reorder_point' => 'decimal:3',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}