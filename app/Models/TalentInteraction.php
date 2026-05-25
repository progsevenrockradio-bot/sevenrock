<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TalentInteraction extends Model
{
    protected $fillable = [
        'talent_id',
        'visitor_ip',
        'type',
        'content',
    ];

    public function talent(): BelongsTo
    {
        return $this->belongsTo(Talent::class);
    }
}
