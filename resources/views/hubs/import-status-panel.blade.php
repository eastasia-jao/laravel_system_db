@php
    $latestImport = $latestImport ?? null;
    $importStatus = $latestImport?->processing_status ?: match ($latestImport?->status) {
        'approved' => 'completed',
        'rejected' => 'failed',
        default => $latestImport ? 'queued' : null,
    };
@endphp

<section id="importStatusPanel" class="card border-0 shadow-sm mb-4" data-status-url="{{ route('hub.products.import-status', $hub->id) }}" @if(! $latestImport) hidden @endif>
    <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div>
                <div class="hub-section-title mb-1">Latest product import</div>
                <div id="importFileName" class="fw-semibold text-break">{{ $latestImport?->file_name }}</div>
            </div>
            <span id="importStatusBadge" class="badge rounded-pill text-bg-{{ $importStatus === 'completed' ? 'success' : ($importStatus === 'failed' ? 'danger' : ($importStatus === 'processing' ? 'primary' : 'warning')) }} px-3 py-2">
                {{ ucfirst($importStatus ?? 'No import') }}
            </span>
        </div>
        <p id="importStatusMessage" class="text-muted small mb-3">
            @if($importStatus === 'queued') Waiting for the import worker to start. This panel updates automatically. @elseif($importStatus === 'processing') The file is currently being imported. This panel updates automatically. @elseif($importStatus === 'failed') {{ $latestImport?->processing_error ?: 'The import did not finish. Review the file and try again.' }} @elseif($importStatus === 'completed') Import completed successfully. @endif
        </p>
        <div class="row row-cols-2 row-cols-md-4 g-2 text-center">
            <div class="col"><div class="border rounded p-2 small text-muted">Rows in file<strong id="importTotalRows" class="d-block fs-5 text-dark">{{ $latestImport?->total_rows ?? '—' }}</strong></div></div>
            <div class="col"><div class="border rounded p-2 small text-muted">Created<strong id="importCreated" class="d-block fs-5 text-success">{{ $latestImport?->created_count ?? '—' }}</strong></div></div>
            <div class="col"><div class="border rounded p-2 small text-muted">Updated<strong id="importUpdated" class="d-block fs-5 text-primary">{{ $latestImport?->updated_count ?? '—' }}</strong></div></div>
            <div class="col"><div class="border rounded p-2 small text-muted">Skipped<strong id="importSkipped" class="d-block fs-5 text-secondary">{{ $latestImport?->skipped_count ?? '—' }}</strong></div></div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const panel = document.getElementById('importStatusPanel');
    if (!panel) return;
    const setText = (id, value) => document.getElementById(id).textContent = value ?? '—';
    const labels = { queued: 'Queued', processing: 'Processing', completed: 'Completed', failed: 'Failed' };
    const messages = {
        queued: 'Waiting for the import worker to start. This panel updates automatically.',
        processing: 'The file is currently being imported. This panel updates automatically.',
        completed: 'Import completed successfully.',
        failed: 'The import did not finish. Review the file and try again.'
    };
    const classes = { queued: 'text-bg-warning', processing: 'text-bg-primary', completed: 'text-bg-success', failed: 'text-bg-danger' };
    let timer;
    const refresh = async () => {
        try {
            const response = await fetch(panel.dataset.statusUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) return;
            const record = (await response.json()).import;
            if (!record) { panel.hidden = true; return; }
            panel.hidden = false;
            setText('importFileName', record.file_name);
            setText('importTotalRows', record.total_rows);
            setText('importCreated', record.created_count);
            setText('importUpdated', record.updated_count);
            setText('importSkipped', record.skipped_count);
            const badge = document.getElementById('importStatusBadge');
            badge.textContent = labels[record.status] || record.status;
            badge.className = `badge rounded-pill ${classes[record.status] || 'text-bg-secondary'} px-3 py-2`;
            document.getElementById('importStatusMessage').textContent = record.status === 'failed' && record.error ? record.error : (messages[record.status] || '');
            if (!['queued', 'processing'].includes(record.status) && timer) clearInterval(timer);
        } catch (_) {
            // The next scheduled check will try again without interrupting the user.
        }
    };
    refresh();
    if (['queued', 'processing'].includes(@json($importStatus))) timer = setInterval(refresh, 8000);
});
</script>
