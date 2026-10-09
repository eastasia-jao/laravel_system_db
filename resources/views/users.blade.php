@extends('layouts.app')

@section('content')
    <style>
        .users-page { max-width: 1500px; }
        .users-card { border: 0; border-radius: 16px; box-shadow: 0 8px 24px rgba(15, 23, 42, .07); }
        .users-table thead th { color: #64748b; font-size: .72rem; letter-spacing: .06em; text-transform: uppercase; white-space: nowrap; }
        .users-table tbody tr { border-color: #eef2f7; }
        .role-badge { background: #eff6ff; color: #1d4ed8; }
        .channel-panel { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; }
        .channel-option { border: 1px solid #e2e8f0; border-radius: 8px; padding: .42rem .35rem; background: #fff; display: flex; align-items: center; justify-content: center; gap: .3rem; font-size: .76rem; white-space: nowrap; cursor: pointer; transition: border-color .15s ease, background-color .15s ease; }
        .channel-option:has(input:checked) { border-color: #2563eb; background: #eff6ff; color: #1d4ed8; font-weight: 600; }
        .channel-option input { margin: 0; }
        .channel-options-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: .4rem; }
        .edit-staff-modal { border: 0; border-radius: 18px; overflow: hidden; box-shadow: 0 24px 70px rgba(15, 23, 42, .24); }
        .edit-staff-header { background: linear-gradient(135deg, #1e3a8a, #2563eb); color: #fff; padding: 1.35rem 1.5rem; }
        .edit-staff-avatar { width: 48px; height: 48px; display: inline-flex; align-items: center; justify-content: center; border-radius: 14px; background: rgba(255, 255, 255, .18); border: 1px solid rgba(255, 255, 255, .3); font-size: 1.1rem; font-weight: 700; }
        .edit-section { border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; padding: 1.1rem; }
        .edit-section-title { display: flex; align-items: center; gap: .55rem; margin-bottom: 1rem; color: #0f172a; font-size: .92rem; font-weight: 700; }
        .edit-section-title i { width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; border-radius: 9px; background: #eff6ff; color: #2563eb; }
        .edit-security-section { background: #f8fafc; }
        .edit-security-section .edit-section-title i { background: #fef3c7; color: #b45309; }
        .edit-staff-modal .form-label { margin-bottom: .4rem; color: #334155; font-size: .8rem; font-weight: 600; }
        .edit-staff-modal .form-control, .edit-staff-modal .form-select { min-height: 44px; border-color: #dbe2ea; border-radius: 10px; }
        .edit-staff-modal .form-control:focus, .edit-staff-modal .form-select:focus { border-color: #3b82f6; box-shadow: 0 0 0 .2rem rgba(59, 130, 246, .12); }
        .staff-form-modal { border: 0; border-radius: 18px; overflow: hidden; box-shadow: 0 24px 70px rgba(15, 23, 42, .24); }
        .register-staff-header { background: linear-gradient(135deg, #064e3b, #059669); color: #fff; padding: 1.35rem 1.5rem; }
        .register-staff-icon { width: 48px; height: 48px; display: inline-flex; align-items: center; justify-content: center; border-radius: 14px; background: rgba(255, 255, 255, .18); border: 1px solid rgba(255, 255, 255, .3); font-size: 1.25rem; }
        .staff-form-modal .form-label { margin-bottom: .4rem; color: #334155; font-size: .8rem; font-weight: 600; }
        .staff-form-modal .form-control, .staff-form-modal .form-select { min-height: 44px; border-color: #dbe2ea; border-radius: 10px; }
        .staff-form-modal .form-control:focus, .staff-form-modal .form-select:focus { border-color: #10b981; box-shadow: 0 0 0 .2rem rgba(16, 185, 129, .12); }
        #create_employee_id, #edit_employee_id { text-transform: uppercase; }
        .create-assignment-panel { padding: 1rem; border: 1px solid #dbe5f1; border-radius: 14px; background: linear-gradient(145deg, #f8fbff, #f3f7fc); }
        .create-assignment-heading { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem; margin-bottom: .35rem; }
        .create-assignment-heading label { margin: 0; color: #0f172a; font-size: .84rem; font-weight: 700; }
        .create-assignment-count { padding: .2rem .55rem; border-radius: 999px; background: #e0f2fe; color: #0369a1; font-size: .72rem; font-weight: 700; }
        .create-assignment-help { margin: 0 0 .85rem; color: #64748b; font-size: .77rem; line-height: 1.45; }
        .create-assignment-search { position: relative; margin-bottom: .65rem; }
        .create-assignment-search i { position: absolute; top: 50%; left: .8rem; color: #94a3b8; transform: translateY(-50%); pointer-events: none; }
        .create-assignment-search .form-control { min-height: 39px; padding-left: 2.2rem; border-radius: 9px; background: #fff; font-size: .8rem; }
        .create-assignment-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 190px), 1fr)); gap: .55rem; max-height: 190px; overflow-y: auto; padding: .1rem .2rem .2rem .1rem; }
        .create-assignment-option { display: flex; min-width: 0; min-height: 44px; align-items: center; justify-content: flex-start; gap: .6rem; padding: .6rem .7rem; border: 1px solid #dbe4ef; border-radius: 10px; background: #fff; color: #334155; font-size: .78rem; line-height: 1.3; cursor: pointer; transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease; }
        .create-assignment-option:hover { border-color: #86b7fe; background: #f8fbff; }
        .create-assignment-option:has(input:checked) { border-color: #2563eb; background: #eff6ff; color: #1d4ed8; box-shadow: 0 0 0 2px rgba(37, 99, 235, .08); font-weight: 600; }
        .create-assignment-option:has(input:disabled) { background: #f1f5f9; color: #94a3b8; cursor: not-allowed; }
        .create-assignment-option input, .edit-assignment-option input { flex: 0 0 auto; width: 1rem; height: 1rem; margin: 0; accent-color: #2563eb; }
        .create-assignment-option span, .edit-assignment-option span { min-width: 0; overflow-wrap: anywhere; }
        .create-assignment-empty, .edit-assignment-empty { display: none; padding: .7rem; border: 1px dashed #cbd5e1; border-radius: 9px; color: #64748b; font-size: .78rem; text-align: center; }
        .edit-assignment-panel { padding: 1rem; border: 1px solid #dbe5f1; border-radius: 14px; background: linear-gradient(145deg, #f8fbff, #f3f7fc); }
        .edit-assignment-heading { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem; margin-bottom: .35rem; }
        .edit-assignment-heading label { margin: 0; color: #0f172a; font-size: .84rem; font-weight: 700; }
        .edit-assignment-count { padding: .2rem .55rem; border-radius: 999px; background: #e0f2fe; color: #0369a1; font-size: .72rem; font-weight: 700; }
        .edit-assignment-help { margin: 0 0 .85rem; color: #64748b; font-size: .77rem; line-height: 1.45; }
        .edit-assignment-search { position: relative; margin-bottom: .65rem; }
        .edit-assignment-search i { position: absolute; top: 50%; left: .8rem; color: #94a3b8; transform: translateY(-50%); pointer-events: none; }
        .edit-assignment-search .form-control { min-height: 39px; padding-left: 2.2rem; border-radius: 9px; background: #fff; font-size: .8rem; }
        .edit-assignment-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 190px), 1fr)); gap: .55rem; max-height: 190px; overflow-y: auto; padding: .1rem .2rem .2rem .1rem; }
        .edit-assignment-option { display: flex; min-width: 0; min-height: 44px; align-items: center; justify-content: flex-start; gap: .6rem; padding: .6rem .7rem; border: 1px solid #dbe4ef; border-radius: 10px; background: #fff; color: #334155; font-size: .78rem; line-height: 1.3; cursor: pointer; transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease; }
        .edit-assignment-option:hover { border-color: #86b7fe; background: #f8fbff; }
        .edit-assignment-option:has(input:checked) { border-color: #2563eb; background: #eff6ff; color: #1d4ed8; box-shadow: 0 0 0 2px rgba(37, 99, 235, .08); font-weight: 600; }
        .edit-assignment-option:has(input:disabled) { background: #f1f5f9; color: #94a3b8; cursor: not-allowed; }
        @media (max-width: 575.98px) { .create-assignment-panel { padding: .8rem; } .create-assignment-list { grid-template-columns: 1fr; max-height: 160px; } }
        @media (max-width: 575.98px) { .edit-assignment-panel { padding: .8rem; } .edit-assignment-list { grid-template-columns: 1fr; max-height: 160px; } }
    </style>
    <div class="container-fluid px-4 py-4 users-page">
        <x-page-header class="mb-4" eyebrow="Administration workspace" title="Staff Directory" description="Manage accounts, branch access, roles, and sales channels." icon="fa-users-gear">
            <x-slot:actions><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registerStaffModal"><i class="bi bi-plus-lg me-1"></i> Register New Staff</button></x-slot:actions>
        </x-page-header>

        

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
                                <td class="ps-4"><div class="fw-semibold">{{ $user->name }}</div></td>
                                <td>{{ $user->mobile_number ?: 'No mobile number' }}</td>
                                <td><span class="badge rounded-pill role-badge px-3 py-2">{{ ucwords(str_replace('_', ' ', $user->role)) }}</span></td>
                                <td>{{ $user->storeHub?->name ?: 'All store hubs' }}</td>
                                <td><span class="badge rounded-pill {{ $user->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} px-3 py-2">{{ ucfirst($user->status) }}</span></td>
                                
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary border-0" onclick="editUser({{ $user->id }})">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary border-0" onclick="resetUserPassword({{ $user->id }}, this.dataset.userName)" data-user-name="{{ $user->name }}" aria-label="Reset password for {{ $user->name }}" title="Reset password">
                                            <i class="bi bi-key"></i>
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

    <div class="modal fade" id="registerStaffModal" tabindex="-1" aria-labelledby="registerStaffModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <form action="{{ route('users.store') }}" method="POST" class="modal-content staff-form-modal">
                @csrf
                <div class="modal-header register-staff-header border-0">
                    <div>
                            <h5 class="modal-title fw-bold mb-1" id="registerStaffModalLabel">Register New Staff</h5>
                            <div class="small text-white-50">Create an account and assign its operational access.</div>
                        </div>
                    </div>
                <div class="modal-body bg-light p-3 p-md-4">
                    <div class="edit-section mb-3">
                        <div class="edit-section-title"><i class="bi bi-person-vcard"></i>Staff Information</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="create_employee_id" class="form-label">Employee ID</label>
                                <input id="create_employee_id" type="text" name="employee_id" class="form-control" value="{{ old('employee_id') }}" placeholder="e.g. AC001" maxlength="10" required autocomplete="off" autocapitalize="characters">
                            </div>
                            <div class="col-md-4">
                                <label for="create_first_name" class="form-label">First Name</label>
                                <input id="create_first_name" type="text" name="first_name" class="form-control" value="{{ old('first_name') }}" placeholder="First name" required autocomplete="given-name">
                            </div>
                            <div class="col-md-4">
                                <label for="create_last_name" class="form-label">Last Name</label>
                                <input id="create_last_name" type="text" name="last_name" class="form-control" value="{{ old('last_name') }}" placeholder="Last name" required autocomplete="family-name">
                            </div>
                            <div class="col-md-6">
                                <label for="create_mobile" class="form-label">Mobile Number</label>
                                <input id="create_mobile" type="tel" name="mobile_number" class="form-control" value="{{ old('mobile_number') }}" placeholder="09XXXXXXXXX" maxlength="11" required autocomplete="tel">
                            </div>
                            <div class="col-md-6">
                                <label for="create_email" class="form-label">Email Address <span class="text-muted fw-normal">(optional)</span></label>
                                <input id="create_email" type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="staff@company.com" autocomplete="email">
                            </div>
                        </div>
                    </div>

                    <div class="edit-section mb-3">
                        <div class="edit-section-title"><i class="bi bi-diagram-3"></i>Access &amp; Assignment</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="create_hub" class="form-label">Assigned Store Hub</label>
                                <select id="create_hub" name="hub_id" class="form-select" required>
                                    <option value="" disabled @selected(!old('hub_id'))>Select a store hub</option>
                                    @foreach($hubs as $hub)<option value="{{ $hub->id }}" data-head-office="{{ $hub->is_head_office ? 'true' : 'false' }}" @selected(old('hub_id') == $hub->id)>{{ $hub->name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="create_role" class="form-label">System Role</label>
                                <select name="role" id="create_role" class="form-select role-selector" required>
                                    <option value="" disabled @selected(!old('role'))>Select a role</option>
                                    @foreach($staffRoles as $staffRole)
                                        <option value="{{ $staffRole->slug }}" @selected(old('role') === $staffRole->slug)>{{ $staffRole->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="create-assignment-panel" id="create_additional_hubs_panel" style="display:none;">
                                    <div class="create-assignment-heading">
                                        <label for="create_branch_search">Additional Assigned Branches</label>
                                        <span class="create-assignment-count" id="create_branch_count">0 selected</span>
                                    </div>
                                    <p class="create-assignment-help">Choose any extra branches this associate can work at. Their sales channel stays Walk-In.</p>
                                    <div class="create-assignment-search">
                                        <i class="bi bi-search" aria-hidden="true"></i>
                                        <input type="search" id="create_branch_search" class="form-control" placeholder="Find a branch..." autocomplete="off" aria-label="Search additional branches">
                                    </div>
                                    <div class="create-assignment-list" id="create_additional_hub_list">
                                        @foreach($hubs->where('is_head_office', false) as $hub)
                                            <label class="create-assignment-option" data-branch-name="{{ $hub->name }}">
                                                <input class="create-additional-hub" type="checkbox" name="additional_hub_ids[]" value="{{ $hub->id }}" @checked(in_array((string) $hub->id, array_map('strval', old('additional_hub_ids', [])), true))>
                                                <span>{{ $hub->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <div class="create-assignment-empty mt-2" id="create_branch_empty">No branches match your search.</div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="channel-panel p-3" id="create_channels_panel" style="display:none;">
                                    <label class="small text-muted fw-semibold">Sales Channels <span class="fw-normal">(Inventory or Sales/Marketing Staff)</span></label>
                                    <div class="channel-options-grid mt-2">
                                        @foreach(['shopee' => 'Shopee', 'lazada' => 'Lazada', 'online' => 'Online Orders', 'walk_in' => 'Walk-In', 'wholesale' => 'Wholesale', 'tiktok' => 'TikTok', 'fully_booked' => 'Fully Booked'] as $value => $label)
                                            <label class="channel-option" @if($value === 'fully_booked') data-sales-marketing-only @endif><input class="form-check-input create-channel me-1" type="checkbox" name="sales_channels[]" value="{{ $value }}" @checked(in_array($value, old('sales_channels', []), true))> {{ $label }}</label>
                                        @endforeach
                                    </div>
                                    <small class="text-muted d-block mt-2">Select the channels this staff member can record sales for. Admin system access is unchanged.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="edit-section edit-security-section">
                        <div class="edit-section-title mb-1"><i class="bi bi-lock"></i>Account Security</div>
                        <p class="small text-muted mb-3">Use at least 8 characters with uppercase, lowercase, number, and symbol.</p>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="create_password" class="form-label">Password</label>
                                <input type="password" name="password" id="create_password" class="form-control" placeholder="Create a secure password" required autocomplete="new-password">
                            </div>
                            <div class="col-md-6">
                                <label for="create_password_confirmation" class="form-label">Confirm Password</label>
                                <input type="password" name="password_confirmation" id="create_password_confirmation" class="form-control" placeholder="Repeat the password" required autocomplete="new-password">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-white px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4"><i class="bi bi-person-check me-1"></i>Create Staff Account</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="editStaffModal" tabindex="-1" aria-labelledby="editStaffModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <form id="editStaffForm" method="POST" class="modal-content edit-staff-modal">
                @csrf 
                @method('PUT')
                <div class="modal-header edit-staff-header border-0">
                    <div>
                            <h5 class="modal-title fw-bold mb-1" id="editStaffModalLabel">Edit Staff Account</h5>
                            <div class="small text-white-50"><span id="edit_staff_display_name">Staff member</span><span id="edit_staff_meta"></span></div>
                        </div>
                    </div>
                <div class="modal-body bg-light p-3 p-md-4">
                    <div id="editErrorAlert" class="alert alert-danger" style="display: none;"></div>

                    <div class="edit-section mb-3">
                        <div class="edit-section-title"><i class="bi bi-person"></i>Profile Information</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="edit_employee_id" class="form-label">Employee ID</label>
                                <input type="text" name="employee_id" id="edit_employee_id" class="form-control" placeholder="e.g. AC001" maxlength="10" autocomplete="off" autocapitalize="characters">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_first_name" class="form-label">First Name</label>
                                <input type="text" name="first_name" id="edit_first_name" class="form-control" placeholder="First name" required autocomplete="given-name">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_last_name" class="form-label">Last Name</label>
                                <input type="text" name="last_name" id="edit_last_name" class="form-control" placeholder="Last name" required autocomplete="family-name">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_mobile" class="form-label">Mobile Number</label>
                                <input type="tel" name="mobile_number" id="edit_mobile" class="form-control" placeholder="09XXXXXXXXX" maxlength="11" required autocomplete="tel">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_email" class="form-label">Email Address <span class="text-muted fw-normal">(optional)</span></label>
                                <input type="email" name="email" id="edit_email" class="form-control" placeholder="staff@company.com" autocomplete="email">
                            </div>
                        </div>
                    </div>

                    <div class="edit-section mb-3">
                        <div class="edit-section-title"><i class="bi bi-shield-check"></i>Access &amp; Assignment</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="edit_hub" class="form-label">Assigned Store Hub</label>
                                <select name="hub_id" id="edit_hub" class="form-select" required>
                                    @foreach($hubs as $hub)
                                        <option value="{{ $hub->id }}" data-head-office="{{ $hub->is_head_office ? 'true' : 'false' }}">{{ $hub->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_role" class="form-label">System Role</label>
                                <select name="role" id="edit_role" class="form-select role-selector" required>
                                    @foreach($staffRoles as $staffRole)
                                        <option value="{{ $staffRole->slug }}">{{ $staffRole->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="edit-assignment-panel" id="edit_additional_hubs_panel" style="display:none;">
                                    <div class="edit-assignment-heading">
                                        <label for="edit_branch_search">Additional Assigned Branches</label>
                                        <span class="edit-assignment-count" id="edit_branch_count">0 selected</span>
                                    </div>
                                    <p class="edit-assignment-help">Choose any extra branches this associate can work at. Their sales channel stays Walk-In.</p>
                                    <div class="edit-assignment-search">
                                        <i class="bi bi-search" aria-hidden="true"></i>
                                        <input type="search" id="edit_branch_search" class="form-control" placeholder="Find a branch..." autocomplete="off" aria-label="Search additional branches">
                                    </div>
                                    <div class="edit-assignment-list" id="edit_additional_hub_list">
                                        @foreach($hubs->where('is_head_office', false) as $hub)
                                            <label class="edit-assignment-option" data-branch-name="{{ $hub->name }}">
                                                <input class="edit-additional-hub" type="checkbox" name="additional_hub_ids[]" value="{{ $hub->id }}">
                                                <span>{{ $hub->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <div class="edit-assignment-empty mt-2" id="edit_branch_empty">No branches match your search.</div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="channel-panel p-3" id="edit_channels_panel" style="display:none;">
                                    <label class="small text-muted fw-semibold">Sales Channels <span class="fw-normal">(Inventory or Sales/Marketing Staff)</span></label>
                                    <div class="channel-options-grid mt-2" id="edit_channels">
                                        @foreach(['shopee' => 'Shopee', 'lazada' => 'Lazada', 'online' => 'Online Orders', 'walk_in' => 'Walk-In', 'wholesale' => 'Wholesale', 'tiktok' => 'TikTok', 'fully_booked' => 'Fully Booked'] as $value => $label)
                                            <label class="channel-option" @if($value === 'fully_booked') data-sales-marketing-only @endif><input class="form-check-input edit-channel me-1" type="checkbox" name="sales_channels[]" value="{{ $value }}"> {{ $label }}</label>
                                        @endforeach
                                    </div>
                                    <small class="text-muted d-block mt-2">Select the channels this staff member can record sales for. Admin system access is unchanged.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="edit-section edit-security-section">
                        <div class="edit-section-title mb-1"><i class="bi bi-key"></i>Change Password</div>
                        <p class="small text-muted mb-3">Leave these fields blank to keep the current password.</p>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="edit_current_password" class="form-label">Current Password</label>
                                <input type="password" name="current_password" id="edit_current_password" class="form-control" placeholder="Required only when setting a new password" autocomplete="current-password">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_password" class="form-label">New Password</label>
                                <input type="password" name="password" id="edit_password" class="form-control" placeholder="At least 8 characters" autocomplete="new-password">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_password_confirmation" class="form-label">Confirm New Password</label>
                                <input type="password" name="password_confirmation" id="edit_password_confirmation" class="form-control" placeholder="Repeat the new password" autocomplete="new-password">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-white px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check2-circle me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="resetPasswordForm" method="POST" class="modal-content edit-staff-modal">
                @csrf
                <div class="modal-header edit-staff-header border-0">
                    <div>
                        <h5 class="modal-title fw-bold mb-1" id="resetPasswordModalLabel">Reset Password</h5>
                        <div class="small text-white-50">Set a new password for <span id="reset_password_user_name">staff member</span>.</div>
                    </div>
                </div>
                <div class="modal-body bg-light p-3 p-md-4">
                    <div id="resetPasswordErrorAlert" class="alert alert-danger" style="display: none;"></div>
                    <p class="small text-muted">The current password will not be shown. Use at least 8 characters with uppercase, lowercase, number, and symbol.</p>
                    <div class="mb-3">
                        <label for="reset_password" class="form-label">New Password</label>
                        <input id="reset_password" name="password" type="password" class="form-control" required autocomplete="new-password">
                    </div>
                    <div>
                        <label for="reset_password_confirmation" class="form-label">Confirm New Password</label>
                        <input id="reset_password_confirmation" name="password_confirmation" type="password" class="form-control" required autocomplete="new-password">
                    </div>
                </div>
                <div class="modal-footer border-0 bg-white px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-key me-1"></i>Reset Password</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        @if($errors->any() && old('first_name'))
        document.addEventListener('DOMContentLoaded', () => {
            new bootstrap.Modal(document.getElementById('registerStaffModal')).show();
            toggleChannelPanel(document.getElementById('create_role'), document.getElementById('create_channels_panel'));
            toggleAdditionalHubsPanel(document.getElementById('create_role'), document.getElementById('create_additional_hubs_panel'));
            syncAdditionalHubOptions('create');
        });
        @endif

        document.querySelectorAll('#create_employee_id, #edit_employee_id').forEach((input) => {
            input.addEventListener('input', () => {
                const cursor = input.selectionStart;
                input.value = input.value.toUpperCase();
                input.setSelectionRange(cursor, cursor);
            });
        });

        function confirmDelete(userId) {
            Swal.fire({ title: 'Are you sure?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Yes, Delete' })
                .then((result) => { if (result.isConfirmed) document.getElementById('delete-form-' + userId).submit(); });
        }

        // Unified Edit Function
        function editUser(id) {
            fetch(`/users/${id}/edit`)
                .then(res => res.json())
                .then(data => {
                    document.getElementById('editStaffForm').reset();
                    document.getElementById('edit_first_name').value = data.user.first_name;
                    document.getElementById('edit_last_name').value = data.user.last_name;
                    document.getElementById('edit_mobile').value = data.user.mobile_number || '';
                    document.getElementById('edit_email').value = data.user.email || '';
                    document.getElementById('edit_hub').value = data.user.hub_id;
                    document.getElementById('edit_role').value = data.user.role;
                    filterPrimaryHubOptions('edit');
                    document.getElementById('edit_staff_display_name').textContent = data.user.name;
                    document.getElementById('edit_employee_id').value = data.user.employee_id || '';
                    document.getElementById('edit_staff_meta').textContent = data.user.employee_id ? ` · Employee ID: ${data.user.employee_id.toUpperCase()}` : ' · Employee ID not set';
                    document.querySelectorAll('.edit-channel').forEach((checkbox) => {
                        checkbox.checked = (data.user.sales_channels || []).includes(checkbox.value);
                    });
                    document.querySelectorAll('.edit-additional-hub').forEach((checkbox) => {
                        checkbox.checked = (data.user.additional_hub_ids || []).map(String).includes(checkbox.value);
                    });
                    document.getElementById('edit_branch_search').value = '';
                    filterAssignmentBranches('edit', '');
                    toggleChannelPanel(document.getElementById('edit_role'), document.getElementById('edit_channels_panel'));
                    toggleAdditionalHubsPanel(document.getElementById('edit_role'), document.getElementById('edit_additional_hubs_panel'));
                    syncAdditionalHubOptions('edit');
                    document.getElementById('editStaffForm').action = `/users/${id}`;
                    document.getElementById('editErrorAlert').style.display = 'none'; // Hide old errors
                    new bootstrap.Modal(document.getElementById('editStaffModal')).show();
                });

        }

        function resetUserPassword(id, name) {
            const form = document.getElementById('resetPasswordForm');
            form.reset();
            form.action = `/users/${id}/reset-password`;
            document.getElementById('reset_password_user_name').textContent = name;
            document.getElementById('resetPasswordErrorAlert').style.display = 'none';
            new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
        }

        function toggleChannelPanel(roleSelect, panel) {
            if (!roleSelect || !panel) return;
            const visible = ['inventory_staff', 'sales_marketing_staff'].includes(roleSelect.value);
            panel.style.display = visible ? 'block' : 'none';
            panel.querySelectorAll('[data-sales-marketing-only]').forEach((option) => {
                const checkbox = option.querySelector('input[type="checkbox"]');
                const channelVisible = roleSelect.value === 'sales_marketing_staff';
                option.hidden = !channelVisible;
                checkbox.disabled = !channelVisible;
                if (!channelVisible) checkbox.checked = false;
            });
            if (!visible) {
                panel.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
                    checkbox.checked = false;
                });
            }
        }

        function toggleAdditionalHubsPanel(roleSelect, panel) {
            if (!roleSelect || !panel) return;
            const visible = roleSelect.value === 'sales_associate';
            panel.style.display = visible ? 'block' : 'none';
            if (!visible) {
                panel.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
                    checkbox.checked = false;
                });
            }
        }

        function filterPrimaryHubOptions(formType) {
            const roleSelect = document.getElementById(`${formType}_role`);
            const hubSelect = document.getElementById(`${formType}_hub`);
            if (!roleSelect || !hubSelect) return;

            const excludeHeadOffice = roleSelect.value === 'sales_associate';
            let selectedOptionAllowed = false;
            Array.from(hubSelect.options).forEach((option) => {
                if (!option.value) return;
                const hidden = excludeHeadOffice && option.dataset.headOffice === 'true';
                option.hidden = hidden;
                option.disabled = hidden;
                if (option.selected && !hidden) selectedOptionAllowed = true;
            });
            if (!selectedOptionAllowed) {
                const firstAllowedOption = Array.from(hubSelect.options).find(option =>
                    option.value && !option.disabled
                );
                hubSelect.value = firstAllowedOption?.value || '';
            }
        }

        function syncAdditionalHubOptions(formType) {
            const hubSelect = document.getElementById(`${formType}_hub`);
            const checkboxes = document.querySelectorAll(`.${formType}-additional-hub`);
            if (!hubSelect) return;
            checkboxes.forEach((checkbox) => {
                checkbox.disabled = checkbox.value === hubSelect.value;
                if (checkbox.disabled) checkbox.checked = false;
            });
            updateAssignmentCount(formType);
        }

        function updateAssignmentCount(formType) {
            const selectedCount = document.querySelectorAll(`.${formType}-additional-hub:checked`).length;
            const count = document.getElementById(`${formType}_branch_count`);
            if (count) count.textContent = `${selectedCount} selected`;
        }

        function filterAssignmentBranches(formType, search) {
            const normalizedSearch = search.trim().toLocaleLowerCase();
            let visibleCount = 0;
            document.querySelectorAll(`#${formType}_additional_hub_list .${formType}-assignment-option`).forEach((option) => {
                const matches = option.dataset.branchName.toLocaleLowerCase().includes(normalizedSearch);
                option.hidden = !matches;
                if (matches) visibleCount++;
            });
            const emptyState = document.getElementById(`${formType}_branch_empty`);
            if (emptyState) emptyState.style.display = visibleCount ? 'none' : 'block';
        }

        document.querySelectorAll('.role-selector').forEach((select) => {
            select.addEventListener('change', function () {
                const panel = this.id === 'create_role'
                    ? document.getElementById('create_channels_panel')
                    : document.getElementById('edit_channels_panel');
                toggleChannelPanel(this, panel);
                const formType = this.id === 'create_role' ? 'create' : 'edit';
                filterPrimaryHubOptions(formType);
                toggleAdditionalHubsPanel(this, document.getElementById(`${formType}_additional_hubs_panel`));
                syncAdditionalHubOptions(formType);
            });

            document.querySelectorAll('#create_first_name, #create_last_name, #edit_first_name, #edit_last_name').forEach((input) => {
                input.addEventListener('input', function () {
                    this.value = this.value
                        .toLowerCase()
                        .replace(/\b\w/g, character => character.toUpperCase());
                });
            });
        });

        ['create', 'edit'].forEach((formType) => {
            filterPrimaryHubOptions(formType);
            document.getElementById(`${formType}_role`)?.addEventListener('change', () => filterPrimaryHubOptions(formType));
            document.getElementById(`${formType}_hub`)?.addEventListener('change', () => syncAdditionalHubOptions(formType));
            syncAdditionalHubOptions(formType);
        });

        document.querySelectorAll('.create-additional-hub').forEach((checkbox) => {
            checkbox.addEventListener('change', () => updateAssignmentCount('create'));
        });
        document.getElementById('create_branch_search')?.addEventListener('input', function () {
            filterAssignmentBranches('create', this.value);
        });
        document.querySelectorAll('.edit-additional-hub').forEach((checkbox) => {
            checkbox.addEventListener('change', () => updateAssignmentCount('edit'));
        });
        document.getElementById('edit_branch_search')?.addEventListener('input', function () {
            filterAssignmentBranches('edit', this.value);
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
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(async response => {
                const data = await response.json();
                return { response, data };
            })
            .then(({ response, data }) => {
                if (response.ok && data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('editStaffModal'))?.hide();
                    return AppAlert.show(data.success, 'success', {
                        title: 'Staff account updated',
                        buttonLabel: 'Done'
                    }).then(() => window.location.reload());
                }
                if (response.status === 422 && data.errors) {
                    errorAlert.style.display = 'none';
                    return AppAlert.show(Object.values(data.errors).flat().join('\n'), 'error', {
                        title: 'Update could not be saved'
                    });
                }
                throw new Error(data.message || 'The staff account could not be updated. Please try again.');
            })
            .catch(error => {
                AppAlert.show(error.message, 'error', { title: 'Update could not be saved' });
            });
        });

        document.getElementById('resetPasswordForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const form = e.target;
            const errorAlert = document.getElementById('resetPasswordErrorAlert');

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();

                if (response.ok && data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('resetPasswordModal'))?.hide();
                    form.reset();
                    await AppAlert.show(data.success, 'success', {
                        title: 'Password reset',
                        buttonLabel: 'Done'
                    });
                    return;
                }
                if (response.status === 422 && data.errors) {
                    errorAlert.textContent = Object.values(data.errors).flat().join(' ');
                    errorAlert.style.display = 'block';
                    return;
                }
                throw new Error(data.message || 'The password could not be reset. Please try again.');
            } catch (error) {
                AppAlert.show(error.message, 'error', { title: 'Password reset failed' });
            }
        });
    </script>
@endsection
