<?php

// routes/api.php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\InteractionController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Admin\CommentModerationController;
use App\Http\Controllers\Api\Admin\BroadcastController;

// Rute Publik (Authentication)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Rute Publik (Konten)
Route::get('/news/top-headlines', [NewsController::class, 'index']);
Route::get('/news/search', [NewsController::class, 'search']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{slug}', [CategoryController::class, 'show']);

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
    Route::post('/news/{news}/vote', [InteractionController::class, 'handleVote']);
    Route::post('/news/{news}/bookmark', [InteractionController::class, 'toggleBookmark']);

    // Menambah komentar pada berita
    Route::post('/news/{news:slug}/comments', [CommentController::class, 'store']);
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
