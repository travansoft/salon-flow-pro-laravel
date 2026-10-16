<div class="sfp-table-wrap">
    <div class="sfp-table-head-row" style="grid-template-columns:2fr repeat({{ count($matrix['actions']) }}, 1fr)">
        <span>Module</span>
        @foreach ($matrix['actions'] as $action)
            <span style="text-align:center">{{ ucfirst($action) }}</span>
        @endforeach
    </div>
    @foreach ($matrix['modules'] as $module => $permissions)
        <div class="sfp-table-row" style="grid-template-columns:2fr repeat({{ count($matrix['actions']) }}, 1fr)">
            <span>{{ \Illuminate\Support\Str::headline($module) }}</span>
            @foreach ($matrix['actions'] as $action)
                <span style="text-align:center">
                    @if (isset($permissions[$action]))
                        <input type="checkbox" class="form-check-input" name="permissions[]" value="{{ $permissions[$action] }}" id="perm-{{ $permissions[$action] }}" @checked(in_array($permissions[$action], $granted, true)) @disabled($readonly)>
                    @endif
                </span>
            @endforeach
        </div>
    @endforeach
</div>

@if (count($matrix['extras']))
    <div style="margin-top:14px">
        <div class="sfp-label">Additional permissions</div>
        @foreach ($matrix['extras'] as $permission => $label)
            <div class="form-check">
                <input type="checkbox" class="form-check-input" name="permissions[]" value="{{ $permission }}" id="perm-{{ $permission }}" @checked(in_array($permission, $granted, true)) @disabled($readonly)>
                <label class="form-check-label" for="perm-{{ $permission }}">{{ $label }}</label>
            </div>
        @endforeach
    </div>
@endif
