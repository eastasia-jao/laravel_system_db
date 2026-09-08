@extends('layouts.app')

@section('content')
    <style>
        .users-page { max-width: 1500px; }
        .users-hero { background: linear-gradient(135deg, #1d4ed8, #2563eb); color: #fff; border-radius: 18px; }
        .users-card { border: 0; border-radius: 16px; box-shadow: 0 8px 24px rgba(15, 23, 42, .07); }
        .users-table thead th { color: #64748b; font-size: .72rem; letter-spacing: .06em; text-transform: uppercase; white-space: nowrap; }
        .users-table tbody tr { border-color: #eef2f7; }
        .avatar { width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center; border-radius: 12px; background: #dbeafe; color: #1d4ed8; font-weight: 700; }
        .role-badge { background: #eff6ff; color: #1d4ed8; }
        .channel-panel { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; }
        .channel-option { border: 1px solid #e2e8f0; border-radius: 10px; padding: .55rem .75rem; background: #fff; }
        .channel-option:has(input:checked) { border-color: #2563eb; background: #eff6ff; }
    </style>
    <div class="container-fluid px-4 py-4 users-page">
        <div class="users-hero p-4 p-lg-5 mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="text-uppercase small opacity-75 fw-semibold mb-2">Administration</div>
                <h3 class="fw-bold mb-1">Staff Directory</h3>
                <p class="mb-0 opacity-75">Manage accounts, branch access, roles, and sales channels.</p>
            </div>
            <button class="btn btn-light text-primary fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#registerStaffModal">
                <i class="bi bi-plus-lg me-1"></i> Register New Staff
            </button>
        </div>

        @if (session('success'))
            <div class="alert alert-{{ session('status_color') ?? 'primary' }} alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="card users-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Name</th>
                                <th>Contact</th>
                                <th>Role</th>
                                <th>Branch</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $user)
                            <tr>
                                <td class="ps-4"><div class="d-flex align-items-center gap-2"><span class="avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</span><div><div class="fw-semibold">{{ $user->name }}</div><small class="text-muted">{{ $user->employee_id }}</small></div></div></td>
                                <td><div>{{ $user->email }}</div><small class="text-muted">{{ $user->mobile_number ?: 'No mobile number' }}</small></td>
                                <td><span class="badge rounded-pill role-badge px-3 py-2">{{ ucwords(str_replace('_', ' ', $user->role)) }}</span></td>
                                <td>{{ $user->storeHub?->name ?: 'All store hubs' }}</td>
                                <td><span class="badge rounded-pill {{ $user->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} px-3 py-2">{{ ucfirst($user->status) }}</span></td>
                                
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary border-0" onclick="editUser({{ $user->id }})">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <form action="{{ route('users.toggleStatus', $user->id) }}" method="POST">
                                            @csrf @method('PUT')
                                            <button type="submit" class="btn btn-sm {{ $user->status == 'active' ? 'btn-outline-warning' : 'btn-outline-success' }} border-0">
                                                <i class="bi {{ $user->status == 'active' ? 'bi-slash-circle' : 'bi-check-circle' }}"></i>
                                            </button>
                                        </form>
                                        <form action="{{ route('users.destroy', $user->id) }}" method="POST" id="delete-form-{{ $user->id }}">
                                            @csrf @method('DELETE')
                                            <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="confirmDelete('{{ $user->id }}')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="registerStaffModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form action="{{ route('users.store') }}" method="POST" class="modal-content">
                @csrf
                <div class="modal-header border-bottom-0 p-4">
                    <h5 class="modal-title fw-bold">Register New Staff</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4">
                    <div class="row g-3">
                        <div class="col-12"><label class="small text-muted">Employee ID</label><input type="text" name="employee_id" class="form-control" value="{{ old('employee_id') }}" maxlength="10" required></div>
                        <div class="col-md-6"><label class="small text-muted">First Name</label><input type="text" name="first_name" class="form-control" value="{{ old('first_name') }}" required></div>
                        <div class="col-md-6"><label class="small text-muted">Last Name</label><input type="text" name="last_name" class="form-control" value="{{ old('last_name') }}" required></div>
                        <div class="col-12"><label class="small text-muted">Email Address</label><input type="email" name="email" class="form-control" value="{{ old('email') }}" required></div>
                        <div class="col-md-6"><label class="small text-muted">Mobile Number</label><input type="tel" name="mobile_number" class="form-control" value="{{ old('mobile_number') }}" maxlength="11" required></div>
                        <div class="col-md-6"><label class="small text-muted">Assign Branch</label>
                            <select name="hub_id" class="form-select" required>
                                <option value="" disabled selected>Select Branch</option>
                                @foreach($hubs as $hub)<option value="{{ $hub->id }}" @selected(old('hub_id') == $hub->id)>{{ $hub->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-6"><label class="small text-muted">Role</label>
                            <select name="role" id="create_role" class="form-select role-selector" required>
                                <option value="admin" @selected(old('role') === 'admin')>Admin</option>
                                <option value="inventory_staff" @selected(old('role') === 'inventory_staff')>Inventory Staff</option>
                                <option value="sales_associate" @selected(old('role') === 'sales_associate')>Sales Associate</option>
                                <option value="sales_marketing_staff" @selected(old('role') === 'sales_marketing_staff')>Sales/Marketing Staff</option>
                            </select>
                        </div>
                        <div class="col-12 channel-panel p-3" id="create_channels_panel" style="display:none;">
                            <label class="small text-muted fw-semibold">Sales Channels <span class="fw-normal">(Sales/Marketing Staff only)</span></label>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                @foreach(['shopee' => 'Shopee', 'lazada' => 'Lazada', 'online' => 'Online Orders', 'wholesale' => 'Wholesale', 'tiktok' => 'TikTok'] as $value => $label)
                                    <label class="channel-option"><input class="form-check-input create-channel me-1" type="checkbox" name="sales_channels[]" value="{{ $value }}" @checked(in_array($value, old('sales_channels', []), true))> {{ $label }}</label>
                                @endforeach
                            </div>
                            <small class="text-muted d-block mt-2">Select the channels this staff member is allowed to manage.</small>
                        </div>
                        <div class="col-md-6"><label class="small text-muted">Password</label><input type="password" name="password" id="password" class="form-control" required></div>
                        <div class="col-md-6"><label class="small text-muted">Confirm Password</label><input type="password" name="password_confirmation" class="form-control" required></div>
                    </div>
                    @if($errors->any() && old('first_name'))
                        <div class="alert alert-danger mt-4 mb-0">
                            <div class="fw-semibold mb-1">Staff registration could not be completed.</div>
                            <ul class="mb-0 ps-3">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary px-4">Save Staff</button></div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="editStaffModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form id="editStaffForm" method="POST" class="modal-content">
                @csrf 
                @method('PUT')
                <div class="modal-header border-bottom-0 p-4">
                    <h5 class="modal-title fw-bold">Edit Staff</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4">
                    <div id="editErrorAlert" class="alert alert-danger" style="display: none;"></div>
                    
                    <input type="text" name="name" id="edit_name" class="form-control mb-3" placeholder="Name" required>
                    <input type="email" name="email" id="edit_email" class="form-control mb-3" placeholder="Email" required>
                    <input type="text" name="mobile_number" id="edit_mobile" class="form-control mb-3" placeholder="Mobile">
                    
                    <select name="hub_id" id="edit_hub" class="form-select mb-3">
                        @foreach($hubs as $hub)
                            <option value="{{ $hub->id }}">{{ $hub->name }}</option>
                        @endforeach
                    </select>

                    <select name="role" id="edit_role" class="form-select mb-3 role-selector">
                        <option value="admin">Admin</option>
                        <option value="inventory_staff">Inventory Staff</option>
                        <option value="sales_associate">Sales Associate</option>
                        <option value="sales_marketing_staff">Sales/Marketing Staff</option>
                    </select>
                    <div class="channel-panel p-3 mb-3" id="edit_channels_panel" style="display:none;">
                    <label class="small text-muted fw-semibold">Sales Channels <span class="fw-normal">(Sales/Marketing Staff only)</span></label>
                    <div class="d-flex flex-wrap gap-2 mt-2" id="edit_channels">
                        @foreach(['shopee' => 'Shopee', 'lazada' => 'Lazada', 'online' => 'Online Orders', 'wholesale' => 'Wholesale', 'tiktok' => 'TikTok'] as $value => $label)
                            <label class="channel-option"><input class="form-check-input edit-channel me-1" type="checkbox" name="sales_channels[]" value="{{ $value }}"> {{ $label }}</label>
                        @endforeach
                    </div>
                    <small class="text-muted d-block mt-2">Select the channels this staff member is allowed to manage.</small>
                    </div>
                    <hr>
                    <input type="password" name="current_password" class="form-control mb-3" placeholder="Current Password (Required to change details)">
                    <hr>
                    <input type="password" name="password" class="form-control mb-1" placeholder="New Password">
                    <input type="password" name="password_confirmation" class="form-control mb-2" placeholder="Confirm Password">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Update</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        @if($errors->any() && old('first_name'))
            new bootstrap.Modal(document.getElementById('registerStaffModal')).show();
            toggleChannelPanel(document.getElementById('create_role'), document.getElementById('create_channels_panel'));
        @endif

        function confirmDelete(userId) {
            Swal.fire({ title: 'Are you sure?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Yes, Delete' })
                .then((result) => { if (result.isConfirmed) document.getElementById('delete-form-' + userId).submit(); });
        }

        // Unified Edit Function
        function editUser(id) {
            fetch(`/users/${id}/edit`)
                .then(res => res.json())
                .then(data => {
                    document.getElementById('edit_name').value = data.user.name;
                    document.getElementById('edit_email').value = data.user.email;
                    document.getElementById('edit_mobile').value = data.user.mobile_number || '';
                    document.getElementById('edit_hub').value = data.user.hub_id;
                    document.getElementById('edit_role').value = data.user.role;
                    document.querySelectorAll('.edit-channel').forEach((checkbox) => {
                        checkbox.checked = (data.user.sales_channels || []).includes(checkbox.value);
                    });
                    toggleChannelPanel(document.getElementById('edit_role'), document.getElementById('edit_channels_panel'));
                    document.getElementById('editStaffForm').action = `/users/${id}`;
                    document.getElementById('editErrorAlert').style.display = 'none'; // Hide old errors
                    new bootstrap.Modal(document.getElementById('editStaffModal')).show();
                });

        }

        function toggleChannelPanel(roleSelect, panel) {
            if (!roleSelect || !panel) return;
            const visible = roleSelect.value === 'sales_marketing_staff';
            panel.style.display = visible ? 'block' : 'none';
            if (!visible) {
                panel.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
                    checkbox.checked = false;
                });
            }
        }

        document.querySelectorAll('.role-selector').forEach((select) => {
            select.addEventListener('change', function () {
                const panel = this.id === 'create_role'
                    ? document.getElementById('create_channels_panel')
                    : document.getElementById('edit_channels_panel');
                toggleChannelPanel(this, panel);
            });
        });

        // Unified Submit Function
        document.getElementById('editStaffForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            const errorAlert = document.getElementById('editErrorAlert');

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => {
                if (response.ok) {
                    window.location.reload();
                } else if (response.status === 422) {
                    return response.json();
                }
            })
            .then(data => {
                if (data && data.errors) {
                    errorAlert.style.display = 'block';
                    errorAlert.innerHTML = Object.values(data.errors).flat().join('<br>');
                }
            });
        });
    </script>
@endsection