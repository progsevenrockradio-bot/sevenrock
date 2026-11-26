<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterProgramDeliverySender extends Model
{
    protected $table = 'master_program_delivery_senders';

    protected $fillable = [
        'master_program_id',
        'email',
        'label',
        'is_active',
    ];

    protected $casts = [
        'master_program_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = strtolower(trim((string) $value));
    }

    public function masterProgram(): BelongsTo
    {
        return $this->belongsTo(MasterProgram::class, 'master_program_id');
    }
}
