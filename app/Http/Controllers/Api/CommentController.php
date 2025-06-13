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
}
