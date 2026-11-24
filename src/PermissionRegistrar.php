<?php

namespace BuildWithSmk\TenantPermission;

use BuildWithSmk\TenantPermission\Branches\DefaultBranchResolver;
use BuildWithSmk\TenantPermission\Contracts\BranchResolver;
use BuildWithSmk\TenantPermission\Contracts\TenantResolver;
use BuildWithSmk\TenantPermission\Models\Permission;
use BuildWithSmk\TenantPermission\Models\Role;
use BuildWithSmk\TenantPermission\Tenancy\DefaultTenantResolver;
use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;

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

    protected ?int $currentBranchId = null;

    /** @var array<int, int>|null */
    protected ?array $accessibleBranchIds = null;

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

    // -----------------------------------------------------------------
    // Branches
    // -----------------------------------------------------------------

    /**
     * Branch id meaning "every branch": the scope of assignments made
     * without a branch, and the scope of checks in "All branches" mode.
     */
    public const ALL_BRANCHES = 0;

    public function branchesEnabled(): bool
    {
        return (bool) config('permission.branches.enabled', false);
    }

    /**
     * Pin the branch for the rest of the request. Pass ALL_BRANCHES (0) for
     * the "All branches" view, or null to unpin. The resolve_branch
     * middleware calls this for you.
     */
    public function setCurrentBranchId(?int $branchId): static
    {
        $this->currentBranchId = $branchId;
        $this->memo = [];

        return $this;
    }

    public function getCurrentBranchId(): ?int
    {
        return $this->currentBranchId;
    }

    /**
     * The branches the current user may see, used by BelongsToBranch in
     * "All branches" mode. Set by the resolve_branch middleware.
     *
     * @param  array<int, int>|null  $branchIds
     */
    public function setAccessibleBranchIds(?array $branchIds): static
    {
        $this->accessibleBranchIds = $branchIds === null
            ? null
            : array_values(array_unique(array_map('intval', $branchIds)));

        return $this;
    }

    /**
     * @return array<int, int>|null null = no branch context yet
     */
    public function getAccessibleBranchIds(): ?array
    {
        return $this->accessibleBranchIds;
    }

    public function isAllBranches(): bool
    {
        return $this->currentBranchId === self::ALL_BRANCHES;
    }

    /**
     * The branch a model's checks run in: the pinned branch, otherwise the
     * user's home branch from the resolver, otherwise ALL_BRANCHES. Always
     * ALL_BRANCHES when branches are disabled.
     */
    public function branchIdFor(?Model $model = null): int
    {
        if (! $this->branchesEnabled()) {
            return self::ALL_BRANCHES;
        }

        if ($this->currentBranchId !== null) {
            return $this->currentBranchId;
        }

        if ($model === null) {
            return self::ALL_BRANCHES;
        }

        return $this->branchResolver()->defaultBranchId($model) ?? self::ALL_BRANCHES;
    }

    /**
     * Branch ids BelongsToBranch models are filtered to, or null when there
     * is no branch context (nothing pinned).
     *
     * @return array<int, int>|null
     */
    public function visibleBranchIds(): ?array
    {
        if ($this->currentBranchId === null) {
            return null;
        }

        if ($this->currentBranchId === self::ALL_BRANCHES) {
            return $this->accessibleBranchIds ?? [];
        }

        return [$this->currentBranchId];
    }

    /**
     * Whether a BelongsToBranch query with no branch context returns no
     * rows (true) or is left unfiltered (false).
     */
    public function branchScopeFailsClosed(): bool
    {
        return (bool) config('permission.branches.strict', true) && ! app()->runningInConsole();
    }

    public function branchResolver(): BranchResolver
    {
        return app(config('permission.branches.resolver') ?? DefaultBranchResolver::class);
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
     * and branch scope. $resolver computes the list on a miss.
     *
     * @param  Closure(): array<int, string>  $resolver
     * @return array<int, string>
     */
    public function rememberEffectivePermissions(Model $model, int $tenantId, Closure $resolver, int $branchId = self::ALL_BRANCHES): array
    {
        $key = sprintf(
            '%s:v%d:%s:v%d:t%d:b%d',
            $this->prefix(),
            $this->version('global'),
            $this->modelToken($model),
            $this->version('model:'.$this->modelToken($model)),
            $tenantId,
            $branchId
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
