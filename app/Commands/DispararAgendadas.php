<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class DispararAgendadas extends BaseCommand
{
    protected $group       = 'Mensagens';
    protected $name        = 'disparar:agendadas';
    protected $description = 'Envia mensagens agendadas vencidas (status pendente).';

    public function run(array $params)
    {
        date_default_timezone_set(config('App')->appTimezone ?? 'America/Sao_Paulo');

        $db = \Config\Database::connect();
        $agendadas = $db->table('mensagens_agendadas')
            ->where('status', 'pendente')
            ->where('enviar_em <=', date('Y-m-d H:i:s'))
            ->orderBy('enviar_em', 'ASC')
            ->get()->getResultArray();

        if (empty($agendadas)) {
            CLI::write('Nada a enviar.', 'yellow');
            return;
        }

        foreach ($agendadas as $ag) {
            $ok = $this->enviarWhatsapp($ag['numero'], $ag['mensagem']);
            if ($ok) {
                $db->table('mensagens_agendadas')->where('id', $ag['id'])->update([
                    'status'    => 'enviado',
                    'enviado_em'=> date('Y-m-d H:i:s')
                ]);
                CLI::write("Enviado para {$ag['numero']} (#{$ag['id']})", 'green');
            } else {
                // mantém como pendente para tentar na próxima rodada
                CLI::write("Falha ao enviar #{$ag['id']} para {$ag['numero']}", 'red');
            }
        }
    }

    private function enviarWhatsapp(string $numero, string $mensagem): bool
    {
        // use as mesmas credenciais do seu Webhook -> UltraMSG
        $instanceId = getenv('ULTRA_INSTANCE_ID') ?: 'instance136009';
        $token      = getenv('ULTRA_TOKEN') ?: 'rbsu6e74buuzsnjj';
        $url        = "https://api.ultramsg.com/{$instanceId}/messages/chat";

        $data = http_build_query([
            'token' => $token,
            'to'    => $numero,
            'body'  => $mensagem
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $result   = curl_exec($ch);
        $err      = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        log_message('error', "Agendadas: HTTP $httpCode - $result");
        if ($err) log_message('error', "Agendadas cURL: $err");

        return $httpCode >= 200 && $httpCode < 300;
    }
}
