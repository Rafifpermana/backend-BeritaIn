<?php

// database/migrations/xxxx_xx_xx_xxxxxx_create_likes_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('news_id')->constrained('news')->onDelete('cascade');
            $table->enum('type', ['like', 'dislike']); // Tipe vote
            $table->unique(['user_id', 'news_id']); // Satu user satu vote per berita
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('likes');
    }
};
