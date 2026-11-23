<?php

namespace BuildWithSmk\TenantPermission\Models;

use BuildWithSmk\TenantPermission\Exceptions\PermissionDoesNotExist;
use BuildWithSmk\TenantPermission\PermissionRegistrar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * An entry in the global permission catalog, e.g. "posts.edit".
 *
 * Name permissions "<module>.<action>". The module is filled in from the name
 * automatically when you don't set one. `status` is informational (shown and
 * filterable in the UI). It does not change permission checks.
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property string|null $display_name
 * @property string|null $module
 * @property string|null $description
 * @property bool $status
 */
class Permission extends Model
{
    protected $fillable = ['name', 'guard_name', 'display_name', 'module', 'description', 'status'];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->attributes['guard_name'] ??= config('permission.default_guard', 'web');
        $this->setTable(config('permission.table_names.permissions', 'permissions'));
    }

    protected static function booted(): void
    {
        static::creating(function (self $permission): void {
            if (blank($permission->module) && str_contains($permission->name, '.')) {
                $permission->module = Str::before($permission->name, '.');
            }
        });

        static::updated(function (self $permission): void {
            if ($permission->wasChanged('name')) {
                app(PermissionRegistrar::class)->forgetAll();
            }
        });

        static::deleted(fn () => app(PermissionRegistrar::class)->forgetAll());
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            app(PermissionRegistrar::class)->roleClass(),
            config('permission.table_names.role_has_permissions', 'role_has_permissions'),
            'permission_id',
            'role_id'
        );
    }

    public static function findByName(string $name, ?string $guardName = null): static
    {
        $guardName ??= config('permission.default_guard', 'web');

        $permission = static::query()->where('name', $name)->where('guard_name', $guardName)->first();

        if (! $permission) {
            throw PermissionDoesNotExist::named($name, $guardName);
        }

        return $permission;
    }

    /**
     * Find or create a permission (handy in seeders). The third argument
     * is a description string, or an array of extra attributes.
     *
     * @param  array<string, mixed>|string|null  $attributes
     */
    public static function findOrCreate(string $name, ?string $guardName = null, array|string|null $attributes = null): static
    {
        $guardName ??= config('permission.default_guard', 'web');
        $attributes = is_string($attributes) ? ['description' => $attributes] : ($attributes ?? []);

        return static::query()->firstOrCreate(['name' => $name, 'guard_name' => $guardName], $attributes);
    }
}
