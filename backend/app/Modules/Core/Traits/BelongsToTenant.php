<?php

namespace App\Modules\Core\Traits;

use App\Models\User;
use App\Modules\Core\Models\Tenant;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * @mixin Model
 * @method static void creating(Closure|string $callback)
 * @method static void addGlobalScope(Scope|Closure|string $scope, Closure|Scope|null $implementation = null)
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::creating(function (Model $model) {
            /** @var User|null $user */
            $user = Auth::user();

            if (! $model->getAttribute('tenant_id') && $user && $user->tenant_id) {
                $model->setAttribute('tenant_id', $user->tenant_id);
            }
        });

        static::addGlobalScope('tenant', function (Builder $builder) {
            /** @var User|null $user */
            $user = Auth::user();

            if ($user && $user->tenant_id) {
                $builder->where($builder->qualifyColumn('tenant_id'), $user->tenant_id);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}