<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ReleaseIntakeSender extends Model
{
    public const ENTITY_TYPES = [
        'artist' => 'Artista / Banda',
        'label' => 'Sello discográfico',
        'agency' => 'Agencia / Prensa',
        'other' => 'Otro',
    ];

    protected $table = 'release_intake_senders';

    protected $fillable = [
        'email',
        'entity_name',
        'entity_type',
        'is_active',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_by' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = strtolower(trim($value));
    }

    public function entityTypeLabel(): string
    {
        return self::ENTITY_TYPES[$this->entity_type] ?? 'Otro';
    }
}

