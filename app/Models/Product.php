<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\PublicMediaUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use Auditable;
    protected $fillable = [
        'title',
        'slug',
        'image',
        'price',
        'regular_price',
        'category',
        'description',
        'is_sale',
        'is_published',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'regular_price' => 'decimal:2',
            'is_sale' => 'bool',
            'is_published' => 'bool',
            'sort_order' => 'integer',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    public function getImageUrlAttribute(): string
    {
        return PublicMediaUrl::normalizePublicUrl($this->image) ?: asset($this->image);
    }

    public function toCatalogArray(): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'image' => $this->image_url,
            'price' => $this->formatted_price,
            'regular_price' => $this->formatted_regular_price,
            'sale' => (bool) $this->is_sale,
            'category' => $this->category,
            'description' => $this->description,
        ];
    }

    public function getFormattedPriceAttribute(): string
    {
        return '£' . number_format((float) $this->price, 2);
    }

    public function getFormattedRegularPriceAttribute(): string
    {
        return $this->regular_price !== null ? '£' . number_format((float) $this->regular_price, 2) : '';
    }
}
