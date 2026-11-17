<?php

namespace App\Modules\Member\Models;

use App\Modules\Sales\Models\Sale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use SoftDeletes;

    protected $table = 'members';

    protected $fillable = [
        'tenant_id',
        'member_no',
        'member_type_id',
        'department_id',
        'employee_no',
        'name',
        'phone',
        'email',
        'card_code',
        'status',
        'credit_limit',
        'joined_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'integer',
            'joined_at' => 'datetime',
        ];
    }

    public function memberType(): BelongsTo
    {
        return $this->belongsTo(MemberType::class, 'member_type_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(MemberWallet::class, 'member_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'member_id');
    }
}