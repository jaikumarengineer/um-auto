<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\PermissionCatalog;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravolt\Avatar\Facade as Avatar;
use Modules\Quality\Support\QualityAlertCatalog;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    const MASTER_ROLES = [
        'admin' => 'Master admin',
        'manager' => 'Manager',
        'sub_admin' => 'Sub Admin',
        'staff' => 'Staff',
    ];

    const DEPARTMENT_ROLES = [
        'purchase' => 'Purchase department',
        'incoming' => 'Incoming department',
    ];

    const ADMIN_ROLES = [
        'admin' => 'Master admin',
        'manager' => 'Manager',
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
        'email_alert_preferences',
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
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ui_preferences' => 'array',
            'email_alert_preferences' => 'array',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (User $user) {
            if (! $user->wasRecentlyCreated && ! $user->wasChanged('user_type')) {
                return;
            }

            $user->syncRoleDefaults(forcePermissions: true);
        });
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

    /**
     * @return array{enabled: bool, departments: list<string>, statuses: list<string>, reports: list<string>, summaries: list<string>}
     */
    public function emailAlertPreferences(): array
    {
        return QualityAlertCatalog::normalize($this->email_alert_preferences);
    }

    public function receivesQualityAlert(string $alertType): bool
    {
        if (! $this->is_active || ! filled($this->email)) {
            return false;
        }

        return QualityAlertCatalog::matches($alertType, $this->email_alert_preferences);
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
        if ($url = media_url($this->image)) {
            return $url;
        }

        return Avatar::create($this->full_name)->toBase64();
    }

    public static function assignableRoles(): array
    {
        return [
            'admin' => __('Master admin'),
            'manager' => __('Manager'),
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
        return $this->isUnrestrictedAdmin()
            || $this->isManagerLike()
            || $this->isPurchase()
            || $this->isIncoming();
    }

    /**
     * True admin only — settings/config and unrestricted access.
     */
    public function isUnrestrictedAdmin(): bool
    {
        return $this->user_type === 'admin';
    }

    public function isManager(): bool
    {
        return $this->user_type === 'manager';
    }

    /**
     * Manager plus legacy master types that map to manager scopes.
     */
    public function isManagerLike(): bool
    {
        return in_array($this->user_type, ['manager', 'sub_admin', 'staff'], true);
    }

    /**
     * Broad operational access (admin or manager-like). Prefer canModule() for screens.
     */
    public function isMaster(): bool
    {
        return $this->isUnrestrictedAdmin() || $this->isManagerLike();
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

    public function canModule(string $module, string $action = 'access'): bool
    {
        if ($this->isUnrestrictedAdmin()) {
            return true;
        }

        return $this->can(PermissionCatalog::name($module, $action));
    }

    public function canAnyModule(array $modules, string $action = 'access'): bool
    {
        if ($this->isUnrestrictedAdmin()) {
            return true;
        }

        foreach ($modules as $module) {
            if ($this->canModule($module, $action)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sync Spatie role (and optionally default direct permissions) from user_type.
     */
    public function syncRoleDefaults(bool $forcePermissions = false): void
    {
        $role = PermissionCatalog::roleForUserType((string) $this->user_type);

        if ($role === null) {
            try {
                $this->syncRoles([]);
                $this->syncPermissions([]);
            } catch (\Throwable) {
                // Permission tables may not exist yet during early migrations.
            }

            return;
        }

        if (! class_exists(\Spatie\Permission\Models\Role::class)
            || ! class_exists(\Spatie\Permission\Models\Permission::class)) {
            return;
        }

        try {
            \Spatie\Permission\Models\Role::findOrCreate($role, 'web');

            if ($forcePermissions && $role !== 'admin') {
                foreach (PermissionCatalog::defaultsFor($role) as $permission) {
                    \Spatie\Permission\Models\Permission::findOrCreate($permission, 'web');
                }
            }

            $this->syncRoles([$role]);

            if ($role === 'admin') {
                $this->syncPermissions([]);

                return;
            }

            if ($forcePermissions) {
                $this->syncPermissions(PermissionCatalog::defaultsFor($role));
            }
        } catch (\Throwable) {
            // Ignore when permission tables are unavailable.
        }
    }

    public function adminHomeRoute(): string
    {
        return match (true) {
            $this->isPurchase() => 'admin::quality.purchase.orders',
            $this->isIncoming() => 'admin::quality.incoming.parts',
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
