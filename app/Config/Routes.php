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

