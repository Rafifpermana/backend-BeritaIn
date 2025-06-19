<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Article;
use App\Models\Comment;

class ArticleInteractionController extends Controller
{
    private function getOrCreateArticle(Request $request)
    {
        $validated = $request->validate([
            'article.url' => 'required|url',
            'article.title' => 'required|string',
            'article.description' => 'nullable|string',
            'article.image' => 'nullable|url',
            'article.source_name' => 'required|string',
            'article.pubDate' => 'required|date',
        ]);

        return Article::firstOrCreate(
            ['url' => $validated['article']['url']],
            [
                'title' => $validated['article']['title'],
                'description' => $validated['article']['description'],
                'image_url' => $validated['article']['image'],
                'source_name' => $validated['article']['source_name'],
                'published_at' => $validated['article']['pubDate'],
            ]
        );
    }

    public function postComment(Request $request)
    {
        $article = $this->getOrCreateArticle($request);
        $request->validate([
            'content' => 'required|string',
            'parent_id' => 'nullable|exists:comments,id',
        ]);

        $comment = $article->comments()->create([
            'user_id' => auth()->id(),
            'content' => $request->content,
            'parent_id' => $request->parent_id,
        ]);

        return response()->json($comment->load('user'), 201);
    }

    public function handleVote(Request $request)
    {
        $article = $this->getOrCreateArticle($request);
        $request->validate(['type' => 'required|in:like,dislike']);

        $vote = $article->likes()->updateOrCreate(
            ['user_id' => auth()->id()],
            ['type' => $request->type]
        );

        return response()->json(['message' => 'Vote recorded.', 'vote' => $vote]);
    }

    public function toggleBookmark(Request $request)
    {
        $article = $this->getOrCreateArticle($request);
        $bookmark = $article->bookmarks()->where('user_id', auth()->id())->first();

        if ($bookmark) {
            $bookmark->delete();
            return response()->json(['bookmarked' => false]);
        } else {
            $article->bookmarks()->create(['user_id' => auth()->id()]);
            return response()->json(['bookmarked' => true]);
        }
    }
}
