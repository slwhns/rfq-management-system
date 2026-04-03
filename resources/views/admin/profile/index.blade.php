@extends('layouts.app')

@section('content')
@php
    $user = auth()->user();
    $displayName = strtoupper((string) ($user?->name ?? 'USER'));
    $displayRole = ucfirst($user?->normalizedRole() ?? 'staff');
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
            <span class="profile-badge">{{ $displayRole }}</span>

            <div class="profile-grid">
                <div class="profile-label">Employee ID</div>
                <div class="profile-value">{{ $employeeId }}</div>

                <div class="profile-label">Email</div>
                <div class="profile-value">{{ $user?->email ?? '-' }}</div>

                <div class="profile-label">Name</div>
                <div class="profile-value">{{ $user?->name ?? '-' }}</div>

                <div class="profile-label">Phone Number</div>
                <div class="profile-value">{{ $user?->phone_number ?? '-' }}</div>

                <div class="profile-label">Department</div>
                <div class="profile-value">{{ $user?->department ?? '-' }}</div>

                <div class="profile-label">Company</div>
                <div class="profile-value">{{ $user?->company_name ?? 'QS Smart Data Center' }}</div>

                <div class="profile-label">Role</div>
                <div class="profile-value">{{ $displayRole }}</div>
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
                    <label for="profile-edit-department" class="fs-12 fw-bold mg-b-5 d-block">Department (Optional)</label>
                    <input id="profile-edit-department" type="text" name="department" class="pd-10 bdr-all-22 br-5 w-100" value="{{ $user?->department ?? '' }}">
                </div>

                <div>
                    <label for="profile-edit-company" class="fs-12 fw-bold mg-b-5 d-block">Company Name</label>
                    <input id="profile-edit-company" type="text" name="company_name" class="pd-10 bdr-all-22 br-5 w-100" value="{{ $user?->company_name ?? '' }}">
                </div>

                <div>
                    <label for="profile-edit-photo" class="fs-12 fw-bold mg-b-5 d-block">Profile Picture (Optional)</label>
                    <input id="profile-edit-photo" type="file" name="profile_photo" class="pd-10 bdr-all-22 br-5 w-100" accept="image/png,image/jpeg,image/webp">
                    <div class="fs-11 clr-grey1 mg-t-5">Allowed: JPG, PNG, WEBP. Max 2MB.</div>
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

