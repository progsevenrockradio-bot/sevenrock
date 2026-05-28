<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutreachCampaign extends Model
{
    protected $fillable = [
        'template_id',
        'program_code',
        'name',
        'description',
        'sent_count',
        'opened_count',
        'responded_count',
        'sent_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'template_id' => 'integer',
            'program_code' => 'string',
            'sent_count' => 'integer',
            'opened_count' => 'integer',
            'responded_count' => 'integer',
            'sent_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(OutreachTemplate::class, 'template_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(MasterProgram::class, 'program_code', 'program_code');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(OutreachLog::class, 'campaign_id');
    }
}
