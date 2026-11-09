<?php

namespace App\Modules\Sales\Models;

use App\Models\User;
use App\Modules\Core\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    protected $table = 'sale_returns';

    protected $fillable = [
        'sale_id',
        'return_no',
        'reason',
        'refund_method_id',
        'refund_total',
        'approved_by',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'refund_total' => 'integer',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class, 'return_id');
    }

    public function refundMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'refund_method_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}