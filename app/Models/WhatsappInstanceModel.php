<?php
namespace App\Models;

use CodeIgniter\Model;

class WhatsappInstanceModel extends Model
{
    protected $table            = 'whatsapp_instancias';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    // Deixe o CI4 fora do gerenciamento automático de timestamps; você já atualiza na mão
    protected $useTimestamps    = false; // created_at / updated_at já existem, mas você seta manualmente
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    // Colunas permitidas no insert/update (alinhe com seu schema atual)
    protected $allowedFields    = [
        'usuario_id',
        'nome',
        'instance_id',
        'token',
        'webhook_url',
        'status',           // ENUM('ativo','inativo') – flag da sua tabela
        'conn_status',      // status de conexão UltraMSG (ex.: authenticated, qr, loading)
        'conn_substatus',   // substatus (ex.: connected, pairing)
        'status_note',      // mensagens informativas
        'status_raw',       // JSON bruto
        'last_qr_at',
        'last_status_at',
        'created_at',
        'updated_at',
    ];

    // Se quiser regras, pode adicionar aqui:
    // protected $validationRules = [];
}
