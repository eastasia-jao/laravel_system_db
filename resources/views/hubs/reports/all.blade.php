<div class="card shadow-sm border-0">
    <div class="card-header bg-white fw-bold">Sales by Channel</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Channel</th><th>Transactions</th><th>Total Sales</th><th class="text-end">Action</th></tr></thead>
            <tbody>
                @forelse($salesByChannel as $row)
                    <tr>
                        <td class="fw-bold">{{ strtoupper(str_replace('_', ' ', $row->channel_type)) }}</td>
                        <td>{{ number_format($row->transaction_count) }}</td>
                        <td class="fw-bold text-success">₱{{ number_format($row->total, 2) }}</td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('hub.report', ['hub' => $hub->id, 'channel' => $row->channel_type] + $filterParams) }}">Open Report</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">No verified sales found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
