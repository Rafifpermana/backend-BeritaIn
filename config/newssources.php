<?php

return [
    'rss_sources' => [
        'cnn' => [
            'name' => env('RSS_CNN_NAME', 'CNN Indonesia'),
            'feeds' => [
                'all' => env('RSS_CNN_ALL'),
                'nasional' => env('RSS_CNN_NASIONAL'),
                'internasional' => env('RSS_CNN_INTERNASIONAL'),
                'ekonomi' => env('RSS_CNN_EKONOMI'),
                'olahraga' => env('RSS_CNN_OLAHRAGA'),
                'teknologi' => env('RSS_CNN_TEKNOLOGI'),
                'hiburan' => env('RSS_CNN_HIBURAN'),
                'gaya-hidup' => env('RSS_CNN_GAYA_HIDUP'),
            ]
        ],
        'cnbc' => [
            'name' => env('RSS_CNBC_NAME', 'CNBC Indonesia'),
            'feeds' => [
                'all' => env('RSS_CNBC_ALL'),
                'market' => env('RSS_CNBC_MARKET'),
                'investment' => env('RSS_CNBC_INVESTMENT'),
                'tech' => env('RSS_CNBC_TECH'),
                'lifestyle' => env('RSS_CNBC_LIFESTYLE'),
            ]
        ],
        'antara' => [
            'name' => env('RSS_ANTARA_NAME', 'Antara News'),
            'feeds' => [
                'all' => env('RSS_ANTARA_ALL'),
                'politik' => env('RSS_ANTARA_POLITIK'),
                'hukum' => env('RSS_ANTARA_HUKUM'),
                'ekonomi' => env('RSS_ANTARA_EKONOMI'),
                'olahraga' => env('RSS_ANTARA_OLAHRAGA'),
                'tekno' => env('RSS_ANTARA_TEKNO'),
            ]
        ],
        'tempo' => [
            'name' => env('RSS_TEMPO_NAME', 'Tempo.co'),
            'feeds' => [
                'all' => env('RSS_TEMPO_ALL'),
                'nasional' => env('RSS_TEMPO_NASIONAL'),
                'bisnis' => env('RSS_TEMPO_BISNIS'),
                'dunia' => env('RSS_TEMPO_DUNIA'),
                'bola' => env('RSS_TEMPO_BOLA'),
                'tekno' => env('RSS_TEMPO_TEKNO'),
            ]
        ],
        'kompas' => [
            'name' => env('RSS_KOMPAS_NAME', 'Kompas.com'),
            'feeds' => [
                'all' => env('RSS_KOMPAS_ALL'),
                'news' => env('RSS_KOMPAS_NEWS'),
                'nasional' => env('RSS_KOMPAS_NASIONAL'),
                'olahraga' => env('RSS_KOMPAS_OLAHRAGA'),
                'ekonomi' => env('RSS_KOMPAS_EKONOMI'),
                'tekno' => env('RSS_KOMPAS_TEKNO'),
                'hiburan' => env('RSS_KOMPAS_HIBURAN'),
                'lifestyle' => env('RSS_KOMPAS_LIFESTYLE'),
            ]
        ],
    ]
];
