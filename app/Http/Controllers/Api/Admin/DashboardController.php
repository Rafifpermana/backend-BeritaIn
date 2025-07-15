<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Comment;
use App\Models\News;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getStats()
    {

        $totalUsers = User::count();
        $totalComments = Comment::count();
        $rejectedComments = Comment::where('status', 'rejected')->count();
        $newArticlesThisWeek = News::where('created_at', '>=', now()->subWeek())->count();

        $usersChart = User::select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        $commentsChart = Comment::select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();


        return response()->json([
            'stats' => [
                'total_users' => $totalUsers,
                'total_comments' => $totalComments,
                'rejected_comments' => $rejectedComments,
                'new_articles_this_week' => $newArticlesThisWeek,
            ],
            'charts' => [
                'users' => $usersChart,
                'comments' => $commentsChart,
            ]
        ]);
    }
}
