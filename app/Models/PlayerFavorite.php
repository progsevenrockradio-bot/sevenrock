<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerFavorite extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_key',
        'signature',
        'title',
        'artist',
        'cover',
    ];
}
