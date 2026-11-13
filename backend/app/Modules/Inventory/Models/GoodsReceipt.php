<?php

namespace App\Modules\Inventory\Models;

use App\Models\User;
use App\Modules\Core\Models\Outlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceipt extends Model
{
    protected $table = 'goods_receipts';

    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'supplier_id',
        'receipt_no',
        'supplier_invoice_no',
        'status',
        'total_cost',
        'note',
        'received_at',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'total_cost' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class, 'goods_receipt_id');
    }
}