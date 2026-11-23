<?php

namespace Smk\Permission;

use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;
use Smk\Permission\Contracts\TenantResolver;
use Smk\Permission\Models\Permission;
use Smk\Permission\Models\Role;
use Smk\Permission\Tenancy\DefaultTenantResolver;

/**
 * Central service of the package: tenant scope resolution, configured model
 * classes, and the effective-permission cache.
 *
 * Bound as a scoped singleton, so the "current tenant" and the in-memory
 * memo reset between requests and queue jobs, including under Octane.
 */
class PermissionRegistrar
{
    protected ?int $currentTenantId = null;

    /** @var array<string, array<int, string>> */
    protected array $memo = [];

    /** @var array<string, int> */
    protected array $versions = [];

    public function __construct(protected CacheRepository $cache) {}

    // -----------------------------------------------------------------
    // Tenancy
    // -----------------------------------------------------------------

    public function tenancyEnabled(): bool
    {
        return (bool) config('permission.tenancy.enabled', false);
    }

    public function globalTenantId(): int
    {
        return (int) config('permission.global_tenant_id', 0);
    }

    /**
     * Pin the tenant scope for the rest of the request, e.g. from your
     * "identify tenant by subdomain" middleware. Pass null to unpin.
     */
    public function setCurrentTenantId(?int $tenantId): static
    {
        $this->currentTenantId = $tenantId;

        return $this;
    }

    public function getCurrentTenantId(): ?int
    {
        return $this->currentTenantId;
    }

    /**
     * The scope used when no model is involved (seeders, role lookups):
     * the pinned tenant if any, otherwise the global scope.
     */
    public function currentScopeId(): int
    {
        if (! $this->tenancyEnabled()) {
            return $this->globalTenantId();
        }

        return $this->currentTenantId ?? $this->globalTenantId();
    }

    /**
     * The scope a model's checks run in. With tenancy disabled this is
     * always the global scope.
     */
    public function tenantIdFor(?Model $model = null): int
    {
        if (! $this->tenancyEnabled()) {
            return $this->globalTenantId();
        }

        if ($this->currentTenantId !== null) {
            return $this->currentTenantId;
        }

        if ($model === null) {
            return $this->globalTenantId();
        }

        return $this->resolver()->resolve($model) ?? $this->globalTenantId();
    }

    public function resolver(): TenantResolver
    {
        return app(config('permission.tenancy.resolver') ?? DefaultTenantResolver::class);
    }

    public function superAdminRoleName(): string
    {
        return (string) config('permission.super_admin_role', 'Super Admin');
    }

    public function defaultGuard(): string
    {
        return (string) config('permission.default_guard', 'web');
    }

    // -----------------------------------------------------------------
    // Configured model classes
    // -----------------------------------------------------------------

    /**
     * @return class-string<Role>
     */
    public function roleClass(): string
    {
        return config('permission.models.role') ?? Role::class;
    }

    /**
     * @return class-string<Permission>
     */
    public function permissionClass(): string
    {
        return config('permission.models.permission') ?? Permission::class;
    }

    /**
     * @return class-string<Model>
     */
    public function userClass(): string
    {
        return config('permission.models.user') ?? config('auth.providers.users.model');
    }

    // -----------------------------------------------------------------
    // Cache
    // -----------------------------------------------------------------

    protected function cacheEnabled(): bool
    {
        return (bool) config('permission.cache.enabled', true);
    }

    protected function prefix(): string
    {
        return (string) config('permission.cache.key_prefix', 'smk_permission');
    }

    protected function modelToken(Model $model): string
    {
        return $model->getMorphClass().':'.$model->getKey();
    }

    /**
     * Get (and cache) the effective permission names of a model in a tenant
     * scope. $resolver computes the list on a miss.
     *
     * @param  Closure(): array<int, string>  $resolver
     * @return array<int, string>
     */
    public function rememberEffectivePermissions(Model $model, int $tenantId, Closure $resolver): array
    {
        $key = sprintf(
            '%s:v%d:%s:v%d:t%d',
            $this->prefix(),
            $this->version('global'),
            $this->modelToken($model),
            $this->version('model:'.$this->modelToken($model)),
            $tenantId
        );

        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        $permissions = $this->cacheEnabled()
            ? $this->cache->remember($key, (int) config('permission.cache.ttl_seconds', 3600), $resolver)
            : $resolver();

        return $this->memo[$key] = $permissions;
    }

    /**
     * Drop the cached permissions of one model in every tenant scope.
     * The second argument is accepted for backward compatibility and ignored.
     */
    public function forgetFor(Model $model, ?int $tenantId = null): void
    {
        $this->bump('model:'.$this->modelToken($model));
    }

    /**
     * Drop every cached permission list (after role or catalog changes).
     * Only this package's keys are affected; your app cache is untouched.
     */
    public function forgetAll(): void
    {
        $this->bump('global');
    }

    protected function version(string $name): int
    {
        if (! $this->cacheEnabled()) {
            return 1;
        }

        $key = $this->prefix().':version:'.$name;

        return $this->versions[$key] ??= (int) $this->cache->get($key, 1);
    }

    protected function bump(string $name): void
    {
        $this->memo = [];
        $this->versions = [];

        if (! $this->cacheEnabled()) {
            return;
        }

        $key = $this->prefix().':version:'.$name;
        $this->cache->forever($key, (int) $this->cache->get($key, 1) + 1);
    }
}
