<div class="sfp-split-2">
    <div class="sfp-field">
        <label class="sfp-label">Name *</label>
        <input type="text" name="name" class="sfp-input" value="{{ old('name', $managedUser->name ?? '') }}">
        @error('name')
            <span class="sfp-invalid-feedback">{{ $message }}</span>
        @enderror
    </div>

    <div class="sfp-field">
        <label class="sfp-label">Username *</label>
        <input type="text" name="username" class="sfp-input" value="{{ old('username', $managedUser->username ?? '') }}">
        @error('username')
            <span class="sfp-invalid-feedback">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="sfp-split-2">
    <div class="sfp-field">
        <label class="sfp-label">Email</label>
        <input type="email" name="email" class="sfp-input" value="{{ old('email', $managedUser->email ?? '') }}">
        @error('email')
            <span class="sfp-invalid-feedback">{{ $message }}</span>
        @enderror
    </div>

    <div class="sfp-field">
        <label class="sfp-label">Password {{ isset($managedUser) ? '' : '*' }}</label>
        <input type="password" name="password" class="sfp-input" autocomplete="new-password" placeholder="{{ isset($managedUser) ? 'Leave blank to keep current' : '' }}">
        @error('password')
            <span class="sfp-invalid-feedback">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="sfp-field">
    <label class="sfp-label">Role *</label>
    @foreach ($roles as $role)
        <div class="form-check">
            <input type="checkbox" name="roles[]" value="{{ $role->name }}" id="role-{{ $role->id }}" class="form-check-input" @checked(in_array($role->name, $selectedRoles, true)) @disabled($isSelf)>
            <label class="form-check-label" for="role-{{ $role->id }}">
                {{ $role->name }}
                @if ($role->description)
                    <span style="font-size:12.5px;color:#66736F"> &mdash; {{ $role->description }}</span>
                @endif
            </label>
        </div>
    @endforeach
    @if ($isSelf)
        <p style="font-size:12.5px;color:#66736F;margin:6px 0 0">You cannot change your own role.</p>
    @endif
    @error('roles')
        <span class="sfp-invalid-feedback">{{ $message }}</span>
    @enderror
    @error('roles.*')
        <span class="sfp-invalid-feedback">{{ $message }}</span>
    @enderror
</div>

<div class="sfp-field">
    <label class="sfp-label">Branches *</label>
    @foreach ($branches as $branch)
        <div class="form-check">
            <input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" id="branch-{{ $branch->id }}" class="form-check-input" @checked(in_array($branch->id, $selectedBranchIds))>
            <label class="form-check-label" for="branch-{{ $branch->id }}">{{ $branch->name }}</label>
        </div>
    @endforeach
    @error('branch_ids')
        <span class="sfp-invalid-feedback">{{ $message }}</span>
    @enderror
</div>
