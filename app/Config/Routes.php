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

