<?php

// routes/api.php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ArticleInteractionController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Admin\CommentModerationController;
use App\Http\Controllers\Api\Admin\BroadcastController;
use App\Http\Controllers\Api\RSSNewsController;
use App\Http\Controllers\Api\ArticleContentController;
use App\Http\Controllers\Api\CategoryController;



// Rute Publik (Authentication)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Konten Berita dari RSS Feeds
Route::prefix('news')->group(function () {
    Route::get('/home', [RSSNewsController::class, 'getHomePageFeed']);
    Route::get('/article-content', [ArticleContentController::class, 'fetch']);
    Route::get('/categories', [CategoryController::class, 'index']); // -> Untuk Navbar & Footer
    Route::get('/news', [RSSNewsController::class, 'getNews']); // -> Untuk HomePage & CategoryPage
    // Contoh: GET /api/news?source=kompas&category=tekno
    Route::get('/', [RSSNewsController::class, 'getNews']);

    // Contoh: GET /api/news/search?q=gibran
    Route::get('/search', [RSSNewsController::class, 'searchNews']);

    // Contoh: GET /api/news/sources
    Route::get('/sources', [RSSNewsController::class, 'getSources']);
});

// Rute yang Membutuhkan Autentikasi
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // User Profile & Data
    Route::get('/user', [UserController::class, 'show']);
    Route::post('/user/profile', [UserController::class, 'updateProfile']);
    Route::post('/user/password', [UserController::class, 'updatePassword']);
    Route::get('/user/notifications', [UserController::class, 'getNotifications']);
    Route::patch('/user/notifications/{notificationId}', [UserController::class, 'markNotificationAsRead']);
    Route::get('/user/bookmarks', [UserController::class, 'getBookmarks']);

    // Interactions
    Route::post('/interactions/comment', [ArticleInteractionController::class, 'postComment']);
    Route::post('/interactions/vote', [ArticleInteractionController::class, 'handleVote']);
    Route::post('/interactions/bookmark', [ArticleInteractionController::class, 'toggleBookmark']);
});

// ADMIN ROUTES
Route::middleware(['auth:sanctum', 'is.admin'])->prefix('admin')->group(function () {
    // Rute baru untuk statistik dashboard
    Route::get('/dashboard-stats', [DashboardController::class, 'getStats']);

    // User Management
    Route::get('/users', [UserManagementController::class, 'index']);
    Route::patch('/users/{user}/role', [UserManagementController::class, 'updateUserRole']);
    Route::delete('/users/{user}', [UserManagementController::class, 'destroy']);

    // Comment Moderation
    Route::get('/comments', [CommentModerationController::class, 'index']);
    Route::patch('/comments/{comment}', [CommentModerationController::class, 'update']);
    Route::delete('/comments/{comment}', [CommentModerationController::class, 'destroy']);

    // Broadcast Notification
    Route::post('/broadcast', [BroadcastController::class, 'send']);
});
