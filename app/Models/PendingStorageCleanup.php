<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PendingStorageCleanup extends Model
{
    use HasFactory;

    protected $table = 'pending_storage_cleanups';

    protected $fillable = [
        'disk',
        'storage_key',
        'operation_id',
        'reason',
        'status',
        'attempts',
        'last_error',
        'last_attempt_at',
        'completed_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'last_attempt_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}

