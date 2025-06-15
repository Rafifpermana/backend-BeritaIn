<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    // Mengambil data user yang sedang login
    public function show(Request $request)
    {
        return response()->json($request->user());
    }

    // Update profil (nama & avatar)
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'name' => 'required|string|max:255',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $user->name = $request->name;

        if ($request->hasFile('avatar')) {
            // Logika untuk menyimpan file avatar
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar_url = $path;
        }

        $user->save();
        return response()->json(['message' => 'Profile updated successfully!', 'user' => $user]);
    }

    // Ganti password
    public function updatePassword(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json(['message' => 'Password updated successfully!']);
    }

    // Mengambil semua notifikasi milik user
    public function getNotifications(Request $request)
    {
        return response()->json($request->user()->notifications()->paginate(15));
    }

    // Menandai notifikasi sebagai sudah dibaca
    public function markNotificationAsRead(Request $request, $notificationId)
    {
        $notification = $request->user()->notifications()->find($notificationId);
        if ($notification) {
            $notification->markAsRead();
        }
        return response()->json(['message' => 'Notification marked as read.']);
    }

    // Mengambil semua bookmark milik user
    public function getBookmarks(Request $request)
    {
        $bookmarks = $request->user()->bookmarks()->with('news.author:id,name', 'news.category:id,name,slug')->paginate(10);
        return response()->json($bookmarks);
    }
}
