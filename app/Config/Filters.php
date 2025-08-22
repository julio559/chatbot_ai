<?php

namespace Config;

use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\Cors;
use CodeIgniter\Filters\CSRF;
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
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'cors'          => Cors::class,
        'forcehttps'    => ForceHTTPS::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,

        // auth do app
        'auth'          => \App\Filters\AuthFilter::class,
    ];

    public array $required = [
        'before' => ['forcehttps','pagecache'],
        'after'  => ['pagecache','performance','toolbar'],
    ];

    public array $globals = [
        'before' => [
            'csrf' => [
                'except' => [
                    // Webhooks (sem CSRF, usam x-api-key)
                    'webhook',
                    'webhook/*',
                    'webhook-sessao/receive',
                    'webhook-sessao/*',

                    // Gateway WhatsApp
                    'whatsapp/gw',
                    'whatsapp/bind',
                    'whatsapp/reset/*',
                    'whatsapp/status/*',
                    'whatsapp/qr/*',
                    'whatsapp/set-webhook/*',
                    'whatsapp/delete/*',

                    // Se você NÃO vai colocar csrf_field() nos forms de Paciente,
                    // libere as ações abaixo:
                    'paciente/atualizar',
                    'paciente/atualizar/*',
                    'paciente/excluir',
                    'paciente/excluir/*',
                ],
            ],
        ],
        'after' => [
            // 'toolbar',
        ],
    ];

    public array $methods = [
        // você pode aplicar filtros por método HTTP aqui
    ];

    public array $filters = [
        // Ex.: 'auth' => ['before' => ['dashboard*','paciente*','kanban*','chat*','etapas*','notificacoes*','agendamentos*','whatsapp*']],
    ];
}
