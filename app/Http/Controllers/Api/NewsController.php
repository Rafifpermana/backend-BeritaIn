<?php

// app/Http/Controllers/Api/NewsController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http; // Import HTTP Client
use Illuminate\Support\Facades\Cache; // Import Cache untuk performa

class NewsController extends Controller
{
    /**
     * Menampilkan daftar berita utama dari NewsAPI.org
     */
    public function index(Request $request)
    {
        // Cache key untuk menyimpan hasil API
        $cacheKey = 'newsapi_top_headlines_' . ($request->input('page', 1));

        // Ambil data dari cache selama 1 jam (3600 detik)
        // Jika tidak ada, jalankan request ke API
        $articles = Cache::remember($cacheKey, 3600, function () use ($request) {
            $apiKey = config('services.newsapi.key');
            $apiUrl = 'https://newsapi.org/v2/top-headlines';

            $response = Http::get($apiUrl, [
                'apiKey' => $apiKey,
                'country' => 'id', // Mengambil berita dari Indonesia
                'category' => $request->input('category', 'general'), // Kategori bisa dinamis
                'page' => $request->input('page', 1),
                'pageSize' => 12,
            ]);

            // Jika request gagal, kembalikan response error
            if ($response->failed()) {
                return response()->json(['error' => 'Gagal mengambil berita.'], 500);
            }

            // Kembalikan hanya bagian 'articles' dari response API
            return $response->json()['articles'];
        });

        return response()->json($articles);
    }

    /**
     * Menangani pencarian berita dari NewsAPI.org
     */
    public function search(Request $request)
    {
        $request->validate(['q' => 'required|string']);
        $query = $request->input('q');

        $cacheKey = 'newsapi_search_' . md5($query) . '_' . ($request->input('page', 1));

        $articles = Cache::remember($cacheKey, 3600, function () use ($query, $request) {
            $apiKey = config('services.newsapi.key');
            $apiUrl = 'https://newsapi.org/v2/everything';

            $response = Http::get($apiUrl, [
                'apiKey' => $apiKey,
                'q' => $query,
                'language' => 'id',
                'sortBy' => 'relevancy',
                'page' => $request->input('page', 1),
                'pageSize' => 12,
            ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Gagal mencari berita.'], 500);
            }

            return $response->json()['articles'];
        });

        return response()->json($articles);
    }
}
