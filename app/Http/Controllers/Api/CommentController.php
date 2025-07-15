<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\News;
use App\Models\Comment;

class CommentController extends Controller
{

    public function store(Request $request, News $news)
    {
        $request->validate([
            'content' => 'required|string',
            'parent_id' => 'nullable|exists:comments,id',
        ]);

        $comment = $news->comments()->create([
            'content' => $request->content,
            'parent_id' => $request->parent_id,
            'user_id' => auth()->id(),
        ]);


        $comment->load('user:id,name,avatar_url');

        return response()->json([
            'message' => 'Komentar berhasil ditambahkan!',
            'comment' => $comment
        ], 201);
    }
    public function like(Comment $comment)
    {
        $userId = auth()->id();


        $existingVote = $comment->votes()->where('user_id', $userId)->first();

        if ($existingVote) {
            if ($existingVote->vote_type === 'like') {

                $existingVote->delete();
                $comment->decrement('likes');
            } else {

                $existingVote->update(['vote_type' => 'like']);
                $comment->increment('likes');
                $comment->decrement('dislikes');
            }
        } else {

            $comment->votes()->create([
                'user_id' => $userId,
                'vote_type' => 'like'
            ]);
            $comment->increment('likes');
        }

        $currentUserVote = $comment->votes()->where('user_id', $userId)->first();
        $userVoteStatus = $currentUserVote ?
            ($currentUserVote->vote_type === 'like' ? 'liked' : 'disliked') : null;

        return response()->json([
            'message' => 'Vote berhasil',
            'likes' => $comment->fresh()->votes()->where('vote_type', 'like')->count(),
            'dislikes' => $comment->fresh()->votes()->where('vote_type', 'dislike')->count(),
            'userVote' => $userVoteStatus
        ]);
    }

    public function dislike(Comment $comment)
    {
        $userId = auth()->id();


        $existingVote = $comment->votes()->where('user_id', $userId)->first();

        if ($existingVote) {
            if ($existingVote->vote_type === 'dislike') {

                $existingVote->delete();
                $comment->decrement('dislikes');
            } else {

                $existingVote->update(['vote_type' => 'dislike']);
                $comment->increment('dislikes');
                $comment->decrement('likes');
            }
        } else {

            $comment->votes()->create([
                'user_id' => $userId,
                'vote_type' => 'dislike'
            ]);
            $comment->increment('dislikes');
        }

        return response()->json([
            'message' => 'Vote berhasil',
            'likes' => $comment->fresh()->likes,
            'dislikes' => $comment->fresh()->dislikes
        ]);
    }

    public function removeVote(Comment $comment)
    {
        $userId = auth()->id();


        $existingVote = $comment->votes()->where('user_id', $userId)->first();

        if ($existingVote) {

            if ($existingVote->vote_type === 'like') {
                $comment->decrement('likes');
            } else {
                $comment->decrement('dislikes');
            }


            $existingVote->delete();

            return response()->json([
                'message' => 'Vote berhasil dihapus',
                'likes' => $comment->fresh()->likes,
                'dislikes' => $comment->fresh()->dislikes
            ]);
        }

        return response()->json([
            'message' => 'Tidak ada vote yang ditemukan',
            'likes' => $comment->likes,
            'dislikes' => $comment->dislikes
        ], 404);
    }
}
