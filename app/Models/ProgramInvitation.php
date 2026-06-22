<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramInvitation extends Model
{
    protected $fillable = [
        'master_program_id',
        'requested_fields',
        'expires_at',
        'completed_at',
    ];

    protected $casts = [
        'requested_fields' => 'array',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function masterProgram()
    {
        return $this->belongsTo(MasterProgram::class);
    }
}
