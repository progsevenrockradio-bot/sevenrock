<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use Auditable;
    protected $fillable = [
        'title',
        'slug',
        'starts_at',
        'ends_at',
        'location',
        'venue',
        'ticket_url',
        'ticket_label',
        'categories',
        'poster',
        'venue_url',
        'facebook_url',
        'embed_url',
        'map_url',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'categories' => 'array',
            'content' => 'array',
        ];
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>=', now()->startOfDay());
    }
}
