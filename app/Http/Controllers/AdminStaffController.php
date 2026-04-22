<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AdminStaffController extends Controller
{
    public function index()
    {
        $staffUsers = User::query()
            ->whereIn('role', [User::ROLE_CLIENT, User::ROLE_ADMIN])
            ->latest()
            ->paginate(10);

        $hasUsername = Schema::hasColumn('users', 'username');
        $hasPhoneNumber = Schema::hasColumn('users', 'phone_number');

        return view('admin.staff.index', compact('staffUsers', 'hasUsername', 'hasPhoneNumber'));
    }

    public function store(Request $request)
    {
        $hasPhoneNumber = Schema::hasColumn('users', 'phone_number');
        $nameRules = ['required', 'string', 'max:255'];
        $emailRules = ['required', 'email', 'max:255', Rule::unique('users', 'email')];
        $passwordRules = ['required', 'string', 'min:8'];
        $companyRules = ['nullable', 'string', 'max:255'];

        $rules = [
            'name' => $nameRules,
            'email' => $emailRules,
            'password' => $passwordRules,
            'company_name' => $companyRules,
            'role' => ['required', Rule::in([User::ROLE_CLIENT, User::ROLE_ADMIN])],
        ];

        if (Schema::hasColumn('users', 'username')) {
            $rules['username'] = ['required', 'string', 'max:255', Rule::unique('users', 'username')];
        }

        if ($hasPhoneNumber) {
            $rules['phone_number'] = ['nullable', 'string', 'max:20'];
        }

        $validated = $request->validate($rules);

        $staff = new User();
        $staff->name = $validated['name'];
        $staff->email = $validated['email'];
        $staff->password = Hash::make($validated['password']);
        $staff->company_name = $validated['company_name'] ?? null;
        $staff->role = $validated['role'];
        if ($hasPhoneNumber) {
            $staff->phone_number = $validated['phone_number'] ?? null;
        }
        if (Schema::hasColumn('users', 'username')) {
            $staff->username = $validated['username'];
        }
        $staff->save();

        return redirect()->route('admin.staff.index')
            ->with('toast_type', 'success')
            ->with('toast_title', 'Success')
            ->with('toast_message', 'User account has been created.');
    }

    public function update(Request $request, User $user)
    {
        $hasPhoneNumber = Schema::hasColumn('users', 'phone_number');

        if (!in_array($user->normalizedRole(), [User::ROLE_CLIENT, User::ROLE_ADMIN], true)) {
            return redirect()->route('admin.staff.index')
                ->with('toast_type', 'error')
                ->with('toast_title', 'Error')
            ->with('toast_message', 'Only client or admin users can be updated here.');
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in([User::ROLE_CLIENT, User::ROLE_ADMIN])],
        ];

        if (Schema::hasColumn('users', 'username')) {
            $rules['username'] = ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)];
        }

        if ($hasPhoneNumber) {
            $rules['phone_number'] = ['nullable', 'string', 'max:20'];
        }

        $request->merge(['_edit_staff_id' => $user->id]);
        $validated = $request->validate($rules);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->company_name = $validated['company_name'] ?? null;
        if ($hasPhoneNumber) {
            $user->phone_number = $validated['phone_number'] ?? null;
        }
        $user->role = $validated['role'];
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        if (Schema::hasColumn('users', 'username')) {
            $user->username = $validated['username'];
        }
        $user->save();

        return redirect()->route('admin.staff.index')
            ->with('toast_type', 'success')
            ->with('toast_title', 'Success')
            ->with('toast_message', 'User has been updated.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()?->id) {
            return redirect()->route('admin.staff.index')
                ->with('toast_type', 'error')
                ->with('toast_title', 'Error')
                ->with('toast_message', 'You cannot delete your own account.');
        }

        if (!in_array($user->normalizedRole(), [User::ROLE_CLIENT, User::ROLE_ADMIN], true)) {
            return redirect()->route('admin.staff.index')
                ->with('toast_type', 'error')
                ->with('toast_title', 'Error')
            ->with('toast_message', 'Only client or admin users can be deleted here.');
        }

        $user->delete();

        return redirect()->route('admin.staff.index')
            ->with('toast_type', 'success')
            ->with('toast_title', 'Success')
            ->with('toast_message', 'User has been deleted.');
    }
}
