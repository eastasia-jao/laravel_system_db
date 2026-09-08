@extends('layouts.app')

@section('content')
@php
    $actionLabels = [
        'product_import' => ['Product Import', 'success', 'fa-file-import'],
        'product_export' => ['Product Export', 'primary', 'fa-file-export'],
        'inventory_verification' => ['Inventory Verification', 'warning', 'fa-clipboard-check'],
    ];
@endphp

<div class="container-fluid py-2">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h2 class="fw-bold mb-1">Staff Activity Logs</h2>
            <p class="text-muted mb-0">Product import, export, and inventory verification history.</p>
        </div>
        <span class="badge bg-dark fs-6 px-3 py-2">{{ $logs->total() }} log{{ $logs->total() === 1 ? '' : 's' }}</span>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('staff-logs.index') }}" class="row g-3 align-items-end">
                <div class="col-xl-3 col-md-6">
                    <label class="form-label small fw-semibold">Search</label>
                    <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Staff, item, invoice...">
                </div>
                <div class="col-xl-2 col-md-6">
                    <label class="form-label small fw-semibold">Activity</label>
                    <select name="action" class="form-select">
                        <option value="">All activities</option>
                        @foreach($actionLabels as $value => $config)
                            <option value="{{ $value }}" @selected(request('action') === $value)>{{ $config[0] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-6">
                    <label class="form-label small fw-semibold">Store Hub</label>
                    <select name="hub_id" class="form-select">
                        <option value="">All hubs</option>
                        @foreach($hubs as $hub)
                            <option value="{{ $hub->id }}" @selected((string) request('hub_id') === (string) $hub->id)>{{ $hub->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-6">
                    <label class="form-label small fw-semibold">Staff Member</label>
                    <select name="user_id" class="form-select">
                        <option value="">All staff</option>
                        @foreach($staff as $member)
                            <option value="{{ $member->id }}" @selected((string) request('user_id') === (string) $member->id)>{{ $member->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-1 col-md-3">
                    <label class="form-label small fw-semibold">From</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
                </div>
                <div class="col-xl-1 col-md-3">
                    <label class="form-label small fw-semibold">To</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
                </div>
                <div class="col-xl-1 col-md-6 d-flex gap-2">
                    <button class="btn btn-primary" title="Apply filters"><i class="fa-solid fa-filter"></i></button>
                    <a href="{{ route('staff-logs.index') }}" class="btn btn-outline-secondary" title="Clear filters"><i class="fa-solid fa-rotate-left"></i></a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Date & Time</th>
                            <th>Staff</th>
                            <th>Store Hub</th>
                            <th>Activity</th>
                            <th>Summary</th>
                            <th class="text-center pe-4">Items</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            @php
                                $config = $actionLabels[$log->action_type] ?? ['Activity', 'secondary', 'fa-clock'];
                                $details = $log->details ?? [];
                            @endphp
                            <tr>
                                <td class="ps-4 text-nowrap">
                                    <div class="fw-semibold">{{ $log->created_at->format('M d, Y') }}</div>
                                    <small class="text-muted">{{ $log->created_at->format('h:i:s A') }}</small>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $log->user?->name ?? 'Deleted user' }}</div>
                                    <small class="text-muted">{{ str_replace('_', ' ', $log->user?->role ?? 'unknown') }}</small>
                                </td>
                                <td>{{ $log->storeHub?->name ?? 'N/A' }}</td>
                                <td>
                                    <span class="badge text-bg-{{ $config[1] }}">
                                        <i class="fa-solid {{ $config[2] }} me-1"></i>{{ $config[0] }}
                                    </span>
                                </td>
                                <td>
                                    <div>{{ $log->description }}</div>
                                    @if(!empty($details['file_name']))
                                        <small class="text-muted">File: {{ $details['file_name'] }}</small>
                                    @elseif(!empty($details['invoice_number']))
                                        <small class="text-muted">Invoice: {{ $details['invoice_number'] }}</small>
                                    @endif
                                </td>
                                <td class="text-center pe-4">
                                    <a href="{{ route('staff-logs.show', $log) }}" class="btn btn-sm btn-outline-primary text-nowrap">
                                        <i class="fa-solid fa-list me-1"></i> View List ({{ $log->items_count }})
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-5"><i class="fa-solid fa-clock-rotate-left fa-2x mb-3 d-block"></i>No staff activity logs found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">{{ $logs->links() }}</div>
</div>

@endsection
