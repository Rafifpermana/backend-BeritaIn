<?php

// database/seeders/CommentSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Comment;
use App\Models\News;
use App\Models\User;

class CommentSeeder extends Seeder
{
    public function run(): void
    {
        $news = News::all();
        $users = User::all();

        // Buat 30 komentar utama
        for ($i = 0; $i < 30; $i++) {
            Comment::create([
                'content' => 'Ini adalah komentar yang sangat relevan dan membangun.',
                'news_id' => $news->random()->id,
                'user_id' => $users->random()->id,
            ]);
        }

        // Buat 15 balasan untuk komentar yang sudah ada
        $comments = Comment::all();
        for ($i = 0; $i < 15; $i++) {
            $parentComment = $comments->random();
            Comment::create([
                'content' => 'Saya setuju dengan komentar ini!',
                'news_id' => $parentComment->news_id,
                'user_id' => $users->random()->id,
                'parent_id' => $parentComment->id, // Menjadikannya balasan
            ]);
        }
    }
}
