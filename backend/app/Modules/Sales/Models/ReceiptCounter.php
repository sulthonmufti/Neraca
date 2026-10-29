<?php

namespace App\Modules\Sales\Models;

use App\Modules\Core\Models\Outlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptCounter extends Model
{
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

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}