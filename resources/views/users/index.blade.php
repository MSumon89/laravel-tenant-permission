@extends($pm->layout())

@section('title', 'Users')

@section($pm->section())
<div class="pm">
    @include('permission::partials.header', [
        'title' => 'Users',
        'subtitle' => 'Pick a user to change their roles or grant/deny single permissions.',
    ])

    <form method="GET" class="pm-toolbar" role="search">
        <input class="pm-input" type="search" name="search" value="{{ request('search') }}" placeholder="Search users" aria-label="Search users">
        <button class="pm-btn" type="submit">Search</button>
    </form>

    <div class="pm-card pm-table-wrap">
        <table class="pm-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Roles</th>
                    <th><span style="position:absolute;left:-9999px">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>
                            <strong>{{ $user->getAttribute($displayColumn) ?? 'User #'.$user->getKey() }}</strong>
                            @if ($user->getAttribute('email'))
                                <div class="pm-muted pm-small">{{ $user->getAttribute('email') }}</div>
                            @endif
                        </td>
                        <td>
                            @forelse ($user->rolesForTenant($tenantId) as $role)
                                <span @class(['pm-badge', 'pm-badge-accent' => $role->is_admin || $role->isSuperAdminRole()])>{{ $role->name }}</span>
                            @empty
                                <span class="pm-muted">No roles</span>
                            @endforelse
                        </td>
                        <td>
                            <div class="pm-actions">
                                <a class="pm-btn pm-btn-sm" href="{{ $pm->route('users.edit', $user->getKey()) }}">Manage access</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="pm-empty">No users found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links('permission::partials.pagination') }}
</div>
@endsection
