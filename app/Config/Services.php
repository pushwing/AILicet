<?php

namespace Config;

use App\Libraries\JwtLibrary;
use App\Libraries\LicenseSigner;
use App\Notifications\LogNotifier;
use App\Notifications\Notifier;
use App\Notifications\SlackNotifier;
use App\Queue\LogQueue;
use App\Queue\RedisLogQueue;
use App\Services\AbuseDetectionService;
use App\Services\LicenseExpiryService;
use App\Services\LogQueueConsumer;
use Predis\Client as Redis;
use App\Licensing\Storage\LicenseStorageInterface;
use App\Licensing\Storage\LocalLicenseStorage;
use App\Licensing\Strategy\LicensePayloadStrategyResolver;
use App\Services\AgencyService;
use App\Services\ClientService;
use App\Services\ClientSignupService;
use App\Services\CustomerService;
use App\Services\FloatingLicenseService;
use App\Services\LicenseLifecycleService;
use App\Services\LicenseQueryService;
use App\Services\NodeLockLicenseService;
use App\Services\ProductService;
use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /**
     * JWT(HS256) 인코더/디코더. 테스트에서 injectMock 으로 시크릿 주입 가능.
     */
    public static function jwt(bool $getShared = true): JwtLibrary
    {
        if ($getShared) {
            return static::getSharedInstance('jwt');
        }

        return new JwtLibrary();
    }

    /**
     * 상품·모듈 서비스.
     */
    public static function productService(bool $getShared = true): ProductService
    {
        if ($getShared) {
            return static::getSharedInstance('productService');
        }

        return new ProductService();
    }

    /**
     * 라이센스 Ed25519 서명기(env/KMS 키).
     */
    public static function licenseSigner(bool $getShared = true): LicenseSigner
    {
        if ($getShared) {
            return static::getSharedInstance('licenseSigner');
        }

        return LicenseSigner::fromConfig();
    }

    /**
     * 라이센스 파일 저장소. env(license.storageDriver) 로 구현 선택(기본 local).
     */
    public static function licenseStorage(bool $getShared = true): LicenseStorageInterface
    {
        if ($getShared) {
            return static::getSharedInstance('licenseStorage');
        }

        // S3 드라이버는 인프라(aws-sdk) 도입 시 여기서 분기한다.
        return new LocalLicenseStorage();
    }

    /**
     * 노드락 라이센스 발급 서비스.
     */
    public static function nodeLockLicenseService(bool $getShared = true): NodeLockLicenseService
    {
        if ($getShared) {
            return static::getSharedInstance('nodeLockLicenseService');
        }

        return new NodeLockLicenseService(
            static::licenseSigner(),
            static::licenseStorage(),
            new LicensePayloadStrategyResolver(),
            (string) (env('license.deployTarget') ?: 'dev'),
        );
    }

    /**
     * 플로팅 라이센스 발급 서비스.
     */
    public static function floatingLicenseService(bool $getShared = true): FloatingLicenseService
    {
        if ($getShared) {
            return static::getSharedInstance('floatingLicenseService');
        }

        return new FloatingLicenseService();
    }

    /**
     * 라이센스 생명주기(상태/연장/재발급) 서비스.
     */
    public static function licenseLifecycleService(bool $getShared = true): LicenseLifecycleService
    {
        if ($getShared) {
            return static::getSharedInstance('licenseLifecycleService');
        }

        return new LicenseLifecycleService();
    }

    /**
     * 회원(대행사/고객) 관리 서비스.
     */
    public static function customerService(bool $getShared = true): CustomerService
    {
        if ($getShared) {
            return static::getSharedInstance('customerService');
        }

        return new CustomerService();
    }

    /**
     * 라이센스 조회(목록·상세) 서비스.
     */
    public static function licenseQueryService(bool $getShared = true): LicenseQueryService
    {
        if ($getShared) {
            return static::getSharedInstance('licenseQueryService');
        }

        return new LicenseQueryService();
    }

    /**
     * 알림기 — 슬랙 webhook 설정 시 SlackNotifier, 없으면 LogNotifier.
     */
    public static function notifier(bool $getShared = true): Notifier
    {
        if ($getShared) {
            return static::getSharedInstance('notifier');
        }

        $webhook = (string) env('slack.webhookUrl');
        if ($webhook === '') {
            return new LogNotifier();
        }

        return new SlackNotifier($webhook, WRITEPATH . 'logs/notify-failed');
    }

    /**
     * 라이센스 만료 배치 서비스.
     */
    public static function licenseExpiryService(bool $getShared = true): LicenseExpiryService
    {
        if ($getShared) {
            return static::getSharedInstance('licenseExpiryService');
        }

        return new LicenseExpiryService();
    }

    /**
     * 부정사용 감지 서비스.
     */
    public static function abuseDetectionService(bool $getShared = true): AbuseDetectionService
    {
        if ($getShared) {
            return static::getSharedInstance('abuseDetectionService');
        }

        return new AbuseDetectionService();
    }

    /**
     * Redis 클라이언트(predis).
     */
    public static function redis(bool $getShared = true): Redis
    {
        if ($getShared) {
            return static::getSharedInstance('redis');
        }

        return new Redis([
            'scheme' => 'tcp',
            'host'   => (string) (env('redis.host') ?: '127.0.0.1'),
            'port'   => (int) (env('redis.port') ?: 6379),
        ]);
    }

    /**
     * 로그 큐(소비자 측, Redis).
     */
    public static function logQueue(bool $getShared = true): LogQueue
    {
        if ($getShared) {
            return static::getSharedInstance('logQueue');
        }

        return new RedisLogQueue(static::redis());
    }

    /**
     * 로그 큐 소비자.
     */
    public static function logQueueConsumer(bool $getShared = true): LogQueueConsumer
    {
        if ($getShared) {
            return static::getSharedInstance('logQueueConsumer');
        }

        return new LogQueueConsumer(static::logQueue());
    }

    /**
     * 대행사 소유권 스코프 서비스.
     */
    public static function agencyService(bool $getShared = true): AgencyService
    {
        if ($getShared) {
            return static::getSharedInstance('agencyService');
        }

        return new AgencyService();
    }

    /**
     * 고객 자가가입 서비스.
     */
    public static function clientSignupService(bool $getShared = true): ClientSignupService
    {
        if ($getShared) {
            return static::getSharedInstance('clientSignupService');
        }

        return new ClientSignupService();
    }

    /**
     * 고객 셀프서비스 서비스.
     */
    public static function clientService(bool $getShared = true): ClientService
    {
        if ($getShared) {
            return static::getSharedInstance('clientService');
        }

        return new ClientService();
    }
}
