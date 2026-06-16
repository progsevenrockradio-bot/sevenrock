<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'track_submission_id',
        'to_email',
        'subject',
        'body',
        'status',
    ];

    public function trackSubmission()
    {
        return $this->belongsTo(TrackSubmission::class);
    }
}
