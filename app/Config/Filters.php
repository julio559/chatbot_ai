<?php

namespace Config;

use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\Cors;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseFilters
{
    public array $aliases = [
        // sem 'csrf' aqui
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'cors'          => Cors::class,
        'forcehttps'    => ForceHTTPS::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,
        'auth'          => \App\Filters\AuthFilter::class,
    ];

    public array $required = [
        'before' => [
            // 'forcehttps',
        ],
        'after'  => [
            // 'pagecache',
            // 'performance',
            // 'toolbar',
        ],
    ];

    public array $globals = [
        'before' => [
            'cors',
            // nada de csrf aqui
        ],
        'after' => [
            // nada de csrf aqui
        ],
    ];

    // desliga CSRF em todos os métodos
    public array $methods = [
        // vazio
    ];

    public array $filters = [
        // nada de csrf aqui
        // 'auth' => ['before' => ['dashboard*','paciente*','kanban*','chat*','etapas*','notificacoes*','agendamentos*','whatsapp*']],
    ];
}
