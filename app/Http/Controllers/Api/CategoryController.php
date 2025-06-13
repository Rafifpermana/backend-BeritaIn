<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\News;

class CategoryController extends Controller
{
    // Mengambil semua kategori
    public function index()
    {
        return response()->json(Category::all());
    }

    // Mengambil semua berita dalam satu kategori
    public function show($slug)
    {
        $category = Category::where('slug', $slug)->firstOrFail();

        $news = News::where('category_id', $category->id)
            ->with(['author:id,name', 'category:id,name,slug']) // Eager loading
            ->latest() // Urutkan dari yang terbaru
            ->paginate(12); // Paginasi

        return response()->json($news);
    }
}
