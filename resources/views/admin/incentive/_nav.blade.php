<div class="sfp-row" style="margin-bottom:14px">
    <a href="{{ $tenantUrl->route('incentiveProgress.index') }}" class="{{ request()->routeIs('incentiveProgress.*') ? 'sfp-btn-pill-dark' : 'sfp-btn-outline' }}">Progress</a>
    @can('incentives.edit')
        <a href="{{ $tenantUrl->route('staffTargets.index') }}" class="{{ request()->routeIs('staffTargets.*') ? 'sfp-btn-pill-dark' : 'sfp-btn-outline' }}">Targets</a>
        <a href="{{ $tenantUrl->route('incentiveSettings.edit') }}" class="{{ request()->routeIs('incentiveSettings.*') || request()->routeIs('incentiveSlabs.*') ? 'sfp-btn-pill-dark' : 'sfp-btn-outline' }}">Settings</a>
    @endcan
    @can('incentives.create')
        <a href="{{ $tenantUrl->route('staffBonuses.create') }}" class="{{ request()->routeIs('staffBonuses.*') ? 'sfp-btn-pill-dark' : 'sfp-btn-outline' }}">+ Award bonus</a>
    @endcan
</div>
