@extends('layouts.app')

@section('content')
@php
    $user = auth()->user();
    $displayName = strtoupper((string) ($user?->name ?? 'USER'));
    $avatarInitial = strtoupper(substr((string) ($user?->name ?? 'U'), 0, 1));
    $profilePhotoUrl = $user?->profile_photo_path ? \Illuminate\Support\Facades\Storage::url($user->profile_photo_path) : null;
    $employeeId = 'QS-' . str_pad((string) ($user?->id ?? 0), 4, '0', STR_PAD_LEFT);
@endphp

<div class="bg-white5 pd-15 bdr-bottom-22 mg-b-20">
    <div class="d-flex jc-between ai-center">
        <div class="fs-15 fw-bold">Profile</div>
    </div>
</div>

<div class="profile-wrap">
    <div class="profile-card">
        <div class="profile-cover">
            <div class="profile-avatar">
                @if($profilePhotoUrl)
                    <img src="{{ $profilePhotoUrl }}" alt="{{ $displayName }} avatar" class="profile-avatar-image">
                @else
                    {{ $avatarInitial }}
                @endif
            </div>
        </div>

        <div class="profile-main">
            <h2 class="profile-name">{{ $displayName }}</h2>

            <div class="profile-grid">
                <div class="profile-label">Staff ID</div>
                <div class="profile-value">{{ $employeeId }}</div>

                <div class="profile-label">Email</div>
                <div class="profile-value">{{ $user?->email ?? '-' }}</div>

                <div class="profile-label">Name</div>
                <div class="profile-value">{{ $user?->name ?? '-' }}</div>

                <div class="profile-label">Phone Number</div>
                <div class="profile-value">{{ $user?->phone_number ?? '-' }}</div>

                <div class="profile-label">Company</div>
                <div class="profile-value">{{ $user?->company_name ?? 'QS Smart Data Center' }}</div>

                <div class="profile-label">Address</div>
                <div class="profile-value">{{ $user?->address ?? '-' }}</div>
            </div>

            <div class="profile-actions">
                <a href="{{ route('dashboard') }}" class="profile-btn profile-btn-light">Back to Dashboard</a>
                <button type="button" class="profile-btn profile-btn-primary" onclick="openProfileEditModal()">Edit Profile</button>
            </div>
        </div>
    </div>
</div>

<button type="button" id="profile-edit-overlay" class="modal-overlay" onclick="closeProfileEditModal()" aria-label="Close modal"></button>

<div id="profile-edit-modal" class="modal-dialog" style="max-width: 560px;">
    <form id="profile-edit-form" class="modal-content" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PATCH')

        <div class="modal-header">
            <h3>Edit Profile</h3>
            <button type="button" class="modal-close" onclick="closeProfileEditModal()">×</button>
        </div>

        <div class="modal-body">
            <div class="d-grid gap-15">
                <div>
                    <label for="profile-edit-name" class="fs-12 fw-bold mg-b-5 d-block">Name *</label>
                    <input id="profile-edit-name" type="text" name="name" class="pd-10 bdr-all-22 br-5 w-100" value="{{ $user?->name ?? '' }}" required>
                </div>

                <div>
                    <label for="profile-edit-email" class="fs-12 fw-bold mg-b-5 d-block">Email *</label>
                    <input id="profile-edit-email" type="email" name="email" class="pd-10 bdr-all-22 br-5 w-100" value="{{ $user?->email ?? '' }}" required>
                </div>

                <div>
                    <label for="profile-edit-phone" class="fs-12 fw-bold mg-b-5 d-block">Phone Number (Optional)</label>
                    <input id="profile-edit-phone" type="text" name="phone_number" class="pd-10 bdr-all-22 br-5 w-100" value="{{ $user?->phone_number ?? '' }}">
                </div>

                <div>
                    <label for="profile-edit-company" class="fs-12 fw-bold mg-b-5 d-block">Company Name</label>
                    <input id="profile-edit-company" type="text" name="company_name" class="pd-10 bdr-all-22 br-5 w-100" value="{{ $user?->company_name ?? '' }}">
                </div>

                <div>
                    <label for="profile-edit-address" class="fs-12 fw-bold mg-b-5 d-block">Address (Optional)</label>
                    <textarea id="profile-edit-address" name="address" class="pd-10 bdr-all-22 br-5 w-100" style="min-height:80px; resize:vertical;">{{ $user?->address ?? '' }}</textarea>
                </div>

                <div>
                    <label for="profile-edit-photo" class="fs-12 fw-bold mg-b-5 d-block">Profile Picture (Optional)</label>
                    <input id="profile-edit-photo" type="file" name="profile_photo" class="pd-10 bdr-all-22 br-5 w-100" accept="image/png,image/jpeg,image/webp">
                    <div class="fs-11 clr-grey1 mg-t-5">Allowed: JPG, PNG, WEBP. Max 2MB.</div>
                </div>

                <div class="pd-10 br-5" style="border:1px solid #e7eaf3; background:#f8faff; overflow:hidden;">
                    <div class="fs-12 fw-bold mg-b-10">Change Password (Optional)</div>

                    <div class="mg-b-10">
                        <label for="profile-edit-current-password" class="fs-12 fw-bold mg-b-5 d-block">Current Password</label>
                        <input id="profile-edit-current-password" type="password" name="current_password" class="pd-10 bdr-all-22 br-5 w-100" style="box-sizing:border-box; max-width:100%;" autocomplete="current-password" data-profile-password-input>
                    </div>

                    <div class="mg-b-10">
                        <label for="profile-edit-new-password" class="fs-12 fw-bold mg-b-5 d-block">New Password</label>
                        <input id="profile-edit-new-password" type="password" name="new_password" class="pd-10 bdr-all-22 br-5 w-100" style="box-sizing:border-box; max-width:100%;" autocomplete="new-password" data-profile-password-input>
                    </div>

                    <div>
                        <label for="profile-edit-new-password-confirmation" class="fs-12 fw-bold mg-b-5 d-block">Confirm New Password</label>
                        <input id="profile-edit-new-password-confirmation" type="password" name="new_password_confirmation" class="pd-10 bdr-all-22 br-5 w-100" style="box-sizing:border-box; max-width:100%;" autocomplete="new-password" data-profile-password-input>
                    </div>

                    <label class="d-flex ai-center gap-10 mg-t-10 fs-12 clr-grey1" style="cursor:pointer;">
                        <input id="profile-edit-show-password" type="checkbox" data-profile-password-toggle>
                        Show password fields
                    </label>

                    <div class="fs-11 clr-grey1 mg-t-8">Leave all password fields empty if you do not want to change your password. Minimum 8 characters.</div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeProfileEditModal()">Cancel</button>
            <button type="submit" id="profile-edit-submit" class="btn-primary">Save Changes</button>
        </div>
    </form>
</div>

@endsection


