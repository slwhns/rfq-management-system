@extends('layouts.app')

@section('content')
<div class="bg-white5 pd-15 bdr-bottom-22 mg-b-20">
    <div class="d-flex jc-between ai-center">
        <div class="fs-15 fw-bold">User Management</div>
    </div>
</div>

<div
    id="staff-page"
    class="d-grid gap-20"
    style="grid-template-columns: 1fr; align-items: start;"
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
    data-old-username="{{ old('username') }}"
    data-errors='@json($errors->all())'
>
    <div class="bg-white5 pd-20 br-10 box-shadow-basic">
        <div class="d-flex jc-between ai-center mg-b-10" style="flex-wrap: wrap; gap: 10px;">
            <div>
                <div class="fw-bold">Admin &amp; Staff Directory</div>
                <div class="fs-12 clr-grey1">Manage admin and staff accounts that can access this system.</div>
            </div>
            <div class="d-flex ai-center" style="gap: 12px;">
                <input id="staff-search" type="text" class="pd-8 bdr-all-22 br-5 fs-12" style="width: 240px;" placeholder="Search name, email, company...">
                <button type="button" class="bg-blue clr-white pd-8 br-5 cursor-pointer fs-12" style="border:0;" onclick="openAddStaffModal()">+ Add User</button>
            </div>
        </div>

        @if($staffUsers->count() === 0)
            <div class="pd-15 fs-12 clr-grey1">No users found.</div>
        @else
            <div class="of-auto" style="max-height: 470px; overflow-y: auto; overflow-x: auto;">
                <table style="width:100%; border-collapse: collapse; min-width: 760px;">
                    <thead>
                        <tr style="border-bottom:1px solid #d8d8d8;">
                            <th style="text-align:left; padding:12px 8px;">Name</th>
                            <th style="text-align:left; padding:12px 8px;">Email</th>
                            <th style="text-align:left; padding:12px 8px;">Role</th>
                            <th style="text-align:left; padding:12px 8px;">Company</th>
                            <th style="text-align:left; padding:12px 8px; width: 110px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="staff-table-body">
                        @foreach($staffUsers as $staff)
                            <tr data-staff-row="true" data-search="{{ strtolower(trim($staff->name . ' ' . $staff->email . ' ' . $staff->role . ' ' . ($staff->company_name ?? ''))) }}" style="border-bottom:1px solid #ececec; vertical-align: middle;">
                                <td style="padding:10px 8px;">{{ $staff->name }}</td>
                                <td style="padding:10px 8px;">{{ $staff->email }}</td>
                                <td style="padding:10px 8px; text-transform:capitalize;">{{ $staff->role }}</td>
                                <td style="padding:10px 8px;">{{ $staff->company_name ?: '-' }}</td>
                                <td style="padding:10px 8px;">
                                    <div class="d-flex ai-center" style="gap: 8px;">
                                        <button
                                            type="button"
                                            class="btn-icon"
                                            title="Edit Staff"
                                            aria-label="Edit Staff"
                                            data-id="{{ $staff->id }}"
                                            data-name="{{ $staff->name }}"
                                            data-email="{{ $staff->email }}"
                                            data-role="{{ $staff->role }}"
                                            data-company="{{ $staff->company_name }}"
                                            @if($hasUsername)
                                            data-username="{{ $staff->username }}"
                                            @endif
                                            onclick="openEditStaffModal(this)"
                                        >
                                            <i class="ri-edit-line"></i>
                                        </button>

                                        <form method="POST" action="{{ route('admin.staff.destroy', $staff->id) }}" onsubmit="return confirm('Delete this staff account?');" style="display:inline-flex;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-icon" title="Delete Staff" aria-label="Delete Staff" style="color:#bf2f2f;">
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

            <div id="staff-search-empty" class="pd-15 fs-12 clr-grey1" style="display:none;">No matching staff found.</div>

            <div class="mg-t-15">
                {{ $staffUsers->links() }}
            </div>
        @endif
    </div>
</div>

<button type="button" id="modal-overlay" class="modal-overlay" onclick="closeAllModals()" aria-label="Close modal"></button>

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
                        <label for="staff-modal-name" class="fs-12 fw-bold mg-b-5 d-block">Full Name</label>
                        <input id="staff-modal-name" type="text" name="name" class="pd-10 bdr-all-22 br-5 w-100" required>
                    </div>

                    @if($hasUsername)
                        <div>
                            <label for="staff-modal-username" class="fs-12 fw-bold mg-b-5 d-block">Username</label>
                            <input id="staff-modal-username" type="text" name="username" class="pd-10 bdr-all-22 br-5 w-100" required>
                        </div>
                    @endif

                    <div>
                        <label for="staff-modal-email" class="fs-12 fw-bold mg-b-5 d-block">Email</label>
                        <input id="staff-modal-email" type="email" name="email" class="pd-10 bdr-all-22 br-5 w-100" required>
                    </div>

                    <div>
                        <label for="staff-modal-role" class="fs-12 fw-bold mg-b-5 d-block">Role</label>
                        <select id="staff-modal-role" name="role" class="pd-10 bdr-all-22 br-5 w-100" required>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <div>
                        <label for="staff-modal-company" class="fs-12 fw-bold mg-b-5 d-block">Company (Optional)</label>
                        <input id="staff-modal-company" type="text" name="company_name" class="pd-10 bdr-all-22 br-5 w-100">
                    </div>

                    <div>
                        <label for="staff-modal-password" id="staff-password-label" class="fs-12 fw-bold mg-b-5 d-block">Temporary Password</label>
                        <input id="staff-modal-password" type="password" name="password" class="pd-10 bdr-all-22 br-5 w-100" required>
                        <div id="staff-password-help" class="fs-11 clr-grey1 mg-t-5" style="display:none;">Leave blank to keep current password.</div>
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

@endsection
