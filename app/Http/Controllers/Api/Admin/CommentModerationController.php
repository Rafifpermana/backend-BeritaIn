<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Comment;
use App\Models\User;

class CommentModerationController extends Controller
{

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

    public function update(Request $request, Comment $comment)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',

            'points_to_deduct' => 'nullable|integer|min:0'
        ]);

        $comment->status = $request->status;
        $comment->save();

        if ($request->status === 'rejected') {

            $pointsToDeduct = $request->input('points_to_deduct', 15);

            $user = $comment->user;
            if ($user) {
                $user->points = max(0, $user->points - $pointsToDeduct);
                $user->save();
            }
        }

        return response()->json(['message' => 'Comment status updated successfully.', 'comment' => $comment->load('user')]);
    }

    public function destroy(Comment $comment)
    {
        $comment->delete();
        return response()->json(['message' => 'Comment permanently deleted.']);
    }
}
