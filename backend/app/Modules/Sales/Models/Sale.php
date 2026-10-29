<?php

namespace App\Modules\Sales\Models;

use App\Models\User;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'outlet_id',
        'shift_id',
        'receipt_no',
        'cashier_id',
        'member_id',
        'order_type',
        'table_id',
        'status',
        'subtotal',
        'discount_total',
        'tax_total',
        'service_total',
        'rounding',
        'grand_total',
        'paid_total',
        'change_total',
        'note',
        'sold_at',
        'voided_at',
        'void_reason',
        'voided_by',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'tax_total' => 'integer',
            'service_total' => 'integer',
            'rounding' => 'integer',
            'grand_total' => 'integer',
            'paid_total' => 'integer',
            'change_total' => 'integer',
            'sold_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(SaleDiscount::class, 'sale_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class, 'sale_id');
    }
}