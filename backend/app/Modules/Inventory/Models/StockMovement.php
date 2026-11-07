<?php

namespace App\Modules\Inventory\Models;

use App\Models\User;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $table = 'stock_movements';
    
    // Ledger bersifat append-only, created_at dikelola manual tanpa updated_at
    const UPDATED_AT = null;

    protected $fillable = [
        'outlet_id',
        'variant_id',
        'type',
        'qty_change',
        'qty_after',
        'unit_cost',
        'ref_type',
        'ref_id',
        'batch_id',
        'note',
        'user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'qty_change' => 'decimal:3',
            'qty_after' => 'decimal:3',
            'unit_cost' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}