@extends($pm->layout())

@section('title', $role->exists ? 'Edit role' : 'New role')

@section($pm->section())
<div class="pm">
    @include('permission::partials.header', [
        'title' => $role->exists ? 'Edit role: '.$role->name : 'New role',
        'subtitle' => 'Tick the permissions holders of this role should get.',
    ])

    <form method="POST" class="pm-stack"
          action="{{ $role->exists ? $pm->route('roles.update', $role->getKey()) : $pm->route('roles.store') }}">
        @csrf
        @if ($role->exists)
            @method('PUT')
        @endif

        <div class="pm-card pm-grid-2">
            <label class="pm-field">
                <span>Name</span>
                <input class="pm-input" name="name" value="{{ old('name', $role->name) }}" maxlength="255"
                       @required(! $nameLocked) @disabled($nameLocked)>
                @if ($nameLocked)
                    <small class="pm-muted pm-small">System role names can't be changed.</small>
                @endif
            </label>
            <label class="pm-field">
                <span>Description <span class="pm-muted">(optional)</span></span>
                <input class="pm-input" name="description" value="{{ old('description', $role->description) }}" maxlength="255">
            </label>
            @if ($canSetAdmin)
                <label class="pm-check" style="grid-column:1/-1">
                    <input type="hidden" name="is_admin" value="0">
                    <input type="checkbox" name="is_admin" value="1" @checked(old('is_admin', $role->is_admin))>
                    <span>
                        <strong>Full access</strong>
                        <span class="pm-muted">: holders get every permission in this scope, including ones added later. The ticks below are ignored.</span>
                    </span>
                </label>
            @endif
        </div>

        @if ($role->isSuperAdminRole())
            <div class="pm-alert pm-alert-info">The Super Admin role passes every check. Its permission list is not used.</div>
        @endif

        @include('permission::partials.permission-picker', [
            'permissionsByModule' => $permissionsByModule,
            'selected' => old('permissions', $selected),
            'grantable' => $grantable,
        ])

        <div class="pm-form-actions">
            <a class="pm-btn" href="{{ $pm->route('roles.index') }}">Cancel</a>
            <button class="pm-btn pm-btn-primary" type="submit">{{ $role->exists ? 'Save changes' : 'Create role' }}</button>
        </div>
    </form>
</div>
@endsection
