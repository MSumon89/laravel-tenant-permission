{{--
    $permissionsByModule  Collection<module, Collection<Permission>>
    $selected             array<string>   checked permission names
    $grantable            array<string>|null  null = actor may grant anything
--}}
<div class="pm-modules">
    @forelse ($permissionsByModule as $module => $permissions)
        <fieldset class="pm-card pm-module" data-pm-module>
            <legend>
                <span class="pm-module-head">
                    <label class="pm-check" style="padding:0">
                        <input type="checkbox" data-pm-toggle-all aria-label="Select all {{ $module }} permissions">
                        <strong>{{ \Illuminate\Support\Str::headline($module) }}</strong>
                    </label>
                    <span class="pm-muted pm-small">{{ $permissions->count() }}</span>
                </span>
            </legend>
            <div>
                @foreach ($permissions as $permission)
                    @php($canGrant = $grantable === null || in_array($permission->name, $grantable, true))
                    <label class="pm-check" @if ($permission->description) title="{{ $permission->description }}" @endif>
                        <input type="checkbox" name="permissions[]" value="{{ $permission->name }}"
                            @checked(in_array($permission->name, $selected, true))
                            @disabled(! $canGrant)>
                        <span>
                            {{ $permission->display_name ?: $permission->name }}
                            @if ($permission->display_name)
                                <span class="pm-code">{{ $permission->name }}</span>
                            @endif
                            @unless ($canGrant)
                                <span class="pm-muted pm-small">(you don't hold this)</span>
                            @endunless
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @empty
        <div class="pm-card pm-empty">The permission catalog is empty. Add permissions first.</div>
    @endforelse
</div>
