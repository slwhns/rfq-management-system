<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

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
            'department' => 'nullable|string|max:255',
            'profile_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            $validated['profile_photo_path'] = $request->file('profile_photo')->store('profile-photos', 'public');
        }

        unset($validated['profile_photo']);

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
