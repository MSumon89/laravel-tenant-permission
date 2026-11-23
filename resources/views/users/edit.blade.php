@extends($pm->layout())

@section('title', 'Manage access')

@section($pm->section())
@php($userName = $user->getAttribute($displayColumn) ?? 'User #'.$user->getKey())
<div class="pm">
    @include('permission::partials.header', [
        'title' => $userName,
        'subtitle' => 'Roles give a set of permissions. Overrides add (Grant) or remove (Deny) single permissions for this user only. Deny always wins.',
    ])

    @if ($isSuperAdmin)
        <div class="pm-alert pm-alert-info">This user is a Super Admin and passes every check, whatever is set below.</div>
    @elseif ($isTenantAdmin)
        <div class="pm-alert pm-alert-info">This user holds a full-access role and has every permission in this scope.</div>
    @endif

    <form method="POST" action="{{ $pm->route('users.update', $user->getKey()) }}" class="pm-stack">
        @csrf
        @method('PUT')

        <section class="pm-card">
            <h2 class="pm-h2">Roles</h2>
            <p class="pm-muted pm-small" style="margin:0 0 8px">Greyed-out roles contain permissions you don't hold, so you can't change them.</p>
            @forelse ($roles as $role)
                @php($canAssign = in_array($role->getKey(), $assignableIds, true))
                <label class="pm-check">
                    <input type="checkbox" name="roles[]" value="{{ $role->getKey() }}"
                        @checked(in_array($role->getKey(), old('roles', $heldIds)))
                        @disabled(! $canAssign)>
                    <span>
                        <strong>{{ $role->name }}</strong>
                        @if ($role->is_admin || $role->isSuperAdminRole())
                            <span class="pm-badge pm-badge-accent">Full access</span>
                        @endif
                        @if ($role->description)
                            <span class="pm-muted"> | {{ $role->description }}</span>
                        @endif
                    </span>
                </label>
            @empty
                <p class="pm-muted">No roles in this scope yet. <a href="{{ $pm->route('roles.create') }}">Create one</a>.</p>
            @endforelse
        </section>

        <section class="pm-card pm-table-wrap">
            <div style="padding:16px 16px 8px">
                <h2 class="pm-h2">Permission overrides</h2>
                <p class="pm-muted pm-small" style="margin:0">"From roles" and "Effective" show the saved state. They update after you save.</p>
            </div>
            <table class="pm-table">
                <thead>
                    <tr>
                        <th>Permission</th>
                        <th class="pm-hide-sm">From roles</th>
                        <th>Override</th>
                        <th>Effective</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($permissionsByModule as $module => $permissions)
                        <tr><th colspan="4">{{ \Illuminate\Support\Str::headline($module) }}</th></tr>
                        @foreach ($permissions as $permission)
                            @php($name = $permission->name)
                            @php($current = (old('overrides') ?? [])[$name] ?? $overrides[$name] ?? 'inherit')
                            @php($locked = $grantable !== null && ! in_array($name, $grantable, true))
                            <tr>
                                <td>
                                    {{ $permission->display_name ?: $name }}
                                    <div class="pm-code">{{ $name }}</div>
                                </td>
                                <td class="pm-hide-sm">{!! in_array($name, $viaRoles, true) ? '<span class="pm-badge pm-badge-ok">Yes</span>' : '<span class="pm-muted">–</span>' !!}</td>
                                <td>
                                    <span class="pm-radios" role="radiogroup" aria-label="Override for {{ $name }}">
                                        @foreach (['inherit' => 'Inherit', 'grant' => 'Grant', 'deny' => 'Deny'] as $value => $label)
                                            <label>
                                                <input type="radio" name="overrides[{{ $name }}]" value="{{ $value }}"
                                                    @checked($current === $value) @disabled($locked)>
                                                {{ $label }}
                                            </label>
                                        @endforeach
                                    </span>
                                    @if ($locked && ($overrides[$name] ?? null))
                                        <input type="hidden" name="overrides[{{ $name }}]" value="{{ $overrides[$name] }}">
                                    @endif
                                </td>
                                <td>
                                    @if (in_array($name, $effective, true))
                                        <span class="pm-badge pm-badge-ok">Allowed</span>
                                    @else
                                        <span class="pm-badge pm-badge-danger">No</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="4" class="pm-empty">The permission catalog is empty.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <div class="pm-form-actions">
            <a class="pm-btn" href="{{ $pm->route('users.index') }}">Back</a>
            <button class="pm-btn pm-btn-primary" type="submit">Save access</button>
        </div>
    </form>
</div>
@endsection
