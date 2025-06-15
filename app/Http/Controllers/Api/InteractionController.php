<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\News;
use App\Models\Like;
use App\Models\Bookmark;

class InteractionController extends Controller
{
    // Mengelola like/dislike
    public function handleVote(Request $request, News $news)
    {
        $request->validate(['type' => 'required|in:like,dislike']);

        $vote = $news->likes()->updateOrCreate(
            ['user_id' => auth()->id()],
            ['type' => $request->type]
        );

        // Update total likes/dislikes di tabel news
        $news->likes = $news->likes()->where('type', 'like')->count();
        $news->dislikes = $news->likes()->where('type', 'dislike')->count();
        $news->save();

        return response()->json(['message' => 'Vote recorded.', 'likes' => $news->likes, 'dislikes' => $news->dislikes]);
    }

    // Mengelola bookmark
    public function toggleBookmark(Request $request, News $news)
    {
        $bookmark = $news->bookmarks()->where('user_id', auth()->id())->first();

        if ($bookmark) {
            $bookmark->delete();
            return response()->json(['bookmarked' => false]);
        } else {
            $news->bookmarks()->create(['user_id' => auth()->id()]);
            return response()->json(['bookmarked' => true]);
        }
    }
}
