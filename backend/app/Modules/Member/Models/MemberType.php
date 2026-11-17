<?php

namespace App\Modules\Member\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberType extends Model
{
    protected $table = 'member_types';

    protected $fillable = [
        'tenant_id',
        'name',
        'price_list_id',
        'discount_percent',
        'default_credit_limit',
        'points_rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'decimal:2',
            'default_credit_limit' => 'integer',
            'points_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class, 'member_type_id');
    }
}