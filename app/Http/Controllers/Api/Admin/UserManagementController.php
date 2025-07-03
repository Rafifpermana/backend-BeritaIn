<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    /**
     * Menampilkan semua pengguna dengan paginasi.
     */
    public function index()
    {
        return User::orderBy('name', 'asc')->paginate(15);
    }

    /**
     * Menampilkan satu pengguna spesifik.
     */
    public function show(User $user)
    {
        return response()->json($user);
    }

    /**
     * Memperbarui data pengguna (dipanggil oleh admin).
     */
    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'points' => 'required|integer|min:0',
        ]);
        $user->update($data);
        return response()->json(['message' => 'User updated successfully!', 'user' => $user]);
    }

    /**
     * Method BARU yang KHUSUS untuk mengubah peran.
     */
    public function updateRole(Request $request, User $user)
    {
        $data = $request->validate(['role' => 'required|in:admin,user']);
        $user->update($data);
        return response()->json(['message' => 'User role updated successfully!', 'user' => $user]);
    }

    /**
     * Menghapus pengguna.
     */
    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Anda tidak bisa menghapus akun sendiri.'], 403);
        }
        $user->delete();
        return response()->json(['message' => 'User deleted successfully.']);
    }

    /**
     * Fungsi pencarian pengguna.
     */
    public function search(Request $request)
    {
        $query = $request->input('q');
        if (empty($query)) {
            return $this->index();
        }

        $users = User::where('name', 'LIKE', "%{$query}%")
            ->orWhere('email', 'LIKE', "%{$query}%")
            ->paginate(15);

        return response()->json($users);
    }
}
