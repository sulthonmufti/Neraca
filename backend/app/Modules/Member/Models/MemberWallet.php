<?php

namespace App\Modules\Member\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberWallet extends Model
{
    protected $table = 'member_wallets';

    protected $primaryKey = 'member_id';

    public $incrementing = false;

    protected $fillable = [
        'member_id',
        'balance',
        'credit_used',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'credit_used' => 'integer',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }
}