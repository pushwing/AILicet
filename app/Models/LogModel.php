<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * logs — 큐 소비자가 가공 로그를 저장하는 모델. created_at 만 사용.
 */
final class LogModel extends Model
{
    protected $table         = 'logs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = ['level', 'source', 'message', 'context', 'client_ip', 'user_id', 'logged_at'];
}
