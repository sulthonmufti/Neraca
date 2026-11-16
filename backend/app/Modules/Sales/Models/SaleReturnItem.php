<?php

namespace App\Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnItem extends Model
{
    protected $table = 'sale_return_items';

    protected $fillable = [
        'return_id',
        'sale_item_id',
        'qty',
        'amount',
        'restock',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'amount' => 'integer',
            'restock' => 'boolean',
        ];
    }

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class, 'return_id');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class, 'sale_item_id');
    }
}