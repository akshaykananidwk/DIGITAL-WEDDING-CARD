<?php
/**
 * Non-secret application defaults.
 *
 * Anything sensitive (database credentials, app key, API tokens) is written by
 * the installer to the secret configuration file and merged over these values.
 */

declare(strict_types=1);

return [
    'app' => [
        'name'        => 'Shubh Kankotri',
        'tagline'     => 'Digital Invitation Cards for Every Indian Celebration',
        'url'         => null,          // auto-detected when null
        'key'         => null,          // set by installer
        'debug'       => false,
        'locale'      => 'en',
        'locales'     => ['en' => 'English', 'gu' => 'ગુજરાતી', 'hi' => 'हिन्दी'],
        'timezone'    => 'Asia/Kolkata',
        'version'     => null,          // resolved from version.json
        'maintenance' => false,
    ],

    'database' => [
        'driver'    => 'mysql',
        'host'      => '127.0.0.1',
        'port'      => 3306,
        'database'  => '',
        'username'  => '',
        'password'  => '',
        'charset'   => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix'    => '',
        'socket'    => null,
    ],

    'session' => [
        'name'      => 'inv_session',
        'lifetime'  => 7200,            // seconds of inactivity before logout
        'driver'    => 'file',          // file | database
        'path'      => null,            // defaults to storage/sessions
        'same_site' => 'Lax',
        'rotate'    => 1800,            // re-generate the id every 30 minutes
    ],

    'security' => [
        'login_max_attempts'   => 5,
        'login_decay_minutes'  => 15,
        'password_min_length'  => 8,
        'api_rate_limit'       => 120,  // requests / minute / identity
        'public_rate_limit'    => 300,
        'csp_enabled'          => true,
        'csp_report_only'      => false,
        'force_https'          => false,
        'trusted_proxies'      => [],
    ],

    'uploads' => [
        'max_image_size'  => 8388608,   // 8 MB
        'max_music_size'  => 10485760,  // 10 MB
        'max_photos'      => 30,
        'image_mimes'     => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        'audio_mimes'     => ['audio/mpeg', 'audio/mp3', 'audio/ogg'],
        'font_mimes'      => ['font/ttf', 'font/otf', 'application/font-sfnt', 'application/octet-stream'],
        'image_max_width' => 2000,
        'thumb_width'     => 480,
        'webp_quality'    => 82,
        'jpeg_quality'    => 86,
    ],

    'pdf' => [
        'engine'       => 'auto',       // auto | builtin | mpdf | dompdf
        'paper'        => 'A4',
        'margin'       => 12,
        'dpi'          => 150,
        'default_font' => 'NotoSans',
    ],

    'ai' => [
        'enabled'     => false,
        'provider'    => 'gemini',
        'endpoint'    => 'https://generativelanguage.googleapis.com/v1beta/models',
        'model'       => 'gemini-2.0-flash',
        'temperature' => 0.7,
        'max_tokens'  => 2048,
        'timeout'     => 30,
        'daily_limit' => 100,
    ],

    'update' => [
        'api_base'        => 'https://api.github.com',
        'codeload_base'   => 'https://codeload.github.com',
        'timeout'         => 60,
        'keep_backups'    => 5,
        'protected_paths' => [
            '.env',
            '.env.example',
            'storage',
            'uploads',
            'config',
            '.htaccess',
            '.user.ini',
            'php.ini',
            'robots.txt',
            'google*.html',
            '.well-known',
        ],
    ],

    'analytics' => [
        'store_ip'          => false,   // hashed only, never raw
        'unique_window'     => 86400,
        'aggregate_after'   => 90,      // days before raw rows are aggregated away
    ],

    'cache' => [
        'driver' => 'file',
        'ttl'    => 600,
    ],

    'mail' => [
        'driver'       => 'mail',       // mail | smtp | log
        'from_address' => null,
        'from_name'    => null,
        'smtp'         => [
            'host'       => '',
            'port'       => 587,
            'username'   => '',
            'password'   => '',
            'encryption' => 'tls',
            'timeout'    => 15,
        ],
    ],

    'features' => [
        'registration'        => true,
        'ai_generator'        => true,
        'ai_recommendations'  => true,
        'rsvp'                => true,
        'pdf_export'          => true,
        'music'               => true,
        'premium_templates'   => false, // monetisation ready, off by default
        'watermark'           => false,
        'custom_domain'       => false,
        'advanced_analytics'  => true,
        'demo_data'           => true,
    ],
];
