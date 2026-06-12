<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\PublicMediaUrl;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class Agency extends Authenticatable
{
    use Notifiable;
    use Auditable;

    protected $table = 'agencies';

    protected $fillable = [
        'name',
        'slug',
        'email',
        'password',
        'logo_path',
        'website_url',
        'is_active',
        'sort_order',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'password' => 'hashed',
        ];
    }

    public static function boot(): void
    {
        parent::boot();

        static::creating(function (Agency $agency) {
            if (empty($agency->slug)) {
                $agency->slug = Str::slug($agency->name);
            }
        });
    }

    public function radioArtists(): HasMany
    {
        return $this->hasMany(RadioArtist::class, 'agency_id');
    }

    public function getLogoUrlAttribute(): ?string
    {
        if ($this->logo_path) {
            return PublicMediaUrl::normalizePublicUrl($this->logo_path);
        }

        return null;
    }
}
