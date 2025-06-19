<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            "all",
            "bisnis",
            "bola",
            "dunia",
            "ekonomi",
            "entertainment",
            "gaya-hidup",
            "hiburan",
            "hukum",
            "internasional",
            "investment",
            "lifestyle",
            "market",
            "nasional",
            "news",
            "olahraga",
            "politik",
            "tech",
            "tekno",
            "teknologi"
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['slug' => Str::slug($category)],
                ['name' => ucwords(str_replace('-', ' ', $category))]
            );
        }
    }
}
