<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        if ($user->avatar_url) {
            $user->avatar_url = asset('storage/' . $user->avatar_url);
        }

        return response()->json($user);
    }

    public function update(Request $request, User $user)
    {

        if ($user->id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
        ]);

        $user->update($validatedData);

        return response()->json([
            'message' => 'Profile updated successfully!',
            'user'    => $user->fresh()
        ]);
    }

    public function updatePassword(Request $request)
    {
        try {
            $user = $request->user();

            $request->validate([
                'current_password' => ['required', 'current_password'],
                'password'         => ['required', 'confirmed', Password::min(8)],
            ]);

            $user->password = Hash::make($request->password);
            $user->save();

            return response()->json(['message' => 'Password updated successfully!']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Password update error: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred while updating password'
            ], 500);
        }
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'   => 'sometimes|required|string|max:255',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->has('name')) {
            $user->name = $validated['name'];
        }

        if ($request->hasFile('avatar')) {

            if ($user->avatar_url) {

                Storage::disk('public')->delete($user->getRawOriginal('avatar_url'));
            }
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar_url = $path;
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully!',
            'user'    => $user,
        ]);
    }

    public function getNotifications(Request $request)
    {
        return $request->user()->notifications()->paginate(15);
    }

    /**
     * Menandai SEMUA notifikasi yang belum dibaca menjadi sudah dibaca.
     */
    public function markNotificationAsRead(Request $request, $notificationId)
    {
        $notification = $request->user()->notifications()->findOrFail($notificationId);
        $notification->markAsRead();
        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function markAllNotificationsAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['message' => 'All notifications marked as read.']);
    }

    /**
     * Menghapus satu notifikasi spesifik.
     */
    public function deleteNotification(Request $request, $notificationId)
    {
        $notification = $request->user()->notifications()->find($notificationId);
        if ($notification) {
            $notification->delete();
            return response()->json(['message' => 'Notification deleted.']);
        }
        return response()->json(['message' => 'Notification not found.'], 404);
    }

    public function getBookmarks(Request $request)
    {

        $bookmarks = $request->user()
            ->bookmarks()
            ->with('article')
            ->latest()
            ->paginate(10);

        return response()->json($bookmarks);
    }
}
