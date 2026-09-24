@extends('layouts.admin')

@section('title', 'Add Staff')

@section('content')
    <div class="sfp-page-header">
        <div>
            <h1 class="sfp-page-title">Add staff</h1>
            <p class="sfp-page-subtitle">Create a staff record. A login is optional.</p>
        </div>
    </div>

    <div class="sfp-card">
        <form action="{{ $tenantUrl->route('staff.store') }}" method="POST">
            @csrf

            <div class="sfp-card-title">Profile</div>

            <div class="sfp-field">
                <label class="sfp-label">Name *</label>
                <input type="text" name="name" class="sfp-input" value="{{ old('name') }}">
                @error('name')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Designation *</label>
                <select name="designation_id" class="sfp-input">
                    <option value="">&mdash;</option>
                    @foreach ($designations as $designation)
                        <option value="{{ $designation->id }}" @selected(old('designation_id') == $designation->id)>{{ $designation->name }}</option>
                    @endforeach
                </select>
                @error('designation_id')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Phone</label>
                <input type="text" name="phone" class="sfp-input" value="{{ old('phone') }}">
                @error('phone')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Email</label>
                <input type="email" name="email" class="sfp-input" value="{{ old('email') }}">
                @error('email')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-field form-check">
                <input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', true))>
                <label class="form-check-label sfp-label" style="display: inline; text-transform: none; letter-spacing: normal;">Active</label>
            </div>

            <div class="sfp-card-title" style="margin-top: 24px;">System access</div>
            <p style="color: #66736F; font-size: 13px; margin-top: -8px;">Not every staff member needs to log in. Check this only if they need access to the admin panel.</p>

            <div class="sfp-field form-check">
                <input type="checkbox" name="create_login" value="1" id="create_login" class="form-check-input" @checked(old('create_login')) onchange="document.getElementById('login-fields').style.display = this.checked ? 'block' : 'none'">
                <label class="form-check-label sfp-label" for="create_login" style="display: inline; text-transform: none; letter-spacing: normal;">Create a login for this staff member</label>
            </div>

            <div id="login-fields" style="display: {{ old('create_login') ? 'block' : 'none' }};">
                <div class="sfp-field">
                    <label class="sfp-label">Username</label>
                    <input type="text" name="username" class="sfp-input" value="{{ old('username') }}">
                    @error('username')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sfp-field">
                    <label class="sfp-label">Password</label>
                    <input type="password" name="password" class="sfp-input">
                    @error('password')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sfp-field">
                    <label class="sfp-label">Roles</label>
                    @foreach ($roles as $role)
                        <div class="form-check">
                            <input type="checkbox" name="roles[]" value="{{ $role->name }}" id="role-{{ $role->id }}" class="form-check-input" @checked(collect(old('roles', []))->contains($role->name))>
                            <label class="form-check-label" for="role-{{ $role->id }}">{{ $role->name }}</label>
                        </div>
                    @endforeach
                    @error('roles')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sfp-field">
                    <label class="sfp-label">Branches</label>
                    @foreach ($branches as $branch)
                        <div class="form-check">
                            <input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" id="branch-{{ $branch->id }}" class="form-check-input" @checked(collect(old('branch_ids', []))->contains($branch->id))>
                            <label class="form-check-label" for="branch-{{ $branch->id }}">{{ $branch->name }}</label>
                        </div>
                    @endforeach
                    @error('branch_ids')
                        <span class="sfp-invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="sfp-card-title" style="margin-top: 24px;">Services</div>
            <p style="color: #66736F; font-size: 13px; margin-top: -8px;">Select the services this staff member is eligible to perform. Only mapped staff can be assigned to these services when billing.</p>

            <div class="sfp-field">
                @foreach ($services as $service)
                    <div class="form-check">
                        <input type="checkbox" name="service_ids[]" value="{{ $service->id }}" id="service-{{ $service->id }}" class="form-check-input" @checked(collect(old('service_ids', []))->contains($service->id))>
                        <label class="form-check-label" for="service-{{ $service->id }}">{{ $service->name }}</label>
                    </div>
                @endforeach
                @error('service_ids')
                    <span class="sfp-invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="sfp-card-title" style="margin-top: 24px;">HR record <span style="font-weight: 400; color: #94A19D; text-transform: none; letter-spacing: normal;">(optional)</span></div>

            <div class="sfp-field">
                <label class="sfp-label">Employee code</label>
                <input type="text" name="employee_code" class="sfp-input" value="{{ old('employee_code') }}">
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Date of joining</label>
                <input type="date" name="date_of_joining" class="sfp-input" value="{{ old('date_of_joining') }}">
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Employment type</label>
                <input type="text" name="employment_type" class="sfp-input" placeholder="e.g. Full-time, Part-time" value="{{ old('employment_type') }}">
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Date of birth</label>
                <input type="date" name="date_of_birth" class="sfp-input" value="{{ old('date_of_birth') }}">
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Gender</label>
                <input type="text" name="gender" class="sfp-input" value="{{ old('gender') }}">
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Address</label>
                <textarea name="address" class="sfp-input">{{ old('address') }}</textarea>
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Emergency contact name</label>
                <input type="text" name="emergency_contact_name" class="sfp-input" value="{{ old('emergency_contact_name') }}">
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Emergency contact phone</label>
                <input type="text" name="emergency_contact_phone" class="sfp-input" value="{{ old('emergency_contact_phone') }}">
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Base salary</label>
                <input type="number" step="0.01" name="base_salary" class="sfp-input" value="{{ old('base_salary') }}">
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Bank account number</label>
                <input type="text" name="bank_account_number" class="sfp-input" value="{{ old('bank_account_number') }}">
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Bank IFSC</label>
                <input type="text" name="bank_ifsc" class="sfp-input" value="{{ old('bank_ifsc') }}">
            </div>

            <div class="sfp-field">
                <label class="sfp-label">Government ID number</label>
                <input type="text" name="government_id_number" class="sfp-input" value="{{ old('government_id_number') }}">
            </div>

            <div class="sfp-form-actions">
                <button type="submit" class="sfp-btn-primary">Save</button>
            </div>
        </form>
    </div>
@endsection
