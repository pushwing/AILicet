<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * 라이센스(licenses) 모델.
 *
 * config 는 JSON 컬럼(모듈·사용량 제한 등 타입별 데이터 통합). 서비스에서 json_encode 하여 저장한다.
 */
final class LicenseModel extends Model
{
    protected $table          = 'licenses';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'product_id', 'license_type', 'period_code', 'status', 'version', 'host_id',
        'expire_date', 'support_end_date', 'activate_term', 'check_term',
        'activate_history_id', 'path', 'is_trial', 'config', 'issued_by', 'issue_date',
    ];
}
