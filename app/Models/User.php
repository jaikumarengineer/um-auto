<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravolt\Avatar\Facade as Avatar;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    const MASTER_ROLES = [
        'admin' => 'Master admin',
        'sub_admin' => 'Sub Admin',
        'staff' => 'Staff',
    ];

    const DEPARTMENT_ROLES = [
        'purchase' => 'Purchase department',
        'incoming' => 'Incoming department',
    ];

    const ADMIN_ROLES = [
        'admin' => 'Master admin',
        'sub_admin' => 'Sub Admin',
        'staff' => 'Staff',
        'purchase' => 'Purchase department',
        'incoming' => 'Incoming department',
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

    public static function assignableRoles(): array
    {
        return [
            'admin' => __('Master admin'),
            'purchase' => __('Purchase department'),
            'incoming' => __('Incoming department'),
        ];
    }

    public static function allRoleLabels(): array
    {
        return array_merge(self::MASTER_ROLES, self::DEPARTMENT_ROLES, self::USER_ROLES);
    }

    public function isAdmin(): bool
    {
        return $this->isMaster() || $this->isPurchase() || $this->isIncoming();
    }

    public function isMaster(): bool
    {
        return in_array($this->user_type, array_keys(self::MASTER_ROLES), true);
    }

    public function isPurchase(): bool
    {
        return $this->user_type === 'purchase';
    }

    public function isIncoming(): bool
    {
        return $this->user_type === 'incoming';
    }

    public function isUser(): bool
    {
        return in_array($this->user_type, array_keys(self::USER_ROLES), true);
    }

    public function adminHomeRoute(): string
    {
        return match (true) {
            $this->isPurchase() => 'admin::quality.purchase.orders',
            $this->isIncoming() => 'admin::quality.incoming.vendors',
            default => 'admin::dashboard',
        };
    }

    public function qualityAudience(): string
    {
        return match (true) {
            $this->isPurchase() && ! $this->isMaster() => 'purchase',
            $this->isIncoming() && ! $this->isMaster() => 'incoming',
            default => 'master',
        };
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
