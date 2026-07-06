<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * 고객-라이센스 매핑(customer_license) 모델. created_at 만 사용.
 */
final class CustomerLicenseModel extends Model
{
    protected $table         = 'customer_license';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = ['customer_id', 'license_id', 'type', 'created_by'];
}
