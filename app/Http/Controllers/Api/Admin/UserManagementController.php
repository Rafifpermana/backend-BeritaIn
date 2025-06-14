<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class UserManagementController extends Controller
{
    // Mengambil semua daftar pengguna
    public function index()
    {
        $users = User::orderBy('role', 'desc')->orderBy('name', 'asc')->paginate(15);
        return response()->json($users);
    }

    // Mengubah peran (role) pengguna
    public function updateUserRole(Request $request, User $user)
    {
        $request->validate(['role' => 'required|in:admin,user']);
        $user->role = $request->role;
        $user->save();
        return response()->json(['message' => 'User role updated successfully!', 'user' => $user]);
    }

    // Menghapus pengguna
    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'You cannot delete your own account.'], 403);
        }
        $user->delete();
        return response()->json(['message' => 'User deleted successfully.']);
    }
}
