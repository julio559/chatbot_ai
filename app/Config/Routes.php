<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
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
$routes->get('/etapa', 'CriarEtapas::index'); // Exibe as etapas
$routes->post('/etapa/criar_ou_atualizar', 'CriarEtapas::criarOuAtualizarEtapa'); // Cria ou atualiza uma etapa
$routes->post('/etapa/excluir', 'CriarEtapas::excluirEtapa'); // Exclui uma etapa
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

