<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Security extends BaseConfig
{
    public string $csrfProtection = 'cookie';
    public bool   $tokenRandomize = false;
    public string $tokenName      = 'csrf_test_name';
    public string $headerName     = 'X-CSRF-TOKEN';
    public string $cookieName     = 'csrf_cookie_name';
    public int    $expires        = 7200;
    public bool   $regenerate     = true;
    public bool   $redirect       = (ENVIRONMENT === 'production');

    /**
     * --------------------------------------------------------------------------
     * Exclude URIs from CSRF Protection
     * --------------------------------------------------------------------------
     *
     * Essas rotas NÃO vão passar pelo CSRF.
     */
    public array $excludeURIs = [
        'webhook',
        'webhook/*',

        // se quiser liberar os endpoints AJAX também:
        'whatsapp/*',
        'kanban/*',
        'tarefas/*',
        'paciente/*',
        'agendamentos/*',
        'notificacoes/*',
        'configuracaoia/*',
        'painel/*',
        'chat/*',
        'etapas/*',
        'api/*',
        'upload/*',
        'kanban/lead-files/*',
    ];
}
