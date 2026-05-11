@extends('layouts.app')

@section('content')

{{-- Page Title --}}
<div class="dash-title-wrap mg-b-20">
    <div class="d-flex fd-column ai-center jc-center gap-8 txt-center">
        <div class="d-flex ai-center gap-10 jc-center">
            <span class="dash-greeting-emoji">👥</span>
            <div class="dash-greeting-text">User Management</div>
        </div>
        <div class="dash-greeting-sub">Manage admin and client accounts that can access this system</div>
    </div>
</div>

{{-- Staff Table Card --}}
<div
    id="staff-page"
    class="dash-table-card"
    data-update-route-template="{{ route('admin.staff.update', ['user' => '__STAFF_ID__']) }}"
    data-store-route="{{ route('admin.staff.store') }}"
    data-flash-type="{{ session('toast_type') }}"
    data-flash-title="{{ session('toast_title') }}"
    data-flash-message="{{ session('toast_message') }}"
    data-has-errors="{{ $errors->any() ? 'true' : 'false' }}"
    data-old-edit-id="{{ old('_edit_staff_id') }}"
    data-old-name="{{ old('name') }}"
    data-old-email="{{ old('email') }}"
    data-old-role="{{ old('role') }}"
    data-old-company="{{ old('company_name') }}"
    data-old-phone="{{ old('phone_number') }}"
    data-old-username="{{ old('username') }}"
    data-errors='@json($errors->all())'
>
    {{-- Card Header --}}
    <div class="dash-table-header">
        <div>
            <div class="dash-table-title">Admin & Client Directory</div>
            <div class="dash-table-subtitle">Manage admin and client accounts that can access this system.</div>
        </div>
        <div class="d-flex ai-center gap-8">
            <input id="staff-search" type="text"
                class="rfq-filter-input rfq-search-input"
                placeholder="Search name, email, phone, company...">
            <button type="button"
                class="rfq-filter-btn-apply"
                onclick="openAddStaffModal()">
                + Add User
            </button>
        </div>
    </div>

    {{-- Table --}}
    @if($staffUsers->count() === 0)
        <div class="dash-empty-state">
            <i class="ri-user-line"></i>
            <div>No users found.</div>
        </div>
    @else
        <div class="of-auto" style="max-height: 470px; overflow-y: auto; overflow-x: auto;">
            <table class="dash-table" style="min-width: 900px;">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        @if($hasPhoneNumber)
                            <th>Phone Number</th>
                        @endif
                        <th>Role</th>
                        <th>Company</th>
                        <th style="width: 110px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="staff-table-body">
                    @foreach($staffUsers as $staff)
                        <tr
                            data-staff-row="true"
                            data-search="{{ strtolower(trim($staff->name . ' ' . $staff->email . ' ' . ($staff->phone_number ?? '') . ' ' . $staff->role . ' ' . ($staff->company_name ?? ''))) }}"
                        >
                            <td class="fw-bold">{{ $staff->name }}</td>
                            <td>{{ $staff->email }}</td>
                            @if($hasPhoneNumber)
                                <td>{{ $staff->phone_number ?: '-' }}</td>
                            @endif
                            <td style="text-transform:capitalize;">
                                <span class="staff-role-badge staff-role-{{ $staff->role }}">{{ $staff->role }}</span>
                            </td>
                            <td>{{ $staff->company_name ?: '-' }}</td>
                            <td>
                                <div class="d-flex ai-center gap-8">
                                    <button
                                        type="button"
                                        class="staff-action-btn"
                                        title="Edit User"
                                        aria-label="Edit User"
                                        data-id="{{ $staff->id }}"
                                        data-name="{{ $staff->name }}"
                                        data-email="{{ $staff->email }}"
                                        data-role="{{ $staff->role }}"
                                        data-company="{{ $staff->company_name }}"
                                        data-phone="{{ $staff->phone_number }}"
                                        @if($hasUsername)
                                        data-username="{{ $staff->username }}"
                                        @endif
                                        onclick="openEditStaffModal(this)"
                                    >
                                        <i class="ri-edit-line"></i>
                                    </button>

                                    <form method="POST" action="{{ route('admin.staff.destroy', $staff->id) }}" onsubmit="return confirm('Delete this user account?');" style="display:inline-flex;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="staff-action-btn staff-action-delete" title="Delete User" aria-label="Delete User">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div id="staff-search-empty" class="dash-empty-state" style="display:none;">
            <i class="ri-search-line"></i>
            <div>No matching users found.</div>
        </div>

        <div class="mg-t-15">
            {{ $staffUsers->links('vendor.pagination.qs') }}
        </div>
    @endif
</div>

{{-- Modal Overlay --}}
<button type="button" id="modal-overlay" class="modal-overlay" onclick="closeAllModals()" aria-label="Close modal"></button>

{{-- Add / Edit Staff Modal --}}
<div id="staff-modal" class="modal-dialog" style="max-width: 560px;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="staff-modal-title">Add User</h3>
            <button class="modal-close" onclick="closeAllModals()">×</button>
        </div>

        <form id="staff-modal-form" method="POST" action="{{ route('admin.staff.store') }}">
            @csrf
            <input id="staff-modal-method" type="hidden" name="_method" value="POST">
            <input id="staff-edit-id" type="hidden" name="_edit_staff_id" value="">

            <div class="modal-body">
                <div class="d-grid gap-15">
                    <div>
                        <label for="staff-modal-name" class="modal-field-label">Full Name</label>
                        <input id="staff-modal-name" type="text" name="name" class="modal-field-input pd-10 br-5 w-100" required>
                    </div>

                    @if($hasUsername)
                        <div>
                            <label for="staff-modal-username" class="modal-field-label">Username</label>
                            <input id="staff-modal-username" type="text" name="username" class="modal-field-input pd-10 br-5 w-100" required>
                        </div>
                    @endif

                    <div>
                        <label for="staff-modal-email" class="modal-field-label">Email</label>
                        <input id="staff-modal-email" type="email" name="email" class="modal-field-input pd-10 br-5 w-100" required>
                    </div>

                    @if($hasPhoneNumber)
                        <div>
                            <label for="staff-modal-phone" class="modal-field-label">Phone Number <span class="modal-field-optional">(Optional)</span></label>
                            <input id="staff-modal-phone" type="text" name="phone_number" class="modal-field-input pd-10 br-5 w-100" maxlength="20">
                        </div>
                    @endif

                    <div>
                        <label for="staff-modal-role" class="modal-field-label">Role</label>
                        <select id="staff-modal-role" name="role" class="modal-field-input pd-10 br-5 w-100" required>
                            <option value="client">Client</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <div>
                        <label for="staff-modal-company" class="modal-field-label">Company <span class="modal-field-optional">(Optional)</span></label>
                        <input id="staff-modal-company" type="text" name="company_name" class="modal-field-input pd-10 br-5 w-100">
                    </div>

                    <div>
                        <label for="staff-modal-password" id="staff-password-label" class="modal-field-label">Temporary Password</label>
                        <div style="position: relative; width: 100%;">
                            <input id="staff-modal-password" type="password" name="password" class="modal-field-input pd-10 br-5 w-100" style="padding-right: 40px; box-sizing: border-box;" required>
                            <button type="button" tabindex="-1" class="modal-pw-toggle" onclick="toggleStaffPasswordVisibility(this)">
                                <i class="ri-eye-line fs-14"></i>
                            </button>
                        </div>
                        <div id="staff-password-help" class="modal-field-help" style="display:none;">Leave blank to keep current password.</div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeAllModals()">Cancel</button>
                <button type="submit" id="staff-modal-submit" class="btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleStaffPasswordVisibility(btn) {
    const input = document.getElementById('staff-modal-password');
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('ri-eye-line', 'ri-eye-off-line');
    } else {
        input.type = 'password';
        icon.classList.replace('ri-eye-off-line', 'ri-eye-line');
    }
}
</script>

@endsection
