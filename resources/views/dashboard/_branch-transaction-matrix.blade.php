<div class="scroll-panel transaction-matrix-scroll">
    <table class="table mb-0 transaction-matrix">
        <thead>
            <tr>
                <th>Store Branch</th>
                @foreach($paymentColumns as $column)
                    <th class="text-end">{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($matrix as $row)
                <tr>
                    <td class="fw-semibold">{{ $row['name'] }}</td>
                    @foreach($row['amounts'] as $amount)
                        <td class="text-end text-nowrap">₱{{ number_format($amount, 2) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($paymentColumns) + 1 }}" class="empty-state">No stores available.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
