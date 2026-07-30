<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppDownload extends Model
{
    protected $fillable = [
        'ip_address',
        'user_agent',
        'referer',
    ];
}
