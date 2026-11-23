@include('permission::partials.assets')

<nav class="pm-tabs" aria-label="Access control">
    <a href="{{ $pm->route('roles.index') }}" @class(['pm-tab', 'is-active' => $pm->is('roles.*')])>Roles</a>
    <a href="{{ $pm->route('users.index') }}" @class(['pm-tab', 'is-active' => $pm->is('users.*')])>Users</a>
    <a href="{{ $pm->route('permissions.index') }}" @class(['pm-tab', 'is-active' => $pm->is('permissions.*')])>Permissions</a>
    @if ($pm->scopeLabel())
        <span class="pm-scope pm-badge">{{ $pm->scopeLabel() }}</span>
    @endif
</nav>

<div class="pm-head">
    <div>
        <h1 class="pm-title">{{ $title }}</h1>
        @isset($subtitle)
            <p class="pm-muted" style="margin:4px 0 0">{{ $subtitle }}</p>
        @endisset
    </div>
    @isset($action)
        <a class="pm-btn pm-btn-primary" href="{{ $action['url'] }}">{{ $action['label'] }}</a>
    @endisset
</div>

@if (session('permission_status'))
    <div class="pm-alert pm-alert-ok" role="status">{{ session('permission_status') }}</div>
@endif

@if (isset($errors) && $errors->any())
    <div class="pm-alert pm-alert-error" role="alert">
        <ul>
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
