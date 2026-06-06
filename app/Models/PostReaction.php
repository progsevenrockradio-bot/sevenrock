<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostReaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_key',
        'post_id',
        'reaction_type',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
