<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Core\Models\Tax;
use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'category_id',
        'name',
        'kind',
        'image_path',
        'track_stock',
        'tax_id',
        'description',
        'attributes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'track_stock' => 'boolean',
            'attributes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }
}