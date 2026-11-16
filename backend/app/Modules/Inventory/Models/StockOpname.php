<?php

namespace App\Modules\Inventory\Models;

use App\Models\User;
use App\Modules\Core\Models\Outlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockOpname extends Model
{
    protected $table = 'stock_opnames';

    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'opname_no',
        'status',
        'note',
        'opname_at',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'opname_at' => 'datetime',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class, 'stock_opname_id');
    }
}