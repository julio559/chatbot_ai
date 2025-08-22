<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
$routes->get('/home/metrics', 'Home::metrics');
$routes->get('chat', 'Chat::index');
$routes->get('paciente', 'Paciente::index');
$routes->post('/webhook', 'Webhook::index');
$routes->post('paciente/atualizar/(:num)', 'Paciente::atualizar/$1');
$routes->get('configuracaoia', 'ConfiguracaoIA::index');
$routes->post('configuracaoia/salvar', 'ConfiguracaoIA::salvar');
$routes->post('configuracaoia/testar', 'ConfiguracaoIA::testar');
$routes->get('painel/aguardando', 'Painel::aguardando');
$routes->get('/kanban', 'Kanban::index');
$routes->post('/kanban/atualizarEtapa', 'Kanban::atualizarEtapa');
$routes->get ('etapas',             'CriarEtapas::index');                  // lista / view
$routes->post('etapas/salvar',      'CriarEtapas::criarOuAtualizarEtapa');  // form da view
$routes->post('etapas/excluir',     'CriarEtapas::excluirEtapa');           // form da view
$routes->post('etapas/ordenar',     'CriarEtapas::ordenar');  
$routes->post('/configuracaoia/testarsequenciareal', 'ConfiguracaoIa::testarSequenciaReal');
$routes->post('/configuracaoia/testarchat', 'ConfiguracaoIa::testarChatSimulado');
$routes->get('chat', 'Chat::index');
$routes->get('chat/contacts', 'Chat::contacts');
$routes->get('chat/messages/(:segment)', 'Chat::messages/$1');
$routes->post('chat/send', 'Chat::send');
$routes->get('etapas', 'Etapas::index');
$routes->post('etapas/salvar', 'Etapas::save');          // cria ou atualiza (se vier id)
$routes->post('etapas/(:num)/excluir', 'Etapas::delete/$1');
// app/Config/Routes.php
$routes->post('configuracaoia/testarchat', 'ConfiguracaoIA::testarchat');
// app/Config/Routes.php
$routes->post('etapas/ordenar', 'Etapas::ordenar');          // drag-and-drop
$routes->post('etapas/(:num)/up', 'Etapas::moverCima/$1');   // botão ↑
$routes->post('etapas/(:num)/down', 'Etapas::moverBaixo/$1');// botão ↓
// app/Config/Routes.php
$routes->post('kanban/atualizarEtapa', 'Kanban::atualizarEtapa'); // já tinha

// Tags
$routes->get('kanban/tags', 'Kanban::tags');                       // lista todas as tags
$routes->post('kanban/tags', 'Kanban::criarTag');                  // cria tag

// Tags por lead
$routes->get('kanban/lead-tags/(:segment)', 'Kanban::leadTags/$1');          // lista tags do lead + todas
$routes->post('kanban/lead-tags/(:segment)', 'Kanban::salvarLeadTags/$1');   // salva tags do lead
// app/Config/Routes.php
$routes->get('kanban/lead-detalhes/(:segment)', 'Kanban::leadDetalhes/$1');
$routes->post('kanban/lead-nota/(:segment)', 'Kanban::salvarNota/$1');
// app/Config/Routes.php
$routes->get('kanban/lead-schedules/(:segment)', 'Kanban::listarAgendamentos/$1');     // lista agendamentos do lead
$routes->post('kanban/lead-schedules/(:segment)', 'Kanban::agendarMensagem/$1');       // cria agendamento
$routes->post('kanban/lead-schedules/cancelar/(:num)', 'Kanban::cancelarAgendamento/$1'); // cancela

// (opcional, se usar HTTP em vez do comando CLI)
$routes->get('cron/disparar-agendadas', 'Cron::dispararAgendadas'); // proteger com token na querystring
$routes->get('agendamentos', 'Agendamentos::index');                       // página
$routes->get('agendamentos/list', 'Agendamentos::list');                   // JSON (tabela)
$routes->post('agendamentos/update/(:num)', 'Agendamentos::update/$1');    // editar
$routes->post('agendamentos/delete/(:num)', 'Agendamentos::delete/$1');    // excluir/cancelar
// Tela e APIs de Números para Notificação
$routes->get('notificacoes', 'Notificacoes::index');
$routes->get('notificacoes/list', 'Notificacoes::list');         // JSON
$routes->post('notificacoes/save', 'Notificacoes::save');        // create/update
$routes->post('notificacoes/delete/(:num)', 'Notificacoes::delete/$1');

// (Opcional) Regras por etapa
$routes->post('notificacoes/regra/save', 'Notificacoes::saveRule');
$routes->get('notificacoes/regras', 'Notificacoes::rules');      // JSON

$routes->group('configuracaoia', static function ($routes) {
    $routes->get('/', 'ConfiguracaoIA::index');
    $routes->post('salvar', 'ConfiguracaoIA::salvar');

    $routes->get('historicoTeste', 'ConfiguracaoIA::historicoTeste');
    $routes->post('testarchat', 'ConfiguracaoIA::testarchat');

    // limpar histórico (sessão + banco)
    $routes->post('limparHistoricoTeste', 'ConfiguracaoIA::limparHistoricoTeste');

    // atualizar etapa no topo do chat
    $routes->post('atualizarEtapaTeste', 'ConfiguracaoIA::atualizarEtapaTeste');

    // <<< alias para links antigos
    $routes->match(['get','post'], 'testarChatSimulado', 'ConfiguracaoIA::testarChatSimulado');
});

$routes->get('auth', 'Auth::index');
$routes->post('auth/login', 'Auth::login');
$routes->post('auth/register', 'Auth::register');
$routes->get('logout', 'Auth::logout');



$routes->group('whatsapp', ['namespace' => 'App\Controllers'], static function($routes) {
    $routes->get('/', 'Whatsapp::index');
    $routes->get('webhook-base', 'Whatsapp::webhookBase');

    $routes->post('bind', 'Whatsapp::bind');
    $routes->post('save', 'Whatsapp::bind'); // alias opcional

    $routes->get('qr/(:num)', 'Whatsapp::qr/$1');
    $routes->get('status/(:num)', 'Whatsapp::status/$1');
    $routes->post('logout/(:num)', 'Whatsapp::logout/$1');

    $routes->post('webhook/(:num)', 'Whatsapp::setWebhook/$1');

    $routes->delete('delete/(:num)', 'Whatsapp::delete/$1');
    $routes->post('delete/(:num)', 'Whatsapp::delete/$1'); // spoof via _method=DELETE
});

$routes->get('configuracaoia/etapas', 'ConfiguracaoIA::etapas');






$routes->get ('/etapa',                'CriarEtapas::index');
$routes->get ('/etapa/listar',         'CriarEtapas::listar');            // GET (lista somente nomes)
$routes->post('/etapa/criar_ou_atualizar', 'CriarEtapas::criarOuAtualizarEtapa');
$routes->post('/etapa/excluir',        'CriarEtapas::excluirEtapa');
$routes->get ('/aprendizagem',           'Aprendizagem::index');
$routes->get ('/aprendizagem/listar',    'Aprendizagem::listar');
$routes->get ('/aprendizagem/obter/(:num)', 'Aprendizagem::obter/$1');
$routes->post('/aprendizagem/salvar',    'Aprendizagem::salvar');
$routes->post('/aprendizagem/excluir',   'Aprendizagem::excluir');
$routes->get ('/aprendizagem/base',      'Aprendizagem::base'); // para injetar na IA
$routes->get('kanban', 'Kanban::index');

$routes->post('kanban/atualizarEtapa', 'Kanban::atualizarEtapa');

$routes->get('kanban/tags', 'Kanban::tags');
$routes->post('kanban/tags', 'Kanban::criarTag');

$routes->get('kanban/lead-tags/(:segment)', 'Kanban::leadTags/$1');
$routes->post('kanban/lead-tags/(:segment)', 'Kanban::salvarLeadTags/$1');

$routes->get('kanban/lead-detalhes/(:segment)', 'Kanban::leadDetalhes/$1');
$routes->post('kanban/lead-nota/(:segment)', 'Kanban::salvarNota/$1');

$routes->get('kanban/lead-schedules/(:segment)', 'Kanban::listarAgendamentos/$1');
$routes->post('kanban/lead-schedules/(:segment)', 'Kanban::agendarMensagem/$1');
$routes->post('kanban/lead-schedules/cancelar/(:num)', 'Kanban::cancelarAgendamento/$1');

// ⚙️ Config de coluna (requer implementar os 3 métodos abaixo)
$routes->get('kanban/etapa-config/(:segment)', 'Kanban::etapaConfig/$1');
$routes->post('kanban/etapa-config/(:segment)', 'Kanban::salvarEtapaConfig/$1');
$routes->post('kanban/etapa-config/teste/(:segment)', 'Kanban::testeEtapaConfig/$1');
// Anexos do lead
$routes->get ('kanban/lead-files/(:segment)',            'Kanban::listarArquivos/$1');
$routes->post('kanban/lead-files/(:segment)',            'Kanban::uploadArquivo/$1');
$routes->get ('kanban/lead-files/download/(:num)',       'Kanban::baixarArquivo/$1');
$routes->post('kanban/lead-files/delete/(:num)',         'Kanban::excluirArquivo/$1');


// --- Tarefas ---
$routes->group('tarefas', static function ($r) {
    $r->get('/',        'Tarefas::index');
    $r->get('listar',   'Tarefas::listar');
    $r->post('salvar',  'Tarefas::salvar');
    $r->post('concluir','Tarefas::concluir');
    $r->post('excluir', 'Tarefas::excluir');
    $r->post('ordenar', 'Tarefas::ordenar');
});

$routes->group('', ['namespace' => 'App\Controllers'], static function($r) {
    // Painel/integração
    $r->post('whatsapp/gw', 'Whatsapp::gw');
    $r->post('whatsapp/bind', 'Whatsapp::bind');
    $r->post('whatsapp/reset/(:num)', 'Whatsapp::reset/$1');
    $r->get('whatsapp/status/(:num)', 'Whatsapp::status/$1');
    $r->get('whatsapp/qr/(:num)', 'Whatsapp::qr/$1');
    $r->post('whatsapp/set-webhook/(:num)', 'Whatsapp::setWebhook/$1');
    $r->delete('whatsapp/delete/(:num)', 'Whatsapp::delete/$1');

    // **Webhook** que o Node chama
    $r->post('webhook-sessao/receive', 'WebhookSessao::receive');
    // (se quiser usar o controlador “Webhook” mais completo)
    $r->post('webhook', 'Webhook::index');
});

