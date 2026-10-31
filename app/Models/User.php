<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_admin', 'is_conductor'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_conductor' => 'boolean',
        ];
    }

    public function hasAdminAccess(): bool
    {
        return (bool) ($this->getAttribute('is_admin') ?? false)
            || (string) $this->getAttribute('role') === 'admin';
    }

    public function isAdmin(): bool
    {
        return $this->hasAdminAccess();
    }

    public function masterPrograms(): BelongsToMany
    {
        return $this->belongsToMany(MasterProgram::class, 'master_program_conductor', 'user_id', 'master_program_id')
            ->withPivot(['is_active', 'authorized_by', 'authorized_at'])
            ->withTimestamps();
    }

    public function authorizedMasterPrograms(): BelongsToMany
    {
        return $this->masterPrograms()->wherePivot('is_active', true);
    }

    public function submittedRadioPrograms(): HasMany
    {
        return $this->hasMany(RadioProgram::class, 'submitted_by_user_id');
    }

    public function hasConductorAccess(): bool
    {
        if ((bool) ($this->getAttribute('is_conductor') ?? false) || (string) $this->getAttribute('role') === 'conductor') {
            return true;
        }

        if (Schema::hasTable('master_program_conductor')) {
            return $this->authorizedMasterPrograms()->exists();
        }

        return false;
    }

    public function isAuthorizedForMasterProgram(MasterProgram|int $masterProgram): bool
    {
        $programId = $masterProgram instanceof MasterProgram ? (int) $masterProgram->id : (int) $masterProgram;
        if ($programId <= 0 || ! Schema::hasTable('master_program_conductor')) {
            return false;
        }

        return $this->authorizedMasterPrograms()
            ->where('master_programs.id', $programId)
            ->exists();
    }

    public function canManageConductorEpisode(RadioProgram $episode): bool
    {
        if (! $this->isAuthorizedForMasterProgram((int) $episode->master_program_id)) {
            return false;
        }

        return (int) ($episode->submitted_by_user_id ?? 0) === (int) $this->id;
    }
}
