<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property int|null $m_department_id
 * @property string|null $nik
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $jabatan
 * @property string|null $no_whatsapp
 * @property bool $is_active
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class User extends Authenticatable
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'm_department_id',
        'nik',
        'name',
        'email',
        'password',
        'jabatan',
        'no_whatsapp',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];


    protected static function booted(): void
    {
        static::created(function (User $user): void {
            if ($user->isDeveloper() || ! Role::query()->where('nama_role', 'User')->exists()) {
                return;
            }

            $userRole = Role::query()->where('nama_role', 'User')->first();

            if ($userRole !== null) {
                $user->roles()->syncWithoutDetaching([$userRole->id]);
            }
        });
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'm_department_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'user_roles',
            'user_id',
            'role_id',
        );
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'user_id');
    }

    public function preparedDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'official_preparer_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class, 'user_id');
    }

    public function assignedApprovals(): HasMany
    {
        return $this->hasMany(Approval::class, 'assigned_by');
    }

    public function isAdmin(): bool
    {
        if ($this->isDeveloper()) {
            return true;
        }

        return $this->roles()
            ->whereIn('nama_role', ['admin', 'administrator', 'super admin', 'SuperAdmin'])
            ->exists();
    }

    public function isDeveloper(): bool
    {
        return $this->nik === '000000' || $this->email === 'developer@example.com';
    }

    public function isDocumentControlAdmin(): bool
    {
        return $this->roles()
            ->where('nama_role', 'Admin Kontrol Dokumen')
            ->exists();
    }

    public function canAssignDocument(Document $document): bool
    {
        return $this->isDeveloper()
            || $this->isAdmin()
            || $this->hasExplicitPermission('documents.approval.assign');
    }

    public function canUpdateSubmittedDocument(Document $document): bool
    {
        return $this->isDeveloper()
            || $this->isAdmin()
            || $this->hasExplicitPermission('documents.approval.update-submitted');
    }

    public function hasExplicitPermission(string $permissionCode): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('code', $permissionCode))
            ->exists();
    }

    public function hasPermission(string $permissionCode): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('code', $permissionCode))
            ->exists();
    }

    /**
     * @param  array<int, string>  $permissionCodes
     */
    public function hasAnyPermission(array $permissionCodes): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->whereIn('code', $permissionCodes))
            ->exists();
    }

    public function canAccessRoute(?string $route): bool
    {
        if ($route === null || $this->isAdmin()) {
            return true;
        }

        $configuredPermissions = collect(config('access.permissions', []))
            ->filter(fn (array $permission): bool => ($permission['route'] ?? null) === $route);
        $viewPermissionCodes = $configuredPermissions
            ->filter(fn (array $permission): bool => ($permission['action'] ?? 'view') === 'view')
            ->pluck('code');
        $permissionCodes = $viewPermissionCodes->isNotEmpty()
            ? $viewPermissionCodes
            : $configuredPermissions->pluck('code');

        if ($permissionCodes->isNotEmpty()) {
            return $this->roles()
                ->whereHas('permissions', fn ($query) => $query->whereIn('code', $permissionCodes->all()))
                ->exists();
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('route', $route))
            ->exists();
    }

    public function uploadedFiles(): HasMany
    {
        return $this->hasMany(DocumentFile::class, 'uploaded_by');
    }

    public function uploadedDocumentTemplates(): HasMany
    {
        return $this->hasMany(DocumentTemplate::class, 'uploaded_by');
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->name)) ?: [];
        $letters = array_values(array_filter(array_map(
            fn (string $word): string => mb_substr($word, 0, 1),
            $words,
        )));

        if ($letters === []) {
            return '';
        }

        $initials = count($letters) > 1
            ? $letters[0].end($letters)
            : $letters[0];

        return mb_strtoupper($initials);
    }

    public function needsProcessDocumentCount(): int
    {
        return app(\App\Http\Controllers\DocumentManagement\DocumentInboxController::class)->needsProcessCount($this);
    }

    public function setPasswordAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $this->attributes['password'] = Hash::needsRehash($value)
            ? Hash::make($value)
            : $value;
    }
}






