@extends($pm->layout())

@section('title', 'Roles')

@section($pm->section())
<div class="pm">
    @include('permission::partials.header', [
        'title' => 'Roles',
        'subtitle' => 'Group permissions into roles, then assign roles to users.',
        'action' => ['label' => 'New role', 'url' => $pm->route('roles.create')],
    ])

    <form method="GET" class="pm-toolbar" role="search">
        <input class="pm-input" type="search" name="search" value="{{ request('search') }}" placeholder="Search roles" aria-label="Search roles">
        <button class="pm-btn" type="submit">Search</button>
    </form>

    <div class="pm-card pm-table-wrap">
        <table class="pm-table">
            <thead>
                <tr>
                    <th>Role</th>
                    <th class="pm-num">Users</th>
                    <th class="pm-num">Permissions</th>
                    <th><span class="sr-only" style="position:absolute;left:-9999px">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($roles as $role)
                    <tr>
                        <td>
                            <strong>{{ $role->name }}</strong>
                            @if ($role->isSuperAdminRole())
                                <span class="pm-badge pm-badge-accent">Super Admin</span>
                            @elseif ($role->is_admin)
                                <span class="pm-badge pm-badge-accent">Full access</span>
                            @endif
                            @if ($role->is_system)
                                <span class="pm-badge">System</span>
                            @endif
                            @if ($role->description)
                                <div class="pm-muted pm-small">{{ $role->description }}</div>
                            @endif
                        </td>
                        <td class="pm-num">{{ $role->users_count }}</td>
                        <td class="pm-num">{{ $role->is_admin || $role->isSuperAdminRole() ? 'All' : $role->permissions_count }}</td>
                        <td>
                            <div class="pm-actions">
                                <a class="pm-btn pm-btn-sm" href="{{ $pm->route('roles.edit', $role->getKey()) }}">Edit</a>
                                @unless ($role->is_system)
                                    <form method="POST" action="{{ $pm->route('roles.destroy', $role->getKey()) }}"
                                          data-pm-confirm="Delete the &quot;{{ $role->name }}&quot; role? Users holding it lose its permissions.">
                                        @csrf
                                        @method('DELETE')
                                        <button class="pm-btn pm-btn-sm pm-btn-danger" type="submit">Delete</button>
                                    </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="pm-empty">No roles yet. Create one to get started.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $roles->links('permission::partials.pagination') }}
</div>
@endsection
