<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Comment;
use App\Models\User;

class CommentModerationController extends Controller
{
    // Mengambil semua komentar dengan filter status
    public function index(Request $request)
    {
        $request->validate(['status' => 'sometimes|in:approved,pending_review,rejected']);

        $comments = Comment::with('user:id,name,avatar_url', 'news:id,title,slug')
            ->when($request->status, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->latest()
            ->paginate(20);

        return response()->json($comments);
    }

    // Memperbarui status komentar (approve/reject)
    public function update(Request $request, Comment $comment)
    {
        $request->validate(['status' => 'required|in:approved,rejected']);

        $comment->status = $request->status;
        $comment->save();

        // Kurangi poin jika komentar ditolak
        if ($request->status === 'rejected') {
            $user = $comment->user;
            $user->points = max(0, $user->points - 15); // Kurangi 15 poin
            $user->save();
        }

        return response()->json(['message' => 'Comment status updated successfully.', 'comment' => $comment]);
    }

    // Menghapus komentar secara permanen
    public function destroy(Comment $comment)
    {
        $comment->delete();
        return response()->json(['message' => 'Comment permanently deleted.']);
    }
}
