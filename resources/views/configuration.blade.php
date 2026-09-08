<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Configurations - Art Caravan PH</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <style>
        body {
            background-color: #f4f5fa;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin-left: 280px; 
        }
        .card {
            border: none !important;
            border-radius: 12px !important;
        }
        .table th {
            font-weight: 700;
            font-size: 11px;
            letter-spacing: 0.5px;
            background-color: #f8fafc !important;
        }
        .btn-light {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0 !important;
        }
        .config-nav-link {
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 10px 20px;
            border-radius: 8px;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            background: transparent;
        }
        .config-nav-link:hover {
            background-color: #f1f5f9;
            color: #334155;
        }
        .config-nav-link.active {
            background-color: #e0e7ff !important;
            color: #4f46e5 !important;
        }
        .btn i {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 14px;
        }
        .pagination-wrapper nav .text-muted,
        .pagination-wrapper nav div > p,
        .pagination-wrapper .small {
            display: none !important;
        }
    </style>
</head>
<body>

    @include('layouts.sidebar')

    <!-- Floating Toast Notification Container (Top Right) -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;">
        @if(session('success'))
            <div id="liveToast" class="toast align-items-center text-bg-success border-0 shadow-lg rounded-3" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body fw-semibold py-3">
                        <i class="fa-solid fa-circle-check me-2"></i> {{ session('success') }}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div id="liveToast" class="toast align-items-center text-bg-danger border-0 shadow-lg rounded-3" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body fw-semibold py-3">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i> {{ session('error') }}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        @endif
    </div>

    <div class="p-4" style="max-width: 1600px; margin: 0 auto;">
        <div class="mb-4">
            <h4 class="fw-bold text-dark mb-1" style="font-size: 22px;">System Configurations</h4>
            <p class="text-muted small mb-0">Adjust operational parameters, branch store registry controls, and payment channel integrations.</p>
        </div>

        <!-- Tab Navigation -->
        <div class="card shadow-sm p-2 bg-white mb-4">
            <div class="nav d-flex flex-wrap gap-1" id="configTab" role="tablist">
                <button class="config-nav-link active" data-bs-toggle="tab" data-bs-target="#tab-hubs" data-tab-name="hubs" type="button">Store Hubs</button>
                <button class="config-nav-link" data-bs-toggle="tab" data-bs-target="#tab-brands" data-tab-name="brands" type="button">Brands</button>
                <button class="config-nav-link" data-bs-toggle="tab" data-bs-target="#tab-retail" data-tab-name="retail" type="button">Retail Dept & Group</button>
                <button class="config-nav-link" data-bs-toggle="tab" data-bs-target="#tab-units" data-tab-name="units" type="button">Unit Types</button>
            </div>
        </div>

        <!-- Content Area -->
        <div class="tab-content" id="configTabContent">
            
            <!-- Tab 1: Store Hubs -->
            <div class="tab-pane fade show active" id="tab-hubs" role="tabpanel">
                <div class="card shadow-sm border-0 p-4 d-flex flex-column" style="min-height: 480px;">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold text-dark mb-0">Active Store Hubs</h5>
                            @if(auth()->user()->role === 'admin')
                                <button type="button" class="btn btn-primary btn-sm" onclick="openRegisterHubModal()">
                                    <i class="fas fa-plus me-1"></i> Register New Hub
                                </button>
                            @endif
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr class="text-muted text-uppercase" style="border-bottom: 2px solid #f1f5f9;">
                                        <th class="py-2.5">Hub Code</th>
                                        <th>Store Hub Name</th>
                                        <th class="text-center">Operational Status</th>
                                        <th class="text-center" style="width: 120px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $hubsCollection = $hubs instanceof \Illuminate\Pagination\LengthAwarePaginator || method_exists($hubs, 'items') ? collect($hubs->items()) : collect($hubs);
                                        $sortedHubs = $hubsCollection->sortBy('code');
                                    @endphp
                                    @forelse($sortedHubs as $hub)
                                        <tr style="border-bottom: 1px solid #f1f5f9;">
                                            <td class="py-3 fw-bold text-dark text-uppercase font-monospace" style="font-size: 13px;">
                                                {{ $hub->code }}
                                            </td>
                                            <td class="py-3 fw-bold text-dark text-uppercase" style="font-size: 13px;">
                                                <i class="fa-solid fa-store text-primary opacity-50 me-2" style="font-size: 12px;"></i>{{ $hub->name }}
                                            </td>
                                            <td class="text-center">
                                                @if($hub->status === 'active')
                                                    <span class="badge bg-success-subtle text-success px-2.5 py-1 rounded-pill" style="font-size: 11px; font-weight: 600;">Active</span>
                                                @else
                                                    <span class="badge bg-warning-subtle text-warning px-2.5 py-1 rounded-pill" style="font-size: 11px; font-weight: 600;">Inactive</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center align-items-center gap-1">
                                                    @if(auth()->user()->role === 'admin')
                                                        <button class="btn btn-sm btn-light text-primary" data-bs-toggle="modal" data-bs-target="#editHubModal" onclick="populateEditModal(this)" data-id="{{ $hub->id }}" data-name="{{ $hub->name }}" data-code="{{ $hub->code }}" data-headoffice="{{ $hub->is_head_office }}">
                                                            <i class="fa-solid fa-pen-to-square"></i>
                                                        </button>
                                                        <form action="{{ route('storehub.toggle-status', $hub->id) }}?tab=hubs" method="POST" class="m-0">
                                                            @csrf @method('PATCH')
                                                            <button type="submit" class="btn btn-sm btn-light {{ $hub->status === 'active' ? 'text-danger' : 'text-success' }}">
                                                                <i class="fa-solid {{ $hub->status === 'active' ? 'fa-ban' : 'fa-circle-check' }}"></i>
                                                            </button>
                                                        </form>
                                                        <button type="button" class="btn btn-sm btn-light text-danger border-0" 
                                                            onclick="openDeleteModal('{{ route('storehub.destroy', $hub->id) }}', 'hubs')">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4 small">No store hubs found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Pagination Footer & Links -->
                    <div class="mt-auto pt-4 d-flex justify-content-between align-items-center border-top">
                        <div class="text-muted small">
                            @if(method_exists($hubs, 'firstItem') && $hubs->firstItem())
                                Showing {{ $hubs->firstItem() }} to {{ $hubs->lastItem() }} of {{ $hubs->total() }} results
                            @else
                                Showing 1 to {{ count($sortedHubs) }} of {{ count($sortedHubs) }} results
                            @endif
                        </div>
                        <div>
                            @if(method_exists($hubs, 'appends'))
                                {{ $hubs->appends(['tab' => 'hubs'])->onEachSide(1)->links() }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Brands -->
            <div class="tab-pane fade" id="tab-brands" role="tabpanel">
                <div class="card shadow-sm border-0 p-4 d-flex flex-column" style="min-height: 480px;">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold text-dark mb-0">Brands</h5>
                            <button class="btn btn-sm btn-primary px-3" data-bs-toggle="modal" data-bs-target="#addBrandModal">
                                <i class="fa-solid fa-plus me-1"></i> Add Brand Name
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr class="text-muted text-uppercase" style="border-bottom: 2px solid #f1f5f9;">
                                        <th class="py-2.5">Brand Name</th>
                                        <th class="text-center" style="width: 120px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $brandsCollection = $brands instanceof \Illuminate\Pagination\LengthAwarePaginator || method_exists($brands, 'items') ? collect($brands->items()) : collect($brands);
                                        $sortedBrands = $brandsCollection->sortBy(function($brand) {
                                            return strtolower(is_object($brand) ? ($brand->brand_name ?? '') : $brand);
                                        });
                                    @endphp
                                    @forelse($sortedBrands as $brand)
                                        <tr style="border-bottom: 1px solid #f1f5f9;">
                                            <td class="py-3 fw-bold text-dark text-uppercase" style="font-size: 13px;">
                                                <i class="fa-solid fa-tag text-primary opacity-50 me-2" style="font-size: 12px;"></i>
                                                {{ is_object($brand) ? $brand->brand_name : $brand }}
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-light text-danger" onclick="openDeleteModal('{{ route('brands.destroy', $brand->id) }}', 'brands')">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="2" class="text-center text-muted py-4 small">No brands registered yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Pagination Links & Info Section -->
                    <div class="mt-auto pt-4 d-flex justify-content-between align-items-center border-top">
                        <div class="text-muted small">
                            @if(method_exists($brands, 'firstItem') && $brands->firstItem())
                                Showing {{ $brands->firstItem() }} to {{ $brands->lastItem() }} of {{ $brands->total() }} results
                            @else
                                Showing 1 to {{ count($sortedBrands) }} of {{ count($sortedBrands) }} results
                            @endif
                        </div>
                        <div>
                            @if(method_exists($brands, 'appends'))
                                {{ $brands->appends(['tab' => 'brands'])->onEachSide(1)->links() }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Retail Dept & Group -->
            <div class="tab-pane fade" id="tab-retail" role="tabpanel">
                <div class="row g-4">
                    <!-- Departments Section -->
                    <div class="col-md-6 d-flex">
                        <div class="card shadow-sm border-0 p-4 w-100 d-flex flex-column justify-content-between" style="min-height: 480px;">
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h5 class="fw-bold text-dark mb-0">Departments</h5>
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDeptModal">
                                        <i class="fas fa-plus me-1"></i> Add
                                    </button>
                                </div>
                                <ul class="list-group mb-3">
                                    @php
                                        $deptCollection = $departments instanceof \Illuminate\Pagination\LengthAwarePaginator || method_exists($departments, 'items') ? collect($departments->items()) : collect($departments);
                                        
                                        /* Sort departments alphabetically by name */
                                        $sortedDepartments = $deptCollection->sortBy(function($dept) {
                                            $name = $dept->dept_name ?? $dept->name ?? $dept->department_name ?? $dept->title ?? '';
                                            return strtolower(trim($name));
                                        });
                                    @endphp
                                    
                                    @forelse($sortedDepartments as $dept)
                                        @php
                                            $deptName = $dept->dept_name ?? $dept->name ?? $dept->department_name ?? $dept->title ?? 'No Name Found';
                                        @endphp
                                        <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                            <span class="fw-semibold text-dark">{{ $deptName }}</span>
                                            <button type="button" class="btn btn-danger btn-sm px-3" onclick="openDeleteModal('{{ route('department.destroy', $dept->id) }}', 'retail')">Remove</button>
                                        </li>
                                    @empty
                                        <li class="list-group-item text-center text-muted py-4 small">No departments found.</li>
                                    @endforelse
                                </ul>
                            </div>
                            <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                                <div class="text-muted small">
                                    @if(method_exists($departments, 'firstItem') && $departments->firstItem())
                                        Showing {{ $departments->firstItem() }} to {{ $departments->lastItem() }} of {{ $departments->total() }} results
                                    @else
                                        Showing 1 to {{ count($sortedDepartments) }} of {{ count($sortedDepartments) }} results
                                    @endif
                                </div>
                                <div class="pagination-wrapper">
                                    @if(method_exists($departments, 'appends'))
                                        {{ $departments->appends(['tab' => 'retail'])->onEachSide(0)->links() }}
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Groups Section -->
                    <div class="col-md-6 d-flex">
                        <div class="card shadow-sm border-0 p-4 w-100 d-flex flex-column justify-content-between" style="min-height: 480px;">
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h5 class="fw-bold text-dark mb-0">Groups</h5>
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addGroupModal">
                                        <i class="fas fa-plus me-1"></i> Add
                                    </button>
                                </div>
                                <ul class="list-group mb-3">
                                    @php
                                        $groupCollection = $groups instanceof \Illuminate\Pagination\LengthAwarePaginator || method_exists($groups, 'items') ? collect($groups->items()) : collect($groups);
                                        
                                        /* Sort groups alphabetically by name */
                                        $sortedGroups = $groupCollection->sortBy(function($group) {
                                            $name = is_object($group) ? ($group->name ?? $group->group_name ?? '') : $group;
                                            return strtolower(trim($name));
                                        });
                                    @endphp
                                    
                                    @forelse($sortedGroups as $group)
                                        @php
                                            $groupName = is_object($group) ? ($group->name ?? $group->group_name ?? 'No Name Found') : $group;
                                        @endphp
                                        <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                            <span class="fw-semibold text-dark">{{ $groupName }}</span>
                                            <button type="button" class="btn btn-danger btn-sm px-3" onclick="openDeleteModal('{{ route('group.destroy', $group->id) }}', 'retail')">Remove</button>
                                        </li>
                                    @empty
                                        <li class="list-group-item text-center text-muted py-4 small">No groups found.</li>
                                    @endforelse
                                </ul>
                            </div>
                            <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                                <div class="text-muted small">
                                    @if(method_exists($groups, 'firstItem') && $groups->firstItem())
                                        Showing {{ $groups->firstItem() }} to {{ $groups->lastItem() }} of {{ $groups->total() }} results
                                    @else
                                        Showing 1 to {{ count($sortedGroups) }} of {{ count($sortedGroups) }} results
                                    @endif
                                </div>
                                <div class="pagination-wrapper">
                                    @if(method_exists($groups, 'appends'))
                                        {{ $groups->appends(['tab' => 'retail'])->onEachSide(0)->links() }}
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 4: Unit Types -->
            <div class="tab-pane fade" id="tab-units" role="tabpanel">
                <div class="card shadow-sm border-0 p-4 d-flex flex-column" style="min-height: 480px;">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold text-dark mb-0">Unit Type Registry</h5>
                            <button class="btn btn-sm btn-primary px-3" type="button" data-bs-toggle="modal" data-bs-target="#addUnitModal">
                                <i class="fa-solid fa-plus me-1"></i> Add Unit
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr class="text-muted text-uppercase" style="border-bottom: 2px solid #f1f5f9;">
                                        <th>Abbreviation</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $unitsCollection = $unitTypes instanceof \Illuminate\Pagination\LengthAwarePaginator || method_exists($unitTypes, 'items') ? collect($unitTypes->items()) : collect($unitTypes);
                                        $sortedUnits = $unitsCollection->sortBy(function($unit) {
                                            return strtolower(is_object($unit) ? ($unit->abbreviation ?? '') : $unit);
                                        });
                                    @endphp
                                    @foreach($sortedUnits as $unit)
                                        <tr>
                                            <td class="fw-bold text-dark">{{ $unit->abbreviation }}</td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-light text-danger border-0" onclick="openDeleteModal('{{ route('unittypes.destroy', $unit->id) }}', 'units')">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Pagination Footer & Links -->
                    <div class="mt-auto pt-4 d-flex justify-content-between align-items-center border-top">
                        <div class="text-muted small">
                            @if(method_exists($unitTypes, 'firstItem') && $unitTypes->firstItem())
                                Showing {{ $unitTypes->firstItem() }} to {{ $unitTypes->lastItem() }} of {{ $unitTypes->total() }} results
                            @else
                                Showing 1 to {{ count($sortedUnits) }} of {{ count($sortedUnits) }} results
                            @endif
                        </div>
                        <div>
                            @if(method_exists($unitTypes, 'appends'))
                                {{ $unitTypes->appends(['tab' => 'units'])->onEachSide(1)->links() }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4 p-3">
                <form id="deleteForm" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <div class="modal-body text-center py-4">
                        <div class="text-danger mb-3">
                            <i class="fa-solid fa-triangle-exclamation fa-3x"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-2">Confirm Deletion</h4>
                        <p class="text-muted small mb-4">Are you sure you want to delete this item? This action cannot be undone.</p>
                        <div class="d-flex justify-content-center gap-2">
                            <button type="button" class="btn btn-sm btn-secondary px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-danger px-4 rounded-pill">Delete</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Register New Store Hub Modal -->
    <div class="modal fade" id="registerHubModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('storehub.storeHub') }}?tab=hubs" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Register New Store Hub</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Store Hub Name</label>
                            <input type="text" name="name" class="form-control rounded-3 @error('name') is-invalid @enderror" value="{{ old('name') }}" required style="text-transform: uppercase;">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Hub Code Reference</label>
                            <input type="text" name="code" class="form-control rounded-3 @error('code') is-invalid @enderror" value="{{ old('code') }}" required style="text-transform: uppercase;">
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="is_head_office" class="form-check-input" id="headOfficeCheck" value="1">
                            <label class="form-check-label" for="headOfficeCheck">Enable Multi-Channel Operations (Head Office Mode)</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Register Hub</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Hub Modal -->
    <div class="modal fade" id="editHubModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="fw-bold text-dark mb-0">Modify Hub Specifications</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editHubForm" method="POST" action="">
                    @csrf @method('PUT')
                    <div class="modal-body py-4">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Store Hub Name</label>
                            <input type="text" class="form-control rounded-3" id="edit_name" name="name" required style="text-transform: uppercase;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Hub Code Reference</label>
                            <input type="text" class="form-control rounded-3 font-monospace" id="edit_code" name="code" required style="text-transform: uppercase;">
                        </div>
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" name="is_head_office" id="isHeadOfficeCheck" value="1">
                            <label class="form-check-label fw-semibold" for="isHeadOfficeCheck">Enable Multi-Channel Operations (Head Office Mode)</label>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-primary px-4">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Brand Modal -->
    <div class="modal fade" id="addBrandModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="fw-bold text-dark mb-0">Add Brand</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ (Route::has('brands.store') ? route('brands.store') : url('/brands')) }}?tab=brands" method="POST">
                    @csrf
                    <div class="modal-body py-4">
                        <div class="mb-0">
                            <label class="form-label small fw-semibold text-secondary">Brand Name</label>
                            <input type="text" class="form-control rounded-3 @error('brand_name') is-invalid @enderror" name="brand_name" value="{{ old('brand_name') }}" placeholder="E.G. GOLDENPEARL" required style="text-transform: uppercase;">
                            @error('brand_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-primary px-4">Save Brand</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Department Modal -->
    <div class="modal fade" id="addDeptModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header">
                    <h5 class="fw-bold mb-0">Register New Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('retail.storeDept') }}?tab=retail" method="POST">
                    @csrf
                    <div class="modal-body">
                        <input type="text" class="form-control" name="dept_name" placeholder="Department Name" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save Department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Group Modal -->
    <div class="modal fade" id="addGroupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header">
                    <h5 class="fw-bold mb-0">Register New Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('retail.storeGroup') }}?tab=retail" method="POST">
                    @csrf
                    <div class="modal-body">
                        <input type="text" class="form-control" name="group_name" placeholder="Group Name" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm">Save Group</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Unit Type Modal -->
    <div class="modal fade" id="addUnitModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="fw-bold text-dark mb-0">Add Unit Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('unittypes.store', ['tab' => 'units']) }}" method="POST">
                    @csrf
                    <div class="modal-body py-4">
                        <div class="mb-0">
                            <label class="form-label small fw-semibold text-secondary">Abbreviation</label>
                            <input type="text" class="form-control rounded-3 @error('abbreviation') is-invalid @enderror" name="abbreviation" value="{{ old('abbreviation') }}" placeholder="E.G. PCS" required style="text-transform: uppercase;">
                            @error('abbreviation')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-primary px-4">Save Unit Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openRegisterHubModal() {
            const modalEl = document.getElementById('registerHubModal');
            if (modalEl) {
                const modalInstance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                modalInstance.show();
            }
        }

        function populateEditModal(button) {
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            const code = button.getAttribute('data-code');
            const isHeadOffice = button.getAttribute('data-headoffice');

            const editForm = document.getElementById('editHubForm');
            if (editForm) editForm.action = `/storehub/${id}?tab=hubs`;
            
            const nameInput = document.getElementById('edit_name');
            const codeInput = document.getElementById('edit_code');
            const switchBox = document.getElementById('isHeadOfficeCheck');
            
            if (nameInput) nameInput.value = name;
            if (codeInput) codeInput.value = code;
            
            if (switchBox) {
                switchBox.checked = (isHeadOffice == '1' || isHeadOffice === 'true' || isHeadOffice === 'on');
            }
        }

        function openDeleteModal(url, tab = '') {
            const deleteForm = document.getElementById('deleteForm');
            const modalElement = document.getElementById('deleteConfirmModal');
            
            if (deleteForm && modalElement) {
                deleteForm.action = tab ? `${url}?tab=${tab}` : url;
                const deleteModal = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
                deleteModal.show();
            }
        }

        document.addEventListener("DOMContentLoaded", function () {
            const toastEl = document.getElementById('liveToast');
            if (toastEl) {
                const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
                toast.show();
            }

            @if(session('error'))
                const errorModalEl = document.getElementById('errorModal');
                if (errorModalEl) {
                    setTimeout(() => {
                        const errorModal = bootstrap.Modal.getInstance(errorModalEl) || new bootstrap.Modal(errorModalEl);
                        errorModal.show();
                    }, 100);
                }
            @endif

            @if ($errors->has('name') || $errors->has('code'))
                const registerModalEl = document.getElementById('registerHubModal');
                if (registerModalEl) {
                    setTimeout(() => {
                        const registerModal = bootstrap.Modal.getInstance(registerModalEl) || new bootstrap.Modal(registerModalEl);
                        registerModal.show();
                    }, 100);
                }
            @endif

            @if ($errors->has('brand_name'))
                const brandModalEl = document.getElementById('addBrandModal');
                if (brandModalEl) {
                    setTimeout(() => {
                        const brandModal = bootstrap.Modal.getInstance(brandModalEl) || new bootstrap.Modal(brandModalEl);
                        brandModal.show();
                    }, 100);
                }
            @endif

            @if ($errors->has('abbreviation'))
                const unitModalEl = document.getElementById('addUnitModal');
                if (unitModalEl) {
                    setTimeout(() => {
                        const unitModal = bootstrap.Modal.getInstance(unitModalEl) || new bootstrap.Modal(unitModalEl);
                        unitModal.show();
                    }, 100);
                }
            @endif

            const tabButtons = document.querySelectorAll('#configTab button[data-bs-toggle="tab"]');
            tabButtons.forEach(button => {
                button.addEventListener('shown.bs.tab', function (event) {
                    const tabName = event.target.getAttribute('data-tab-name');
                    const url = new URL(window.location);
                    url.searchParams.set('tab', tabName);
                    window.history.replaceState({}, '', url);
                });
            });

            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            
            if (tabParam) {
                let targetSelector = `[data-tab-name="${tabParam}"]`;
                const tabButton = document.querySelector(targetSelector);
                if (tabButton) {
                    const tabInstance = bootstrap.Tab.getInstance(tabButton) || new bootstrap.Tab(tabButton);
                    tabInstance.show();
                }
            }
            else if (urlParams.has('dept_page') || urlParams.has('group_page')) {
                const retailTabButton = document.querySelector('[data-tab-name="retail"]');
                if (retailTabButton) (bootstrap.Tab.getInstance(retailTabButton) || new bootstrap.Tab(retailTabButton)).show();
            }
            else if (urlParams.has('brand_page')) {
                const brandTabButton = document.querySelector('[data-tab-name="brands"]');
                if (brandTabButton) (bootstrap.Tab.getInstance(brandTabButton) || new bootstrap.Tab(brandTabButton)).show();
            }
            else if (urlParams.has('unit_page')) {
                const unitTabButton = document.querySelector('[data-tab-name="units"]');
                if (unitTabButton) (bootstrap.Tab.getInstance(unitTabButton) || new bootstrap.Tab(unitTabButton)).show();
            }
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>