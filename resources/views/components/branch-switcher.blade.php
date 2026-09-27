@props(['branches', 'current'])

<form action="{{ $tenantUrl->route('branchSwitcher.update') }}" method="POST" style="display:flex;align-items:center">
    @csrf
    @method('PUT')
    <select name="branch_id" class="sfp-select" style="min-width:160px" onchange="this.form.submit()">
        @foreach ($branches as $branch)
            <option value="{{ $branch->id }}" @selected($current && $current->id === $branch->id)>{{ $branch->name }}</option>
        @endforeach
    </select>
</form>
