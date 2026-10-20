<?php

namespace Ulams\Core\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Ulams\Core\Support\MorphMap;

/**
 * @property string $current_timezone
 * @property string $first_name
 * @property string $last_name
 * @property string $path_avatar
 * @property string $email
 */
class User extends Authenticatable implements MustVerifyEmail, OAuthenticatable
{
    use Notifiable, HasRoles, HasApiTokens, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'password_reset_token',
    ];

    /**
     * @var array<int, string>
     */
    protected $appends = [
        'avatar_url',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'onboarding_completed' => 'boolean',
    ];

    /**
     * Get the user's full name.
     *
     * @return string
     */
    public function getNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getEmailVerifiedAttribute(): bool
    {
        return $this->hasVerifiedEmail();
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->path_avatar ? Storage::url($this->path_avatar) : null;
    }

    public function guardName(): string
    {
        return 'api';
    }

    /** Every user class is the same table, so all of them are stored as the one `user` alias (see MorphMap). */
    public function getMorphClass()
    {
        return MorphMap::USER;
    }
}
