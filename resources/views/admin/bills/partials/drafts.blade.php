@can('billing.create')
    @if($drafts->isNotEmpty())
        <div class="sfp-table-wrap" style="margin-bottom:16px">
            <div class="sfp-table-head-row" style="grid-template-columns:1.4fr 1fr 80px 120px 140px 160px">
                <span>Draft bills ({{ $drafts->count() }})</span>
                <span>Mobile</span>
                <span>Items</span>
                <span>Total</span>
                <span>Saved</span>
                <span></span>
            </div>

            @foreach($drafts as $draft)
                <div class="sfp-table-row" style="grid-template-columns:1.4fr 1fr 80px 120px 140px 160px">
                    <span style="font-size:14px">{{ $draft->client_name ?? __('Walk-in customer') }}</span>
                    <span style="font-size:13px;color:#66736F">{{ $draft->client_phone ?? '—' }}</span>
                    <span style="font-size:13px;color:#66736F">{{ $draft->item_count }}</span>
                    <span class="sfp-mono" style="font-size:13.5px">&#8377;{{ number_format($draft->total, 2) }}</span>
                    <span style="font-size:13px;color:#66736F">{{ $draft->updated_at->diffForHumans() }}</span>
                    <span style="display:flex;gap:12px;align-items:center">
                        <a href="{{ $tenantUrl->route('bills.create') }}?draft={{ $draft->id }}" class="sfp-btn-primary" style="padding:4px 12px;font-size:12.5px">Continue</a>
                        <form method="POST" action="{{ $tenantUrl->route('billDrafts.destroy', $draft->id) }}" onsubmit="return confirm('Discard this draft?')" style="margin:0">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="sfp-btn-outline" style="padding:4px 12px;font-size:12.5px">Discard</button>
                        </form>
                    </span>
                </div>
            @endforeach
        </div>
    @endif
@endcan
