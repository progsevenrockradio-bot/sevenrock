<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\PublicMediaUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use Auditable;
    protected $fillable = [
        'talent_id',
        'title',
        'slug',
        'image',
        'external_payment_url',
        'external_payment_label',
        'is_talent_product',
        'price',
        'regular_price',
        'category',
        'description',
        'stock',
        'is_sale',
        'is_published',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'regular_price' => 'decimal:2',
            'talent_id' => 'integer',
            'stock' => 'integer',
            'is_sale' => 'bool',
            'is_published' => 'bool',
            'is_talent_product' => 'bool',
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

    public function scopeFromTalent(Builder $query, Talent $talent): Builder
    {
        return $query->where('talent_id', $talent->id);
    }

    public function talent(): BelongsTo
    {
        return $this->belongsTo(Talent::class);
    }

    public function isExternalPayment(): bool
    {
        return filled($this->external_payment_url);
    }

    public function getImageUrlAttribute(): string
    {
        if (! filled($this->image)) {
            return asset('assets/lucille/shop-1.jpg');
        }

        if (filter_var($this->image, FILTER_VALIDATE_URL)) {
            return (string) $this->image;
        }

        if ($this->talent_id) {
            try {
                return Storage::disk('backblaze')->url($this->image);
            } catch (\Throwable) {
                // fallback below
            }
        }

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
            'external_payment_url' => $this->external_payment_url,
            'external_payment_label' => $this->external_payment_label,
            'is_talent_product' => (bool) $this->is_talent_product,
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
