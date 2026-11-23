@extends($pm->layout())

@section('title', $permission->exists ? 'Edit permission' : 'New permission')

@section($pm->section())
<div class="pm">
    @include('permission::partials.header', [
        'title' => $permission->exists ? 'Edit permission' : 'New permission',
        'subtitle' => 'Name it "<module>.<action>", e.g. "invoices.refund". Your code checks this exact name.',
    ])

    <form method="POST" class="pm-stack"
          action="{{ $permission->exists ? $pm->route('permissions.update', $permission->getKey()) : $pm->route('permissions.store') }}">
        @csrf
        @if ($permission->exists)
            @method('PUT')
        @endif

        <div class="pm-card pm-grid-2">
            <label class="pm-field">
                <span>Name</span>
                @if ($permission->exists)
                    <input class="pm-input" value="{{ $permission->name }}" disabled>
                    <small class="pm-muted pm-small">Names can't change, because code depends on them. Create a new permission instead.</small>
                @else
                    <input class="pm-input" name="name" value="{{ old('name') }}" required maxlength="255" placeholder="invoices.refund">
                @endif
            </label>
            <label class="pm-field">
                <span>Display name <span class="pm-muted">(optional)</span></span>
                <input class="pm-input" name="display_name" value="{{ old('display_name', $permission->display_name) }}" maxlength="255" placeholder="Refund invoices">
            </label>
            <label class="pm-field">
                <span>Module <span class="pm-muted">(defaults to the part before the dot)</span></span>
                <input class="pm-input" name="module" value="{{ old('module', $permission->module) }}" maxlength="255" placeholder="invoices">
            </label>
            <label class="pm-field">
                <span>Description <span class="pm-muted">(optional)</span></span>
                <input class="pm-input" name="description" value="{{ old('description', $permission->description) }}" maxlength="255">
            </label>
            <label class="pm-check">
                <input type="hidden" name="status" value="0">
                <input type="checkbox" name="status" value="1" @checked(old('status', $permission->exists ? $permission->status : true))>
                <span>Active <span class="pm-muted">(shown as a label only, checks are not affected)</span></span>
            </label>
        </div>

        <div class="pm-form-actions">
            <a class="pm-btn" href="{{ $pm->route('permissions.index') }}">Cancel</a>
            <button class="pm-btn pm-btn-primary" type="submit">{{ $permission->exists ? 'Save changes' : 'Create permission' }}</button>
        </div>
    </form>
</div>
@endsection
