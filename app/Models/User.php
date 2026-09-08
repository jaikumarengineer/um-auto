<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravolt\Avatar\Facade as Avatar;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    const ADMIN_ROLES = [
        'admin' => 'Admin',
        'sub_admin' => 'Sub Admin',
        'staff' => 'Staff',
    ];

    const USER_ROLES = [
        'user' => 'User',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'salutation',
        'name',
        'last_name',
        'email',
        'phone',
        'password',
        'image',
        'country',
        'city',
        'state',
        'zip_code',
        'address_line_1',
        'address_line_2',
        'date_of_birth',
        'gender',
        'timezone',
        'locale',
        'ui_preferences',
        'is_active',
        'last_login_at',
        'user_type',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
            'ui_preferences' => 'array',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function uiPreference(string $key, mixed $default = null): mixed
    {
        return data_get($this->ui_preferences ?? [], $key, $default);
    }

    public function setUiPreferences(array $preferences): void
    {
        $this->forceFill([
            'ui_preferences' => array_merge($this->ui_preferences ?? [], $preferences),
        ])->save();
    }

    protected $appends = [
        'full_name',
        'initials',
        'avatar_url',
    ];

    public function getFullNameAttribute(): string
    {
        return trim("{$this->name} {$this->last_name}");
    }

    public function getAvatarUrlAttribute()
    {
        if ($this->image) {
            return asset($this->image);
        }

        return Avatar::create($this->full_name)->toBase64();
    }

    public function isAdmin(): bool
    {
        return in_array($this->user_type, array_keys(self::ADMIN_ROLES));
    }

    public function isUser(): bool
    {
        return in_array($this->user_type, array_keys(self::USER_ROLES));
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

}
