<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $userId = (int) (session('usuario_id') ?? 0);

        if ($userId > 0) {
            return; // autenticado
        }

        // Se for AJAX/API, responde 401 em JSON
        if ($request->isAJAX() || $request->getHeaderLine('Accept') === 'application/json') {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(['ok' => false, 'error' => 'unauthorized']);
        }

        // Caso contrário, redireciona para /auth
        return redirect()->to('/auth');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // nada a fazer no after
    }
}
