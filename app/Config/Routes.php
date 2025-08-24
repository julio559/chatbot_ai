<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Home
$routes->get('/', 'Home::index');
$routes->get('/home/metrics', 'Home::metrics');

// Chat
$routes->get('chat', 'Chat::index');
$routes->get('chat/contacts', 'Chat::contacts');
$routes->get('chat/messages/(:segment)', 'Chat::messages/$1');
$routes->post('chat/send', 'Chat::send');

// Paciente
$routes->get('paciente', 'Paciente::index');
$routes->post('paciente/atualizar/(:num)', 'Paciente::atualizar/$1');

// Configuração IA
$routes->get('configuracaoia', 'ConfiguracaoIA::index');
$routes->post('configuracaoia/salvar', 'ConfiguracaoIA::salvar');
$routes->post('configuracaoia/testar', 'ConfiguracaoIA::testar');
$routes->post('configuracaoia/testarchat', 'ConfiguracaoIA::testarchat');
$routes->get('configuracaoia/etapas', 'ConfiguracaoIA::etapas');

$routes->group('configuracaoia', static function ($routes) {
    $routes->get('/', 'ConfiguracaoIA::index');
    $routes->post('salvar', 'ConfiguracaoIA::salvar');
    $routes->get('historicoTeste', 'ConfiguracaoIA::historicoTeste');
    $routes->post('testarchat', 'ConfiguracaoIA::testarchat');
    $routes->post('limparHistoricoTeste', 'ConfiguracaoIA::limparHistoricoTeste');
    $routes->post('atualizarEtapaTeste', 'ConfiguracaoIA::atualizarEtapaTeste');
    $routes->match(['get','post'], 'testarChatSimulado', 'ConfiguracaoIA::testarChatSimulado');
});

// Painel
$routes->get('painel/aguardando', 'Painel::aguardando');

// Kanban
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

// Config de coluna
$routes->get('kanban/etapa-config/(:segment)', 'Kanban::etapaConfig/$1');
$routes->post('kanban/etapa-config/(:segment)', 'Kanban::salvarEtapaConfig/$1');
$routes->post('kanban/etapa-config/teste/(:segment)', 'Kanban::testeEtapaConfig/$1');

// Anexos
$routes->get('kanban/lead-files/(:segment)', 'Kanban::listarArquivos/$1');
$routes->post('kanban/lead-files/(:segment)', 'Kanban::uploadArquivo/$1');
$routes->get('kanban/lead-files/download/(:num)', 'Kanban::baixarArquivo/$1');
$routes->post('kanban/lead-files/delete/(:num)', 'Kanban::excluirArquivo/$1');

// Etapas (nova API)
$routes->get('etapas', 'Etapas::index');
$routes->post('etapas/salvar', 'Etapas::save');
$routes->post('etapas/(:num)/excluir', 'Etapas::delete/$1');
$routes->post('etapas/ordenar', 'Etapas::ordenar');
$routes->post('etapas/(:num)/up', 'Etapas::moverCima/$1');
$routes->post('etapas/(:num)/down', 'Etapas::moverBaixo/$1');

// Etapas (antiga)
$routes->get('/etapa', 'CriarEtapas::index');
$routes->get('/etapa/listar', 'CriarEtapas::listar');
$routes->post('/etapa/criar_ou_atualizar', 'CriarEtapas::criarOuAtualizarEtapa');
$routes->post('/etapa/excluir', 'CriarEtapas::excluirEtapa');

// Aprendizagem
$routes->get('/aprendizagem', 'Aprendizagem::index');
$routes->get('/aprendizagem/listar', 'Aprendizagem::listar');
$routes->get('/aprendizagem/obter/(:num)', 'Aprendizagem::obter/$1');
$routes->post('/aprendizagem/salvar', 'Aprendizagem::salvar');
$routes->post('/aprendizagem/excluir', 'Aprendizagem::excluir');
$routes->get('/aprendizagem/base', 'Aprendizagem::base');

// Tarefas
$routes->group('tarefas', static function ($r) {
    $r->get('/', 'Tarefas::index');
    $r->get('listar', 'Tarefas::listar');
    $r->post('salvar', 'Tarefas::salvar');
    $r->post('concluir', 'Tarefas::concluir');
    $r->post('excluir', 'Tarefas::excluir');
    $r->post('ordenar', 'Tarefas::ordenar');
});

// Notificações
$routes->get('notificacoes', 'Notificacoes::index');
$routes->get('notificacoes/list', 'Notificacoes::list');
$routes->post('notificacoes/save', 'Notificacoes::save');
$routes->post('notificacoes/delete/(:num)', 'Notificacoes::delete/$1');
$routes->post('notificacoes/regra/save', 'Notificacoes::saveRule');
$routes->get('notificacoes/regras', 'Notificacoes::rules');

// Auth
$routes->get('auth', 'Auth::index');
$routes->post('auth/login', 'Auth::login');
$routes->post('auth/register', 'Auth::register');
$routes->get('logout', 'Auth::logout');

// WhatsApp (painel)
$routes->group('whatsapp', ['namespace' => 'App\Controllers'], static function($routes) {
    $routes->get('/', 'Whatsapp::index');
    $routes->get('webhook-base', 'Whatsapp::webhookBase');
    $routes->post('bind', 'Whatsapp::bind');
    $routes->post('save', 'Whatsapp::bind');
    $routes->get('qr/(:num)', 'Whatsapp::qr/$1');
    $routes->get('status/(:num)', 'Whatsapp::status/$1');
    $routes->post('logout/(:num)', 'Whatsapp::logout/$1');
    $routes->post('webhook/(:num)', 'Whatsapp::setWebhook/$1');
    $routes->delete('delete/(:num)', 'Whatsapp::delete/$1');
    $routes->post('delete/(:num)', 'Whatsapp::delete/$1');
});

// === Webhook do gateway (único) ===
$routes->post('webhook', 'Webhook::index');   // só essa!

// Preflight CORS
$routes->options('(:any)', static function () {
    return service('response')->setStatusCode(204);
});

// Cron opcional
$routes->get('cron/disparar-agendadas', 'Cron::dispararAgendadas');
