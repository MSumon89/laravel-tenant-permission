@extends($pm->layout())

@section('title', 'Permissions')

@section($pm->section())
<div class="pm">
    @include('permission::partials.header', [
        'title' => 'Permissions',
        'subtitle' => $canManageCatalog
            ? 'The catalog of everything that can be granted. Your code checks these names.'
            : 'The catalog of everything that can be granted. Only a Super Admin can change it.',
        'action' => $canManageCatalog ? ['label' => 'New permission', 'url' => $pm->route('permissions.create')] : null,
    ])

    <form method="GET" class="pm-toolbar" role="search">
        <input class="pm-input" type="search" name="search" value="{{ request('search') }}" placeholder="Search permissions" aria-label="Search permissions">
        <button class="pm-btn" type="submit">Search</button>
    </form>

    <div class="pm-stack">
        @forelse ($permissionsByModule as $module => $permissions)
            <div class="pm-card pm-table-wrap">
                <table class="pm-table">
                    <thead>
                        <tr>
                            <th>{{ \Illuminate\Support\Str::headline($module) }}</th>
                            <th class="pm-hide-sm">Description</th>
                            <th class="pm-num">Roles</th>
                            @if ($canManageCatalog)
                                <th><span style="position:absolute;left:-9999px">Actions</span></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($permissions as $permission)
                            <tr>
                                <td>
                                    {{ $permission->display_name ?: $permission->name }}
                                    @unless ($permission->status)
                                        <span class="pm-badge">Inactive</span>
                                    @endunless
                                    <div class="pm-code">{{ $permission->name }}</div>
                                </td>
                                <td class="pm-hide-sm pm-muted">{{ $permission->description }}</td>
                                <td class="pm-num">{{ $permission->roles_count }}</td>
                                @if ($canManageCatalog)
                                    <td>
                                        <div class="pm-actions">
                                            <a class="pm-btn pm-btn-sm" href="{{ $pm->route('permissions.edit', $permission->getKey()) }}">Edit</a>
                                            <form method="POST" action="{{ $pm->route('permissions.destroy', $permission->getKey()) }}"
                                                  data-pm-confirm="Delete &quot;{{ $permission->name }}&quot;? It is removed from every role and user.">
                                                @csrf
                                                @method('DELETE')
                                                <button class="pm-btn pm-btn-sm pm-btn-danger" type="submit">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <div class="pm-card pm-empty">No permissions found.</div>
        @endforelse
    </div>
</div>
@endsection
