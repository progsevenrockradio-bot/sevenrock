<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MissingPerson extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'full_name',
        'cedula',
        'age',
        'sex',
        'place_of_residence',
        'emergency_contact_number',
        'last_seen_location',
        'missing_since',
        'description',
        'photo_path',
        'is_approved',
        'status',
    ];

    protected $casts = [
        'age' => 'integer',
        'missing_since' => 'date',
        'is_approved' => 'boolean',
    ];

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->photo_path)
            : null;
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
