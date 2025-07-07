<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ArticleInteractionController;
use App\Http\Controllers\Api\ArticleContentController;
use App\Http\Controllers\Api\RSSNewsController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Admin\CommentModerationController;
use App\Http\Controllers\Api\Admin\BroadcastController;
use App\Http\Controllers\Api\Admin\DashboardController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// --- RUTE PUBLIK ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Berita & Kategori
Route::get('/news', [RSSNewsController::class, 'getNews']);
Route::get('/news/sources', [RSSNewsController::class, 'getSources']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::post('/fetch', [ArticleContentController::class, 'fetch']);

// **PERBAIKAN:** Rute untuk melihat interaksi dan komentar dibuat publik
Route::get('/interactions/article', [ArticleInteractionController::class, 'getArticleInteractions']);
Route::get('/interactions/comments', [ArticleInteractionController::class, 'getComments']);


// --- RUTE TERPROTEKSI (MEMBUTUHKAN LOGIN) ---
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // User Profile
    Route::get('/user', [UserController::class, 'show']);
    Route::post('/user/profile', [UserController::class, 'updateProfile']);
    Route::post('/user/password', [UserController::class, 'updatePassword']);

    // Notifikasi & Bookmark
    Route::get('/user/notifications', [UserController::class, 'getNotifications']);
    Route::post('/user/notifications/mark-all-as-read', [UserController::class, 'markAllNotificationsAsRead']);
    Route::post('/user/notifications/{notification}/read', [UserController::class, 'markNotificationAsRead']);
    Route::delete('/user/notifications/{notification}', [UserController::class, 'deleteNotification']);
    Route::get('/user/bookmarks', [UserController::class, 'getBookmarks']);

    // Interaksi Artikel (Aksi yang memerlukan login)
    Route::post('/interactions/comment', [ArticleInteractionController::class, 'postComment']);
    Route::post('/interactions/vote', [ArticleInteractionController::class, 'handleVote']);
    Route::post('/interactions/bookmark', [ArticleInteractionController::class, 'toggleBookmark']);
});


// --- RUTE KHUSUS ADMIN (MEMBUTUHKAN LOGIN & PERAN ADMIN) ---
Route::middleware(['auth:sanctum', 'is.admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard-stats', [DashboardController::class, 'getStats']);

    // Manajemen Pengguna (CRUD)
    Route::get('/users/search', [UserManagementController::class, 'search']);
    Route::apiResource('users', UserManagementController::class);
    Route::patch('/users/{user}/role', [UserManagementController::class, 'updateRole']);

    // Moderasi Komentar
    Route::apiResource('comments', CommentModerationController::class)->only(['index', 'update', 'destroy']);

    // Broadcast
    Route::post('/broadcast', [BroadcastController::class, 'send']);
});
