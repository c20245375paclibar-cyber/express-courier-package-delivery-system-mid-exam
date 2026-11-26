<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Services\ImageUploadService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile settings.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit');
    }

    public function updateAvatar(Request $request, ImageUploadService $imageUploads): RedirectResponse
    {
        $validated = $request->validate([
            'avatar' => [
                'required',
                'mimetypes:image/jpeg,image/png,image/webp,image/avif',
                'extensions:jpg,jpeg,png,webp,avif',
                'max:2048',
                'dimensions:max_width=4096,max_height=4096',
            ],
        ]);

        $user = $request->user();
        $oldPath = $user->avatar_path;
        $newPath = $imageUploads->store($validated['avatar'], 'avatars', 512, true, 'avatar');

        try {
            if (! $user->forceFill(['avatar_path' => $newPath])->save()) {
                throw new RuntimeException('The profile photo could not be saved.');
            }
        } catch (Throwable $exception) {
            if (! Storage::disk('public')->delete($newPath)) {
                Log::warning('A profile image could not be deleted after a failed update.', ['path' => $newPath]);
            }

            throw $exception;
        }

        if ($oldPath && ! Storage::disk('public')->delete($oldPath)) {
            Log::warning('A replaced profile image could not be deleted.', ['path' => $oldPath]);
        }

        return back()->with('success', 'Profile photo updated successfully.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
