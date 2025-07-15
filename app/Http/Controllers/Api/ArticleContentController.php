<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Cache;

class ArticleContentController extends Controller
{
    private $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ];

    /**
     * Mengambil URL, melakukan web scraping, dan mengembalikan konten bersih.
     */
    public function fetch(Request $request)
    {
        $request->validate(['url' => 'required|url']);
        $url = $request->url;


        $cacheKey = 'article_content_' . md5($url);

        $cachedContent = Cache::get($cacheKey);

        if ($cachedContent) {
            Log::info('Returning cached content for URL: ' . $url);
            return response()->json([
                'success' => true,
                'data' => $cachedContent,
                'cached' => true
            ]);
        }

        try {
            Log::info('Starting fetch for URL: ' . $url);


            $html = $this->fetchWithMultipleStrategies($url);

            if (!$html) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengunduh halaman dari URL yang diberikan setelah mencoba beberapa metode.'
                ], 502);
            }

            Log::info('Successfully fetched HTML, length: ' . strlen($html));


            $crawler = new Crawler($html);


            $title = $this->extractTitle($crawler);


            $image = $this->extractImage($crawler, $url);


            $content = $this->extractContent($crawler, $url);

            if (empty($content)) {

                $content = $this->extractContentAlternative($crawler);
            }

            if (empty($content)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat menemukan konten utama artikel.',
                    'debug' => config('app.debug') ? [
                        'title_found' => !empty($title),
                        'html_length' => strlen($html),
                        'available_selectors' => $this->debugAvailableSelectors($crawler)
                    ] : null
                ], 404);
            }


            $cleanContent = $this->cleanAndFormatContent($content);


            $responseData = [
                'title' => $title,
                'content' => $cleanContent,
                'paragraphs' => $this->extractParagraphs($cleanContent),
                'image' => $image,
                'url' => $url,
                'word_count' => str_word_count(strip_tags($cleanContent)),
                'excerpt' => $this->generateExcerpt($cleanContent),
            ];


            Cache::put($cacheKey, $responseData, now()->addMinutes(60));

            Log::info('Content cached successfully for URL: ' . $url);

            return response()->json([
                'success' => true,
                'data' => $responseData,
                'cached' => false
            ]);
        } catch (\Exception $e) {
            Log::error('Gagal memproses scraping halaman', [
                'url' => $url,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses halaman artikel.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    private function extractImage(Crawler $crawler, string $url): ?string
    {
        $selectors = [
            'meta[property="og:image"]',
            'meta[name="twitter:image"]',
            'article img',
            '.post-content img',
            '.entry-content img',
            '.article-content img',
            '.content img',
            'img[src*="wp-content"]',
            'img[src*="uploads"]'
        ];

        foreach ($selectors as $selector) {
            try {
                if (strpos($selector, 'meta') === 0) {
                    $metaNode = $crawler->filter($selector);
                    if ($metaNode->count() > 0) {
                        $imageSrc = $metaNode->attr('content');
                        if (!empty($imageSrc)) {
                            return $this->normalizeImageUrl($imageSrc, $url);
                        }
                    }
                } else {
                    $imgNode = $crawler->filter($selector);
                    if ($imgNode->count() > 0) {
                        $imageSrc = $imgNode->first()->attr('src');
                        if (!empty($imageSrc)) {
                            return $this->normalizeImageUrl($imageSrc, $url);
                        }
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Normalize image URL to absolute URL
     */
    private function normalizeImageUrl(string $imageSrc, string $baseUrl): string
    {
        if (filter_var($imageSrc, FILTER_VALIDATE_URL)) {
            return $imageSrc;
        }

        $parsedUrl = parse_url($baseUrl);
        $scheme = $parsedUrl['scheme'] ?? 'https';
        $host = $parsedUrl['host'] ?? '';

        if (strpos($imageSrc, '//') === 0) {
            return $scheme . ':' . $imageSrc;
        }

        if (strpos($imageSrc, '/') === 0) {
            return $scheme . '://' . $host . $imageSrc;
        }

        return $scheme . '://' . $host . '/' . ltrim($imageSrc, '/');
    }

    /**
     * Try multiple strategies to fetch HTML
     */
    private function fetchWithMultipleStrategies(string $url): ?string
    {
        $strategies = [
            'fetchWithRandomUserAgent',
            'fetchWithDelayAndRetry',
            'fetchWithMinimalHeaders',
            'fetchWithMobileUserAgent'
        ];

        foreach ($strategies as $strategy) {
            try {
                Log::info("Trying strategy: $strategy");
                $html = $this->$strategy($url);
                if ($html && strlen($html) > 1000) {
                    Log::info("Strategy $strategy succeeded");
                    return $html;
                }
            } catch (\Exception $e) {
                Log::warning("Strategy $strategy failed: " . $e->getMessage());
                continue;
            }
        }

        return null;
    }

    /**
     * Fetch with random user agent
     */
    private function fetchWithRandomUserAgent(string $url): ?string
    {
        $userAgent = $this->userAgents[array_rand($this->userAgents)];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language: id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
                'Accept-Encoding: gzip, deflate, br',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: none',
                'Cache-Control: max-age=0',
            ],
            CURLOPT_ENCODING => '',
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($html === false || !empty($error) || $httpCode >= 400) {
            return null;
        }

        return $html;
    }

    /**
     * Fetch with delay and retry
     */
    private function fetchWithDelayAndRetry(string $url): ?string
    {
        sleep(2);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; NewsBot/1.0; +http://example.com/bot)',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: id,en;q=0.5',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1',
            ],
            CURLOPT_COOKIEJAR => storage_path('cookies.txt'),
            CURLOPT_COOKIEFILE => storage_path('cookies.txt'),
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($html !== false && $httpCode < 400) ? $html : null;
    }

    /**
     * Fetch with minimal headers
     */
    private function fetchWithMinimalHeaders(string $url): ?string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'curl/7.68.0',
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($html !== false && $httpCode < 400) ? $html : null;
    }

    /**
     * Fetch with mobile user agent
     */
    private function fetchWithMobileUserAgent(string $url): ?string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 40,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: id-ID,id;q=0.9',
                'Accept-Encoding: gzip, deflate',
            ],
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($html !== false && $httpCode < 400) ? $html : null;
    }

    /**
     * Extract content with alternative methods
     */
    private function extractContentAlternative(Crawler $crawler): string
    {

        $paragraphs = $crawler->filter('p');
        if ($paragraphs->count() > 3) {
            $content = '';
            $paragraphs->each(function (Crawler $node) use (&$content) {
                $text = trim($node->text());
                if (strlen($text) > 50) {
                    $content .= '<p>' . $text . '</p>';
                }
            });
            return $content;
        }


        $mainContent = $crawler->filter('main, #main, .main, #content, .content');
        if ($mainContent->count() > 0) {
            return $mainContent->first()->html();
        }

        return '';
    }

    /**
     * Debug available selectors
     */
    private function debugAvailableSelectors(Crawler $crawler): array
    {
        $selectors = [];


        $testSelectors = [
            'article',
            '.article',
            '#article',
            '.post-content',
            '.entry-content',
            '.content',
            '.article-content',
            '.article-body',
            'main',
            '#main',
            '.main'
        ];

        foreach ($testSelectors as $selector) {
            try {
                $count = $crawler->filter($selector)->count();
                if ($count > 0) {
                    $selectors[$selector] = $count;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return $selectors;
    }

    /**
     * Ekstrak title dari halaman
     */
    private function extractTitle(Crawler $crawler): string
    {
        $selectors = [
            'h1.post-title',
            'h1.entry-title',
            'h1.article-title',
            'h1.title',
            '.post-header h1',
            '.entry-header h1',
            'article h1',
            'h1',
            'meta[property="og:title"]',
            'title'
        ];

        foreach ($selectors as $selector) {
            try {
                if ($selector === 'meta[property="og:title"]') {
                    $metaNode = $crawler->filter($selector);
                    if ($metaNode->count() > 0) {
                        $title = $metaNode->attr('content');
                        if (!empty($title)) {
                            return trim($title);
                        }
                    }
                } else {
                    $titleNode = $crawler->filter($selector);
                    if ($titleNode->count() > 0) {
                        $title = trim($titleNode->first()->text());
                        if (!empty($title) && strlen($title) > 5) {
                            return $title;
                        }
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return 'Judul tidak ditemukan';
    }

    /**
     * Ekstrak konten artikel dari halaman
     */
    private function extractContent(Crawler $crawler, string $url): string
    {
        $domain = parse_url($url, PHP_URL_HOST);
        $selectors = $this->getSelectorsForDomain($domain);

        foreach ($selectors as $selector) {
            try {
                $contentNode = $crawler->filter($selector);
                if ($contentNode->count() > 0) {
                    $content = $contentNode->first()->html();
                    $textContent = trim(strip_tags($content));


                    if (strlen($textContent) > 100) {
                        return $content;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return '';
    }

    /**
     * Dapatkan selectors berdasarkan domain
     */
    private function getSelectorsForDomain(string $domain): array
    {
        $specificSelectors = [
            'cantika.com' => [
                '.content-inner [itemprop="articleBody"]',
                '.contents',
                'article .content-inner'
            ],
            'cnnindonesia.com' => [
                '.detail-text',
                '.detail-wrap .detail-text p',
                'article .detail-text'
            ],
            'cnbcindonesia.com' => [
                '.detail_text',
                '.detail_content .detail_text',
                'article .detail_text'
            ],
            'antaranews.com' => [
                '.post-content',
                '.simple-text',
                '.post-content p',
                'article .post-content'
            ],
            'liputan6.com' => [
                '.read-page--content-body',
                '.article-content-body',
                '.read-page--content p'
            ],
            'tempo.co' => [
                '.detail-content',
                '.artikel-content',
                '.detail-content p'
            ],
            'kompas.com' => [
                '.read__content',
                '.read__content p',
                'article .read__content'
            ],
            'detik.com' => [
                '.detail__body-text',
                '.itp_bodycontent',
                '.detail_text'
            ]
        ];

        $generalSelectors = [
            'article .post-content',
            'article .entry-content',
            'article .content',
            '.article-content',
            '.post-content',
            '.entry-content',
            '.main-content',
            '.content-body',
            'div[role="main"] .content',
            'main article',
            'article',
            '.article-body',
            '.post-body',
            '.content-area',
            '.text-content',
            '.article-text'
        ];

        $domainKey = str_replace('www.', '', $domain);
        $domainSelectors = $specificSelectors[$domainKey] ?? [];

        return array_merge($domainSelectors, $generalSelectors);
    }

    /**
     * Bersihkan dan format konten menjadi paragraf yang rapi
     */
    private function cleanAndFormatContent(string $content): string
    {
        try {
            $crawler = new Crawler($content);


            $unwantedSelectors = [
                'script',
                'style',
                'nav',
                'header',
                'footer',
                '.ads',
                '.advertisement',
                '.adsbygoogle',
                '[class*="ads"]',
                '.social-share',
                '.share-buttons',
                '[class*="share"]',
                '.related-articles',
                '.baca-juga',
                '.bacajuga',
                '.related-posts',
                '.comments',
                '.comment-form',
                '[class*="comment"]',
                '.sidebar',
                '.widget',
                '.breadcrumb',
                '.pagination',
                '.tags',
                '.tag',
                '.category-links',
                '.author-bio',
                '.author-info',
                '.newsletter-signup',
                '.popup',
                '.modal',
                'iframe',
                'embed',
                'object',
                '[id*="ad"]',
                '[class*="ad-"]',
                '.date',
                '.block-avatar',
                '.artikel-terkait',
                'strong:contains("Pilihan Editor")',
                'p:contains("Halo Sahabat")',
                'p:contains("Telegram")',
                'img',
                'span[itemscope]',
                '.alt-ilustrasi'
            ];

            foreach ($unwantedSelectors as $selector) {
                try {
                    $crawler->filter($selector)->each(function (Crawler $node) {
                        foreach ($node as $domNode) {
                            if ($domNode->parentNode) {
                                $domNode->parentNode->removeChild($domNode);
                            }
                        }
                    });
                } catch (\Exception $e) {
                    continue;
                }
            }


            $cleanContent = '';
            $paragraphs = $crawler->filter('p');

            if ($paragraphs->count() > 0) {
                $paragraphs->each(function (Crawler $node) use (&$cleanContent) {
                    $text = trim($node->text());


                    $unwantedPatterns = [
                        '/^Artikel Terkait:/i',
                        '/^Pilihan Editor:/i',
                        '/Halo Sahabat/i',
                        '/Telegram/i',
                        '/^\s*$/',
                        '/^Reporter\s*$/i',
                        '/^Antara\s*$/i',
                        '/^\d{1,2}\s+(Januari|Februari|Maret|April|Mei|Juni|Juli|Agustus|September|Oktober|November|Desember)/i'
                    ];

                    $shouldSkip = false;
                    foreach ($unwantedPatterns as $pattern) {
                        if (preg_match($pattern, $text)) {
                            $shouldSkip = true;
                            break;
                        }
                    }


                    if (!$shouldSkip && strlen($text) > 30 && !$this->isMetadataText($text)) {
                        $cleanContent .= '<p>' . $text . '</p>' . "\n";
                    }
                });
            }

            return trim($cleanContent);
        } catch (\Exception $e) {
            return $this->fallbackCleanContent($content);
        }
    }

    /**
     * Check if text is metadata (author, date, etc.)
     */
    private function isMetadataText(string $text): bool
    {
        $metadataPatterns = [
            '/^(Selasa|Senin|Rabu|Kamis|Jumat|Sabtu|Minggu),/i',
            '/^\d{1,2}\s+\w+\s+\d{4}/i',
            '/^Reporter$/i',
            '/^Antara$/i',
            '/WIB\s*$/i',
            '/^CANTIKA\.COM/i',
            '/^Jakarta\s*-/i'
        ];

        foreach ($metadataPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fallback content cleaning method
     */
    private function fallbackCleanContent(string $content): string
    {

        $content = strip_tags($content, '<p>');


        $content = preg_replace('/\s+/', ' ', $content);


        $sentences = preg_split('/(?<=[.!?])\s+/', $content);
        $cleanContent = '';

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (strlen($sentence) > 30 && !$this->isMetadataText($sentence)) {
                $cleanContent .= '<p>' . $sentence . '</p>' . "\n";
            }
        }

        return trim($cleanContent);
    }

    /**
     * Extract paragraphs as array
     */
    private function extractParagraphs(string $content): array
    {
        $paragraphs = [];

        try {
            $crawler = new Crawler($content);
            $crawler->filter('p')->each(function (Crawler $node) use (&$paragraphs) {
                $text = trim($node->text());
                if (!empty($text)) {
                    $paragraphs[] = $text;
                }
            });
        } catch (\Exception $e) {

            preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $content, $matches);
            foreach ($matches[1] as $match) {
                $text = trim(strip_tags($match));
                if (!empty($text)) {
                    $paragraphs[] = $text;
                }
            }
        }

        return $paragraphs;
    }

    /**
     * Generate excerpt dari konten
     */
    private function generateExcerpt(string $content, int $length = 200): string
    {
        $text = strip_tags($content);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length) . '...';
    }
}
