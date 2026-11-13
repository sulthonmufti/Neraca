<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceiptCounter extends Model
{
    protected $table = 'goods_receipt_counters';

    protected $fillable = [
        'outlet_id',
        'counter_date',
        'last_number',
    ];

    protected function casts(): array
    {
        return [
            'counter_date' => 'date',
            'last_number' => 'integer',
        ];
    }
}