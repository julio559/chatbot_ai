<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

class TestaConexao extends BaseCommand
{
    protected $group       = 'custom';
    protected $name        = 'testar:conexao';
    protected $description = 'Testa a conexão com o banco de dados.';

    public function run(array $params)
    {
        try {
            $db = Database::connect();
            $query = $db->query('SELECT 1');
            if ($query) {
                CLI::write('✅ Conexão com o banco de dados bem-sucedida!', 'green');
            } else {
                CLI::error('❌ Falha ao executar query de teste.');
            }
        } catch (\Throwable $e) {
            CLI::error('❌ Erro na conexão: ' . $e->getMessage());
        }
    }
}
