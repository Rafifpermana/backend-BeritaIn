<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Bookmark;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ArticleInteractionController extends Controller
{
    private function getOrCreateArticle(Request $request)
    {
        $validated = $request->validate([
            'article.url' => 'required|url',
            'article.title' => 'required|string',
            'article.description' => 'nullable|string',
            'article.image' => 'nullable|string',
            'article.source_name' => 'required|string',
            'article.pubDate' => 'required|date',
        ]);

        $articleData = $validated['article'];

        return Article::firstOrCreate(
            ['url' => $articleData['url']],
            [
                'title' => $articleData['title'],
                'description' => $articleData['description'] ?? '',
                'image_url' => $articleData['image'] ?? '',
                'source_name' => $articleData['source_name'],
                'published_at' => Carbon::parse($articleData['pubDate']),
            ]
        );
    }

    public function postComment(Request $request)
    {
        try {
            $article = $this->getOrCreateArticle($request);

            $validated = $request->validate([
                'content' => 'required|string|max:1000',
                'parent_id' => 'nullable|exists:comments,id',
            ]);

            $comment = $article->comments()->create([
                'user_id' => Auth::id(),
                'content' => $validated['content'],
                'parent_id' => $validated['parent_id'],
                'status' => 'approved', // Auto approve for now
            ]);

            // Load the user relationship
            $comment->load('user');

            return response()->json([
                'success' => true,
                'message' => 'Komentar berhasil ditambahkan',
                'id' => $comment->id,
                'content' => $comment->content,
                'created_at' => $comment->created_at,
                'user' => $comment->user,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating comment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan komentar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function handleVote(Request $request)
    {
        try {
            // Ini sudah benar, mendapatkan artikel atau membuatnya jika belum ada.
            $article = $this->getOrCreateArticle($request);

            $validated = $request->validate(['type' => 'required|in:like,dislike']);
            $userId = Auth::id();
            $voteType = $validated['type'];

            // Gunakan updateOrCreate untuk logika yang lebih bersih
            $vote = $article->likes()->updateOrCreate(
                [
                    // Kondisi untuk mencari: cari vote dari user ini untuk artikel ini
                    'user_id' => $userId,
                ],
                [
                    // Data untuk di-update atau di-create
                    'type' => $voteType,
                ]
            );

            $message = 'Berhasil memproses vote';

            // Dapatkan jumlah vote terbaru
            $likesCount = $article->likes()->where('type', 'like')->count();
            $dislikesCount = $article->likes()->where('type', 'dislike')->count();

            return response()->json([
                'success' => true,
                'message' => $message,
                // 'current_vote' akan selalu sama dengan $voteType setelah ini
                'current_vote' => $vote->type,
                'likes_count' => $likesCount,
                'dislikes_count' => $dislikesCount,
            ]);
        } catch (\Exception $e) {
            // Log error untuk debugging
            Log::error('Error saat memproses vote: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses vote di server',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function toggleBookmark(Request $request)
    {
        try {
            $article = $this->getOrCreateArticle($request);
            $userId = Auth::id();

            $bookmark = Bookmark::where('article_id', $article->id)
                ->where('user_id', $userId)
                ->first();

            if ($bookmark) {
                $bookmark->delete();
                $message = 'Bookmark dihapus';
                $bookmarked = false;
            } else {
                Bookmark::create([
                    'article_id' => $article->id,
                    'user_id' => $userId,
                ]);
                $message = 'Berhasil menambahkan bookmark';
                $bookmarked = true;
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'bookmarked' => $bookmarked,
            ]);
        } catch (\Exception $e) {
            Log::error('Error toggling bookmark: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengubah bookmark',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getArticleInteractions(Request $request)
    {
        try {
            $validated = $request->validate([
                'article_url' => 'required|url',
            ]);

            $article = Article::where('url', $validated['article_url'])->first();

            if (!$article) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'likes_count' => 0,
                        'dislikes_count' => 0,
                        'user_vote' => null,
                        'is_bookmarked' => false,
                        'comments_count' => 0,
                    ]
                ]);
            }

            $userId = Auth::id();
            $userVote = null;
            $isBookmarked = false;

            if ($userId) {
                $userLike = Like::where('article_id', $article->id)
                    ->where('user_id', $userId)
                    ->first();
                $userVote = $userLike ? $userLike->type : null;

                $isBookmarked = Bookmark::where('article_id', $article->id)
                    ->where('user_id', $userId)
                    ->exists();
            }

            $likesCount = Like::where('article_id', $article->id)
                ->where('type', 'like')
                ->count();
            $dislikesCount = Like::where('article_id', $article->id)
                ->where('type', 'dislike')
                ->count();
            $commentsCount = Comment::where('article_id', $article->id)
                ->whereNull('parent_id')
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'likes_count' => $likesCount,
                    'dislikes_count' => $dislikesCount,
                    'user_vote' => $userVote,
                    'is_bookmarked' => $isBookmarked,
                    'comments_count' => $commentsCount,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting article interactions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data interaksi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getComments(Request $request)
    {
        try {
            $validated = $request->validate([
                'article_url' => 'required|url',
            ]);

            $article = Article::where('url', $validated['article_url'])->first();

            if (!$article) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            $comments = Comment::where('article_id', $article->id)
                ->whereNull('parent_id')
                ->with(['user', 'replies.user'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $comments
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting comments: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil komentar',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
