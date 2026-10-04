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
                        <button type="button" class="sfp-btn-outline" style="padding:4px 12px;font-size:12.5px" data-bs-toggle="modal" data-bs-target="#discardDraftModal" data-discard-url="{{ $tenantUrl->route('billDrafts.destroy', $draft->id) }}" data-discard-client="{{ $draft->client_name ?? __('Walk-in customer') }}">Discard</button>
                    </span>
                </div>
            @endforeach
        </div>

        <div class="modal fade" id="discardDraftModal" tabindex="-1" aria-labelledby="discardDraftModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form id="discard-draft-form" method="POST" action="">
                        @csrf
                        @method('DELETE')

                        <div class="modal-header">
                            <h5 class="modal-title" id="discardDraftModalLabel">Discard draft</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <p style="font-size:13.5px;color:#66736F;margin:0">
                                Discard the draft for <strong id="discard-draft-client"></strong>? This cannot be undone.
                            </p>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="sfp-btn-outline" data-bs-dismiss="modal">Keep draft</button>
                            <button type="submit" class="sfp-btn-primary" style="background:#A8506B;border-color:#A8506B">Discard</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            document.getElementById('discardDraftModal').addEventListener('show.bs.modal', (event) => {
                document.getElementById('discard-draft-form').action = event.relatedTarget.dataset.discardUrl;
                document.getElementById('discard-draft-client').textContent = event.relatedTarget.dataset.discardClient;
            });
        </script>
    @endif
@endcan
