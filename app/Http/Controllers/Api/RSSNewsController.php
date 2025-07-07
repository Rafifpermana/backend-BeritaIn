<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Vedmant\FeedReader\Facades\FeedReader;
use App\Models\Category;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\Article;
use App\Models\Like;
use App\Models\Comment;

class RSSNewsController extends Controller
{
    private $rssSources;

    public function __construct()
    {
        $this->rssSources = config('newssources.rss_sources', []);
    }

    public function getHomePageFeed(Request $request)
    {
        $limitPerSource = (int)$request->input('limit_per_source', 10);
        $cacheKey = "rss_homepage_feed_limit_{$limitPerSource}";

        $articles = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($limitPerSource) {
            $allArticles = [];

            // Loop melalui setiap sumber berita di konfigurasi
            foreach ($this->rssSources as $sourceKey => $sourceData) {
                // Ambil feed dari kategori 'all' untuk setiap sumber
                if (isset($sourceData['feeds']['all'])) {
                    $feedUrl = $sourceData['feeds']['all'];

                    // Kita panggil method parseRSSFeed yang sudah ada
                    // dengan page=1 dan limit yang kita tentukan
                    $sourceArticles = $this->parseRSSFeed($feedUrl, 1, $limitPerSource);

                    // Tambahkan nama sumber ke setiap artikel
                    foreach ($sourceArticles as &$article) {
                        $article['source_name'] = $sourceData['name'];
                    }

                    $allArticles = array_merge($allArticles, $sourceArticles);
                }
            }

            // Acak urutan semua artikel yang sudah digabung
            shuffle($allArticles);

            return $allArticles;
        });

        return response()->json($articles);
    }

    // Ganti fungsi searchNews Anda dengan yang ini
    public function searchNews(Request $request)
    {
        $query = $request->input('q', '');
        $categorySlug = $request->input('category');
        $limitPerSource = (int)$request->input('limit_per_source', 30); // Ambil lebih banyak hasil

        if (empty(trim($query))) {
            return response()->json(['data' => [], 'message' => 'Query pencarian diperlukan'], 400);
        }

        // Menggunakan cache tetap penting untuk performa
        $cacheKey = "rss_search_raw_" . md5($query) . "_" . ($categorySlug ?: 'all');
        $allArticles = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($categorySlug) {
            $sources = config('newssources.rss_sources');
            $articles = [];
            $limit = 100; // Ambil banyak artikel untuk di-filter

            // Logika pengambilan artikel (tidak berubah)
            if ($categorySlug) {
                // ... (logika Anda yang sudah ada untuk mengambil berdasarkan kategori)
            } else {
                // Ambil dari semua sumber jika tidak ada kategori
                foreach ($sources as $sourceKey => $sourceData) {
                    if (isset($sourceData['feeds']['all'])) {
                        $feedUrl = $sourceData['feeds']['all'];
                        $sourceArticles = $this->parseRSSFeed($feedUrl, 1, $limit);
                        foreach ($sourceArticles as &$article) {
                            $article['source_name'] = $sourceData['name'];
                        }
                        $articles = array_merge($articles, $sourceArticles);
                    }
                }
            }
            return $articles;
        });

        // Filter dan urutkan dengan logika baru
        $filteredArticles = $this->filterArticlesByQuery($allArticles, $query);
        $sortedArticles = $this->sortArticlesByRelevance($filteredArticles); // Pengurutan sekarang lebih sederhana

        // Kembalikan hasil yang sudah diurutkan dan dipotong sesuai limit
        return response()->json(array_slice($sortedArticles, 0, $limitPerSource));
    }

    // Ganti fungsi filterArticlesByQuery Anda dengan yang ini
    private function filterArticlesByQuery($articles, $query)
    {
        $query = strtolower(trim($query));
        if (empty($query)) return [];

        // Ambil kata kunci yang lebih dari 2 huruf
        $keywords = array_filter(explode(' ', $query), fn($k) => strlen(trim($k)) > 2);
        // Jika tidak ada kata kunci panjang, gunakan semua kata kunci
        if (empty($keywords)) {
            $keywords = explode(' ', $query);
        }

        $filteredResults = [];
        foreach ($articles as $article) {
            $title = strtolower($article['title']);
            $description = strtolower($article['description'] ?? '');
            $score = 0;

            // Skor 1: Jika seluruh frasa pencarian ada di judul (skor tertinggi)
            if (str_contains($title, $query)) {
                $score += 50;
            }

            $matchedKeywords = 0;
            foreach ($keywords as $keyword) {
                $keyword = trim($keyword);
                if (empty($keyword)) continue;

                // Cek apakah kata kunci ada di judul atau deskripsi
                if (str_contains($title, $keyword)) {
                    $score += 15; // Beri skor lebih tinggi jika di judul
                    $matchedKeywords++;
                } elseif (str_contains($description, $keyword)) {
                    $score += 5; // Skor standar jika di deskripsi
                    $matchedKeywords++;
                }
            }

            // Skor 2: Beri bonus besar jika SEMUA kata kunci ditemukan
            if (count($keywords) > 1 && $matchedKeywords === count($keywords)) {
                $score += 30;
            }

            // Hanya masukkan artikel yang memiliki skor (ditemukan kecocokan)
            if ($score > 0) {
                $article['relevance_score'] = $score;
                $filteredResults[] = $article;
            }
        }

        return $filteredResults;
    }

    // Ganti fungsi sortArticlesByRelevance Anda dengan yang ini
    private function sortArticlesByRelevance($articles)
    {
        usort($articles, function ($a, $b) {
            // Prioritas 1: Urutkan berdasarkan skor relevansi tertinggi
            if ($a['relevance_score'] !== $b['relevance_score']) {
                return $b['relevance_score'] <=> $a['relevance_score'];
            }
            // Prioritas 2: Jika skor sama, urutkan berdasarkan tanggal terbaru
            return strtotime($b['pubDate']) <=> strtotime($a['pubDate']);
        });

        return $articles;
    }

    // Update method getNews untuk mendukung search
    public function getNews(Request $request)
    {
        // Jika ada parameter search query, redirect ke search
        if ($request->has('q') && !empty(trim($request->input('q')))) {
            return $this->searchNews($request);
        }

        $categorySlug = $request->input('category');
        $limitPerSource = (int)$request->input('limit_per_source', 50);

        // Cache key berdasarkan kategori dan limit
        $cacheKey = "rss_news_" . ($categorySlug ?: 'all') . "_limit_{$limitPerSource}";

        $articles = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($categorySlug, $limitPerSource) {
            $sources = config('newssources.rss_sources');
            $allArticles = [];

            if ($categorySlug) {
                // Jika ada kategori spesifik, cari di semua sumber yang memiliki kategori tersebut
                foreach ($sources as $sourceKey => $sourceData) {
                    if (isset($sourceData['feeds'][$categorySlug])) {
                        $feedUrl = $sourceData['feeds'][$categorySlug];
                        $sourceArticles = $this->parseRSSFeed($feedUrl, 1, $limitPerSource);

                        // Tambahkan informasi sumber dan kategori ke setiap artikel
                        foreach ($sourceArticles as &$article) {
                            $article['source_name'] = $sourceData['name'];
                            $article['source_key'] = $sourceKey;
                            $article['category'] = $categorySlug;
                        }

                        $allArticles = array_merge($allArticles, $sourceArticles);
                    }
                }

                // Jika tidak ada artikel ditemukan untuk kategori spesifik, 
                // coba cari dengan mapping kategori alternatif
                if (empty($allArticles)) {
                    $categoryMappings = $this->getCategoryMappings();

                    if (isset($categoryMappings[$categorySlug])) {
                        foreach ($categoryMappings[$categorySlug] as $sourceKey => $sourceCategoryKey) {
                            if (isset($sources[$sourceKey]['feeds'][$sourceCategoryKey])) {
                                $feedUrl = $sources[$sourceKey]['feeds'][$sourceCategoryKey];
                                $sourceArticles = $this->parseRSSFeed($feedUrl, 1, $limitPerSource);

                                foreach ($sourceArticles as &$article) {
                                    $article['source_name'] = $sources[$sourceKey]['name'];
                                    $article['source_key'] = $sourceKey;
                                    $article['category'] = $categorySlug;
                                }

                                $allArticles = array_merge($allArticles, $sourceArticles);
                            }
                        }
                    }
                }

                // Urutkan berdasarkan tanggal publikasi terbaru
                usort($allArticles, fn($a, $b) => strtotime($b['pubDate']) <=> strtotime($a['pubDate']));
            } else {
                // ================== LOGIKA BARU UNTUK HALAMAN UTAMA ==================
                // Ambil beberapa artikel dari SETIAP sumber berita
                foreach ($sources as $sourceKey => $sourceData) {
                    if (isset($sourceData['feeds']['all'])) {
                        $feedUrl = $sourceData['feeds']['all'];

                        // Ambil artikel dari satu sumber
                        $sourceArticles = $this->parseRSSFeed($feedUrl, 1, $limitPerSource);

                        // Tambahkan informasi sumber ke setiap artikel
                        foreach ($sourceArticles as &$article) {
                            $article['source_name'] = $sourceData['name'];
                            $article['source_key'] = $sourceKey;
                            $article['category'] = 'all';
                        }

                        // Gabungkan hasilnya ke array utama
                        $allArticles = array_merge($allArticles, $sourceArticles);
                    }
                }
                // Setelah semua sumber digabungkan, baru kita acak urutannya
                shuffle($allArticles);
                // ====================================================================
            }

            return $allArticles;
        });

        return response()->json([
            'data' => $articles,
            'category' => $categorySlug,
            'total' => count($articles),
            'message' => empty($articles) ? 'No articles found for this category' : 'Articles retrieved successfully'
        ]);
    }

    /**
     * Mendapatkan mapping kategori untuk kompatibilitas dengan berbagai sumber
     */
    private function getCategoryMappings()
    {
        return [
            // Bisnis
            'bisnis' => [
                'tempo' => 'bisnis',
                'cnbc' => 'market',
                'cnn' => 'ekonomi',
                'kompas' => 'ekonomi'
            ],

            // Edukasi - mapping ke kategori terdekat
            'edukasi' => [
                'cnn' => 'nasional',
                'kompas' => 'news',
                'antara' => 'all'
            ],

            // Ekonomi
            'ekonomi' => [
                'cnn' => 'ekonomi',
                'cnbc' => 'market',
                'kompas' => 'ekonomi',
                'antara' => 'ekonomi'
            ],

            // Gaya Hidup
            'gaya-hidup' => [
                'cnn' => 'gaya-hidup',
                'cnbc' => 'lifestyle',
                'kompas' => 'lifestyle'
            ],

            // Hiburan
            'hiburan' => [
                'cnn' => 'hiburan',
                'kompas' => 'hiburan'
            ],

            // Hukum
            'hukum' => [
                'antara' => 'hukum',
                'cnn' => 'nasional',
                'kompas' => 'nasional'
            ],

            // Internasional
            'internasional' => [
                'cnn' => 'internasional',
                'kompas' => 'internasional',
                'tempo' => 'dunia'
            ],

            // Nasional
            'nasional' => [
                'cnn' => 'nasional',
                'kompas' => 'nasional',
                'tempo' => 'nasional'
            ],

            // Olahraga
            'olahraga' => [
                'cnn' => 'olahraga',
                'kompas' => 'olahraga',
                'antara' => 'olahraga',
                'tempo' => 'bola'
            ],

            // Otomotif - mapping ke teknologi atau ekonomi
            'otomotif' => [
                'cnn' => 'teknologi',
                'cnbc' => 'tech',
                'kompas' => 'tekno',
                'antara' => 'tekno'
            ],

            // Pasar Modal
            'pasar-modal' => [
                'cnbc' => 'market',
                'cnbc' => 'investment',
                'cnn' => 'ekonomi',
                'kompas' => 'ekonomi'
            ],

            // Politik
            'politik' => [
                'antara' => 'politik',
                'cnn' => 'nasional',
                'kompas' => 'nasional',
                'tempo' => 'nasional'
            ],

            // Sains - mapping ke teknologi
            'sains' => [
                'cnn' => 'teknologi',
                'cnbc' => 'tech',
                'kompas' => 'tekno',
                'antara' => 'tekno',
                'tempo' => 'tekno'
            ],

            // Teknologi
            'teknologi' => [
                'cnn' => 'teknologi',
                'cnbc' => 'tech',
                'kompas' => 'tekno',
                'antara' => 'tekno',
                'tempo' => 'tekno'
            ],

            // Travel - mapping ke lifestyle atau gaya hidup
            'travel' => [
                'cnn' => 'gaya-hidup',
                'cnbc' => 'lifestyle',
                'kompas' => 'lifestyle'
            ],

            // Mapping balik untuk kompatibilitas
            'tech' => [
                'cnn' => 'teknologi',
                'cnbc' => 'tech',
                'kompas' => 'tekno',
                'antara' => 'tekno',
                'tempo' => 'tekno'
            ],
            'tekno' => [
                'cnn' => 'teknologi',
                'cnbc' => 'tech',
                'kompas' => 'tekno',
                'antara' => 'tekno',
                'tempo' => 'tekno'
            ],
            'bola' => [
                'cnn' => 'olahraga',
                'kompas' => 'olahraga',
                'antara' => 'olahraga',
                'tempo' => 'bola'
            ],
            'market' => [
                'cnn' => 'ekonomi',
                'cnbc' => 'market',
                'kompas' => 'ekonomi',
                'antara' => 'ekonomi'
            ],
            'lifestyle' => [
                'cnn' => 'gaya-hidup',
                'cnbc' => 'lifestyle',
                'kompas' => 'lifestyle'
            ],
            'dunia' => [
                'cnn' => 'internasional',
                'kompas' => 'internasional',
                'tempo' => 'dunia'
            ],
            'entertainment' => [
                'cnn' => 'hiburan',
                'kompas' => 'hiburan'
            ],
            'investment' => [
                'cnbc' => 'investment',
                'cnn' => 'ekonomi'
            ],
            'news' => [
                'kompas' => 'news',
                'cnn' => 'nasional'
            ]
        ];
    }

    /**
     * Mendapatkan daftar kategori yang sudah didefinisikan dengan mapping ke RSS sources
     */
    public function getCategories()
    {
        // Daftar kategori yang sudah didefinisikan aplikasi
        $predefinedCategories = [
            ['name' => 'Bisnis', 'slug' => 'bisnis'],
            ['name' => 'Edukasi', 'slug' => 'edukasi'],
            ['name' => 'Ekonomi', 'slug' => 'ekonomi'],
            ['name' => 'Gaya Hidup', 'slug' => 'gaya-hidup'],
            ['name' => 'Hiburan', 'slug' => 'hiburan'],
            ['name' => 'Hukum', 'slug' => 'hukum'],
            ['name' => 'Internasional', 'slug' => 'internasional'],
            ['name' => 'Nasional', 'slug' => 'nasional'],
            ['name' => 'Olahraga', 'slug' => 'olahraga'],
            ['name' => 'Otomotif', 'slug' => 'otomotif'],
            ['name' => 'Pasar Modal', 'slug' => 'pasar-modal'],
            ['name' => 'Politik', 'slug' => 'politik'],
            ['name' => 'Sains', 'slug' => 'sains'],
            ['name' => 'Teknologi', 'slug' => 'teknologi'],
            ['name' => 'Travel', 'slug' => 'travel']
        ];

        // Tambahkan informasi availability berdasarkan RSS sources
        $categoryMappings = $this->getCategoryMappings();

        foreach ($predefinedCategories as &$category) {
            $category['available'] = isset($categoryMappings[$category['slug']]);
            $category['sources'] = $categoryMappings[$category['slug']] ?? [];
        }

        return response()->json($predefinedCategories);
    }
    /**
     * Mendapatkan daftar sumber berita dan kategorinya.
     */
    public function getSources()
    {
        $sources = [];
        foreach ($this->rssSources as $key => $source) {
            $categories = [];
            foreach ($source['feeds'] as $catKey => $url) {
                $categories[] = [
                    'key' => $catKey,
                    'name' => ucfirst($catKey)
                ];
            }
            $sources[] = [
                'key' => $key,
                'name' => $source['name'],
                'categories' => $categories
            ];
        }
        return response()->json(['sources' => $sources]);
    }

    /**
     * Logika utama untuk mengambil dan mem-parsing RSS Feed.
     * (Versi Baru yang Lebih Baik)
     */
    private function parseRSSFeed($url, $page = 1, $limit = 50)
    {
        try {
            // --- PERBAIKAN: Tambahkan error handling untuk HTTP request ---
            $response = Http::timeout(10)->get($url); // Timeout 10 detik

            if (!$response->successful()) {
                Log::error('Gagal mengambil RSS Feed', [
                    'url' => $url,
                    'status' => $response->status()
                ]);
                return []; // Kembalikan array kosong jika request gagal
            }

            $xmlString = $response->body();
            // Ganti tag yang tidak standar jika ada
            $xmlString = str_replace('<media:content', '<content', $xmlString);
            $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);

            if ($xml === false) {
                Log::error('Gagal mem-parsing XML', ['url' => $url]);
                return [];
            }

            $articles = [];
            foreach ($xml->channel->item as $item) {
                $parsedItem = $this->parseRSSItem($item);
                if ($parsedItem) {
                    $articles[] = $parsedItem;
                }
            }
            return array_slice($articles, ($page - 1) * $limit, $limit);
        } catch (\Exception $e) {
            Log::error('Kesalahan fatal saat memproses RSS Feed', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Parse satu item dari feed menjadi format array yang konsisten.
     * Fungsi ini sudah mencakup pengambilan data interaksi.
     */
    private function parseRSSItem($item)
    {
        try {
            // --- Logika pengambilan gambar yang sudah diperbaiki ---
            $namespaces = $item->getNamespaces(true);
            $imageUrl = null;

            if (isset($item->enclosure) && strpos((string)$item->enclosure['type'], 'image') !== false) {
                $imageUrl = (string)$item->enclosure['url'];
            } elseif (isset($item->children($namespaces['media'])->content)) {
                $imageUrl = (string)$item->children($namespaces['media'])->content->attributes()->url;
            } elseif (isset($item->children($namespaces['content'])->encoded)) {
                preg_match('/<img[^>]+src="([^"]+)"/', (string)$item->children($namespaces['content'])->encoded, $matches);
                $imageUrl = $matches[1] ?? null;
            } elseif (preg_match('/<img[^>]+src="([^"]+)"/', (string)$item->description, $matches)) {
                $imageUrl = $matches[1] ?? null;
            }

            // --- Logika pengambilan data interaksi ---
            $articleUrl = (string)$item->link;
            $dbArticle = Article::where('url', $articleUrl)->first();

            $likesCount = $dbArticle ? $dbArticle->likes()->where('type', 'like')->count() : 0;
            $dislikesCount = $dbArticle ? $dbArticle->likes()->where('type', 'dislike')->count() : 0;
            $commentsCount = $dbArticle ? $dbArticle->comments()->count() : 0;

            // --- Menggabungkan semua data ---
            return [
                'title' => (string)$item->title,
                'link' => $articleUrl,
                'description' => strip_tags(html_entity_decode((string)$item->description)),
                'pubDate' => date('Y-m-d H:i:s', strtotime((string)$item->pubDate)),
                'image' => $imageUrl,
                'author' => (string)($item->author ?? $item->{'dc:creator'} ?? null),
                'likes_count' => $likesCount,
                'dislikes_count' => $dislikesCount,
                'comments_count' => $commentsCount,
            ];
        } catch (\Exception $e) {
            Log::warning('Gagal memproses satu item RSS', ['title' => (string)($item->title ?? 'N/A'), 'error' => $e->getMessage()]);
            return null;
        }
    }
}
