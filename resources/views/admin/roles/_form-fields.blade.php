<div class="sfp-field">
    <label class="sfp-label">Role name *</label>
    <input type="text" name="name" class="sfp-input" value="{{ old('name', $role->name ?? '') }}" @readonly(isset($role) && $role->isBuiltIn())>
    @error('name')
        <span class="sfp-invalid-feedback">{{ $message }}</span>
    @enderror
</div>

<div class="sfp-field">
    <label class="sfp-label">Description</label>
    <input type="text" name="description" class="sfp-input" value="{{ old('description', $role->description ?? '') }}" placeholder="What is this role for?">
    @error('description')
        <span class="sfp-invalid-feedback">{{ $message }}</span>
    @enderror
</div>

<div class="sfp-field">
    <label class="sfp-label">Permissions *</label>
    @include('admin.roles._permission-matrix', ['matrix' => $matrix, 'granted' => old('permissions', $granted ?? []), 'readonly' => false])
    @error('permissions')
        <span class="sfp-invalid-feedback">{{ $message }}</span>
    @enderror
    @error('permissions.*')
        <span class="sfp-invalid-feedback">{{ $message }}</span>
    @enderror
</div>
