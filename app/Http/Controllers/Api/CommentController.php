<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\News;
use App\Models\Comment;

class CommentController extends Controller
{
    // Menyimpan komentar baru pada sebuah berita
    public function store(Request $request, News $news)
    {
        $request->validate([
            'content' => 'required|string',
            'parent_id' => 'nullable|exists:comments,id',
        ]);

        $comment = $news->comments()->create([
            'content' => $request->content,
            'parent_id' => $request->parent_id,
            'user_id' => auth()->id(), // Mengambil id user yang sedang login
        ]);

        // Load relasi user untuk ditampilkan di response
        $comment->load('user:id,name,avatar_url');

        return response()->json([
            'message' => 'Komentar berhasil ditambahkan!',
            'comment' => $comment
        ], 201);
    }
    public function like(Comment $comment)
    {
        $userId = auth()->id();

        // Cek apakah user sudah vote
        $existingVote = $comment->votes()->where('user_id', $userId)->first();

        if ($existingVote) {
            if ($existingVote->vote_type === 'like') {
                // Jika sudah like, hapus like
                $existingVote->delete();
                $comment->decrement('likes');
            } else {
                // Jika dislike, ubah ke like
                $existingVote->update(['vote_type' => 'like']);
                $comment->increment('likes');
                $comment->decrement('dislikes');
            }
        } else {
            // Buat vote baru
            $comment->votes()->create([
                'user_id' => $userId,
                'vote_type' => 'like'
            ]);
            $comment->increment('likes');
        }

        return response()->json([
            'message' => 'Vote berhasil',
            'likes' => $comment->fresh()->likes,
            'dislikes' => $comment->fresh()->dislikes
        ]);
    }

    public function dislike(Comment $comment)
    {
        $userId = auth()->id();

        // Cek apakah user sudah vote
        $existingVote = $comment->votes()->where('user_id', $userId)->first();

        if ($existingVote) {
            if ($existingVote->vote_type === 'dislike') {
                // Jika sudah dislike, hapus dislike
                $existingVote->delete();
                $comment->decrement('dislikes');
            } else {
                // Jika like, ubah ke dislike
                $existingVote->update(['vote_type' => 'dislike']);
                $comment->increment('dislikes');
                $comment->decrement('likes');
            }
        } else {
            // Buat vote baru
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

        // Cari vote user untuk comment ini
        $existingVote = $comment->votes()->where('user_id', $userId)->first();

        if ($existingVote) {
            // Kurangi counter berdasarkan tipe vote
            if ($existingVote->vote_type === 'like') {
                $comment->decrement('likes');
            } else {
                $comment->decrement('dislikes');
            }

            // Hapus vote
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
