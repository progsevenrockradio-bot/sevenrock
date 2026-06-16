<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageCountdown extends Model
{
    protected $fillable = [
        'route_path',
        'title',
        'description',
        'active_at',
        'is_enabled',
    ];

    protected $casts = [
        'active_at' => 'datetime',
        'is_enabled' => 'boolean',
    ];
}
