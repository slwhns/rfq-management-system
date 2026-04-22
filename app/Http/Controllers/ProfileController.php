<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /**
     * Update the user's profile information.
     */
    public function update(Request $request)
    {
        $user = auth()->user();
        if (!$user instanceof User) {
            abort(403, 'Unauthorized');
        }

        // Validate the input
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'company_name' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:1000',
            'profile_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'current_password' => 'nullable|required_with:new_password,new_password_confirmation|string',
            'new_password' => 'nullable|string|min:8|confirmed|different:current_password',
        ]);

        // Allow profile updates to continue even if the address migration is not yet applied.
        if (!Schema::hasColumn('users', 'address')) {
            unset($validated['address']);
        }

        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            $validated['profile_photo_path'] = $request->file('profile_photo')->store('profile-photos', 'public');
        }

        if (!empty($validated['new_password'])) {
            if (empty($validated['current_password']) || !Hash::check((string) $validated['current_password'], (string) $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['Current password is incorrect.'],
                ]);
            }

            $validated['password'] = Hash::make((string) $validated['new_password']);
        }

        unset($validated['profile_photo']);
        unset($validated['current_password'], $validated['new_password'], $validated['new_password_confirmation']);

        // Update the user
        $user->update($validated);

        $returnRoute = route('profile.index');

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'redirect' => $returnRoute,
        ]);
    }
}
