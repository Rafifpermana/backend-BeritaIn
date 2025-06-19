<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Vedmant\FeedReader\Facades\FeedReader;
use App\Models\Category;

class RSSNewsController extends Controller
{
    private $rssSources;

    public function __construct()
    {
        $this->rssSources = config('newssources.rss_sources', []);
    }

    public function getHomePageFeed(Request $request)
    {
        $limitPerSource = (int)$request->input('limit_per_source', 8);
        $cacheKey = "rss_homepage_feed_limit_{$limitPerSource}";

        $articles = Cache::remember($cacheKey, now()->addMinutes(20), function () use ($limitPerSource) {
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

    // Tambahkan method baru untuk search functionality
    public function searchNews(Request $request)
    {
        $query = $request->input('q', '');
        $categorySlug = $request->input('category');
        $limitPerSource = (int)$request->input('limit_per_source', 20);

        if (empty(trim($query))) {
            return response()->json([
                'data' => [],
                'message' => 'Search query is required',
                'total' => 0
            ], 400);
        }

        $cacheKey = "rss_search_" . md5($query) . "_" . ($categorySlug ?: 'all') . "_limit_{$limitPerSource}";
        $articles = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($query, $categorySlug, $limitPerSource) {
            $sources = config('newssources.rss_sources');
            $allArticles = [];

            // Get all available categories from the database
            $availableCategories = Category::pluck('slug')->toArray();

            if ($categorySlug && in_array($categorySlug, $availableCategories)) {
                foreach ($sources as $sourceKey => $sourceData) {
                    if (isset($sourceData['feeds'][$categorySlug])) {
                        $feedUrl = $sourceData['feeds'][$categorySlug];
                        $sourceArticles = $this->parseRSSFeed($feedUrl, 1, 100);
                        foreach ($sourceArticles as &$article) {
                            $article['source_name'] = $sourceData['name'];
                            $article['source_key'] = $sourceKey;
                            $article['category'] = $categorySlug;
                        }
                        $allArticles = array_merge($allArticles, $sourceArticles);
                    }
                }

                // If no articles found, try category mapping
                if (empty($allArticles)) {
                    $categoryMappings = $this->getCategoryMappings();
                    if (isset($categoryMappings[$categorySlug])) {
                        foreach ($categoryMappings[$categorySlug] as $sourceKey => $mappedCategory) {
                            if (isset($sources[$sourceKey]['feeds'][$mappedCategory])) {
                                $feedUrl = $sources[$sourceKey]['feeds'][$mappedCategory];
                                $sourceArticles = $this->parseRSSFeed($feedUrl, 1, 100);
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
            } else {
                // Search across all categories
                foreach ($sources as $sourceKey => $sourceData) {
                    if (isset($sourceData['feeds']['all'])) {
                        $feedUrl = $sourceData['feeds']['all'];
                        $sourceArticles = $this->parseRSSFeed($feedUrl, 1, 100);
                        foreach ($sourceArticles as &$article) {
                            $article['source_name'] = $sourceData['name'];
                            $article['source_key'] = $sourceKey;
                            $article['category'] = 'all';
                        }
                        $allArticles = array_merge($allArticles, $sourceArticles);
                    }
                }
            }

            // Filter and sort
            $filteredArticles = $this->filterArticlesByQuery($allArticles, $query);
            $sortedArticles = $this->sortArticlesByRelevance($filteredArticles, $query);

            return array_slice($sortedArticles, 0, $limitPerSource);
        });

        return response()->json([
            'data' => $articles,
            'query' => $query,
            'category' => $categorySlug,
            'total' => count($articles),
            'message' => empty($articles) ? 'No articles found for your search' : 'Search results retrieved successfully'
        ]);
    }

    // Method untuk filter artikel berdasarkan query
    private function filterArticlesByQuery($articles, $query)
    {
        $query = strtolower(trim($query));
        $keywords = explode(' ', $query);

        $filteredArticles = [];

        foreach ($articles as $article) {
            $title = strtolower($article['title']);
            $description = strtolower($article['description']);
            $content = $title . ' ' . $description;

            $relevanceScore = 0;
            $matchCount = 0;

            // Cek exact match untuk seluruh query
            if (strpos($content, $query) !== false) {
                $relevanceScore += 10;
                $matchCount++;
            }

            // Cek individual keywords
            foreach ($keywords as $keyword) {
                $keyword = trim($keyword);
                if (strlen($keyword) > 2) { // Skip kata terlalu pendek
                    if (strpos($title, $keyword) !== false) {
                        $relevanceScore += 5; // Title match lebih tinggi
                        $matchCount++;
                    } elseif (strpos($description, $keyword) !== false) {
                        $relevanceScore += 2;
                        $matchCount++;
                    }
                }
            }

            // Artikel harus match minimal 1 keyword untuk dimasukkan
            if ($matchCount > 0) {
                $article['relevance_score'] = $relevanceScore;
                $article['match_count'] = $matchCount;
                $filteredArticles[] = $article;
            }
        }

        return $filteredArticles;
    }

    // Method untuk sort artikel berdasarkan relevansi
    private function sortArticlesByRelevance($articles, $query)
    {
        usort($articles, function ($a, $b) {
            // Prioritas pertama: relevance score
            if ($a['relevance_score'] != $b['relevance_score']) {
                return $b['relevance_score'] <=> $a['relevance_score'];
            }

            // Prioritas kedua: match count
            if ($a['match_count'] != $b['match_count']) {
                return $b['match_count'] <=> $a['match_count'];
            }

            // Prioritas ketiga: tanggal terbaru
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
        $limitPerSource = (int)$request->input('limit_per_source', 20);

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
                // Jika tidak ada kategori, ambil dari feed 'all' semua sumber
                foreach ($sources as $sourceKey => $sourceData) {
                    if (isset($sourceData['feeds']['all'])) {
                        $feedUrl = $sourceData['feeds']['all'];
                        $sourceArticles = $this->parseRSSFeed($feedUrl, 1, $limitPerSource);

                        foreach ($sourceArticles as &$article) {
                            $article['source_name'] = $sourceData['name'];
                            $article['source_key'] = $sourceKey;
                            $article['category'] = 'all';
                        }

                        $allArticles = array_merge($allArticles, $sourceArticles);
                    }
                }

                // Acak untuk halaman utama
                shuffle($allArticles);
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
    private function parseRSSFeed($feedUrl, $page, $limit)
    {
        try {
            $response = Http::timeout(15)->get($feedUrl);

            if ($response->failed()) {
                Log::error('Gagal mengambil RSS feed', ['url' => $feedUrl, 'status' => $response->status()]);
                return [];
            }

            $xmlContent = $response->body();
            // IMPROVEMENT: Membersihkan XML dari karakter yang tidak valid sebelum parsing
            $xmlContent = preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '', $xmlContent);

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOCDATA);

            if ($xml === false) {
                Log::error('Gagal parsing XML', ['url' => $feedUrl, 'errors' => libxml_get_errors()]);
                libxml_clear_errors();
                return [];
            }

            $articles = [];
            // Memeriksa apakah ada item di dalam channel atau langsung di root
            $items = $xml->channel->item ?? $xml->item ?? [];
            foreach ($items as $item) {
                $parsedItem = $this->parseRSSItem($item);
                if ($parsedItem) {
                    $articles[] = $parsedItem;
                }
            }

            // Terapkan paginasi manual
            $offset = ($page - 1) * $limit;
            return array_slice($articles, $offset, $limit);
        } catch (\Exception $e) {
            Log::error('Error saat memproses RSS Feed', ['url' => $feedUrl, 'message' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Parse individual RSS item.
     * (Versi Baru yang Lebih Baik)
     */
    private function parseRSSItem($item)
    {
        try {
            // Mengakses namespace untuk media, content, dll.
            $namespaces = $item->getNamespaces(true);
            $media = $item->children($namespaces['media'] ?? null);
            $content = $item->children($namespaces['content'] ?? null);

            $imageUrl = null;
            // Mencari gambar dari berbagai kemungkinan tag
            if (isset($item->enclosure) && strpos((string)$item->enclosure['type'], 'image') !== false) {
                $imageUrl = (string)$item->enclosure['url'];
            } elseif (isset($media->content)) {
                $imageUrl = (string)$media->content->attributes()->url;
            } elseif (isset($content->encoded)) {
                preg_match('/<img[^>]+src="([^"]+)"/', (string)$content->encoded, $matches);
                $imageUrl = $matches[1] ?? null;
            } elseif (preg_match('/<img[^>]+src="([^"]+)"/', (string)$item->description, $matches)) {
                $imageUrl = $matches[1] ?? null;
            }

            return [
                'title' => (string)$item->title,
                'link' => (string)$item->link,
                'description' => strip_tags(html_entity_decode((string)$item->description)),
                'pubDate' => date('Y-m-d H:i:s', strtotime((string)$item->pubDate)),
                'image' => $imageUrl,
                'author' => (string)($item->author ?? $item->{'dc:creator'} ?? null),
            ];
        } catch (\Exception $e) {
            Log::warning('Gagal memproses satu item RSS', ['title' => (string)($item->title ?? 'N/A'), 'error' => $e->getMessage()]);
            return null; // Lewati item yang error agar tidak menghentikan proses
        }
    }
}
