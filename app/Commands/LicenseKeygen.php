<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\LicenseSigner;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * 라이센스 서명용 Ed25519 키페어 생성.
 *
 * 출력된 값을 .env(운영은 KMS/Secrets Manager)에 넣는다. 개인키는 절대 커밋 금지.
 *   php spark license:keygen
 */
final class LicenseKeygen extends BaseCommand
{
    protected $group       = 'License';
    protected $name        = 'license:keygen';
    protected $description = 'Ed25519 라이센스 서명 키페어를 생성한다(.env 주입용).';

    /**
     * @param list<string> $params
     */
    public function run(array $params): void
    {
        $pair = LicenseSigner::generateKeypair();

        CLI::write('Ed25519 라이센스 서명 키페어 (base64)', 'green');
        CLI::newLine();
        CLI::write('# .env 에 아래를 추가하세요(개인키는 절대 커밋하지 마세요):', 'yellow');
        CLI::write('license.ed25519PublicKey = ' . $pair['publicKey']);
        CLI::write('license.ed25519SecretKey = ' . $pair['secretKey']);
        CLI::newLine();
        CLI::write('운영 환경에서는 개인키를 KMS/Secrets Manager 로 주입하세요.', 'yellow');
    }
}
