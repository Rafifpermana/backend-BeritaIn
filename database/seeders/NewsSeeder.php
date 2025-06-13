<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\News;
use App\Models\User;
use App\Models\Category;
use Illuminate\Support\Str;

class NewsSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $categories = Category::all();

        for ($i = 0; $i < 20; $i++) { // Membuat 20 berita
            $title = "Ini Adalah Judul Berita Menarik Ke-" . ($i + 1);
            News::create([
                'title' => $title,
                'slug' => Str::slug($title),
                'content' => "Ini adalah isi konten dari berita. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.",
                'user_id' => $users->random()->id,
                'category_id' => $categories->random()->id,
                'likes' => rand(10, 200),
                'dislikes' => rand(0, 20),
            ]);
        }
    }
}
