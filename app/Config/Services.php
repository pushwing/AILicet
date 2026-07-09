<?php

namespace Config;

use App\Integrations\AiClient;
use App\Integrations\AitesseraClient;
use App\Integrations\AnthropicAiClient;
use App\Integrations\NullAiClient;
use App\Libraries\HtmlSanitizer;
use App\Libraries\JwtLibrary;
use App\Libraries\JwtVerifier;
use App\Libraries\LicenseSigner;
use App\Licensing\Storage\LicenseStorageInterface;
use App\Licensing\Storage\LocalLicenseStorage;
use App\Licensing\Strategy\LicensePayloadStrategyResolver;
use App\Notifications\LogNotifier;
use App\Notifications\Notifier;
use App\Notifications\SlackNotifier;
use App\Queue\LogQueue;
use App\Queue\RedisLogQueue;
use App\Services\AbuseDetectionService;
use App\Services\AgencyService;
use App\Services\AuditLogQueryService;
use App\Services\ClientService;
use App\Services\ClientSignupService;
use App\Services\CustomerService;
use App\Services\DashboardService;
use App\Services\FloatingLicenseService;
use App\Services\LicenseExpiryService;
use App\Services\LicenseLifecycleService;
use App\Services\LicensePolicyValidator;
use App\Services\LicenseQueryService;
use App\Services\LogClassificationService;
use App\Services\LogQueueConsumer;
use App\Services\ModuleService;
use App\Services\NodeLockLicenseService;
use App\Services\NotificationService;
use App\Services\ProductService;
use CodeIgniter\Config\BaseService;
use Predis\Client as Redis;

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
     * AITessera 발급 토큰 검증기. 대칭키(HS256)→비대칭키(RS256) 무중단 전환을 위해
     * `JWT_VERIFY_ALGOS`(기본 `HS256,RS256`)로 허용 알고리즘을 제어한다. 전환 완료 후
     * `JWT_VERIFY_ALGOS=RS256` 으로 좁히면 코드 변경 없이 HS256 을 차단한다.
     *
     * 테스트에서 injectMock('aitesseraToken', ...) 으로 대체 가능.
     */
    public static function aitesseraToken(bool $getShared = true): JwtVerifier
    {
        if ($getShared) {
            return static::getSharedInstance('aitesseraToken');
        }

        return JwtVerifier::fromConfig();
    }

    /**
     * AILicet 자체 발급 토큰(플로팅 라이센스 활성화 등)용 HS256 서명기.
     * 전용 시크릿 `LICENSE_TOKEN_SECRET`, 미설정 시 `JWT_SECRET` 으로 폴백한다.
     *
     * 테스트에서 injectMock('licenseToken', ...) 으로 대체 가능.
     */
    public static function licenseToken(bool $getShared = true): JwtLibrary
    {
        if ($getShared) {
            return static::getSharedInstance('licenseToken');
        }

        $secret = (string) env('LICENSE_TOKEN_SECRET');
        if ($secret === '') {
            $secret = (string) env('JWT_SECRET');
        }

        return new JwtLibrary($secret);
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
     * 리치 텍스트 HTML 화이트리스트 정화기.
     *
     * 테스트에서 injectMock('htmlSanitizer', ...) 으로 대체 가능.
     */
    public static function htmlSanitizer(bool $getShared = true): HtmlSanitizer
    {
        if ($getShared) {
            return static::getSharedInstance('htmlSanitizer');
        }

        return new HtmlSanitizer();
    }

    /**
     * 모듈 마스터 서비스.
     */
    public static function moduleService(bool $getShared = true): ModuleService
    {
        if ($getShared) {
            return static::getSharedInstance('moduleService');
        }

        return new ModuleService();
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
     * 기간정책별 발급 입력 검증·정규화기(노드락·플로팅 공통).
     */
    public static function licensePolicyValidator(bool $getShared = true): LicensePolicyValidator
    {
        if ($getShared) {
            return static::getSharedInstance('licensePolicyValidator');
        }

        return new LicensePolicyValidator();
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
     * 대시보드 집계(통계·차트·최근 라이선스) 서비스.
     */
    public static function dashboardService(bool $getShared = true): DashboardService
    {
        if ($getShared) {
            return static::getSharedInstance('dashboardService');
        }

        return new DashboardService();
    }

    /**
     * 감사 로그 조회(목록·상세) 서비스.
     */
    public static function auditLogQueryService(bool $getShared = true): AuditLogQueryService
    {
        if ($getShared) {
            return static::getSharedInstance('auditLogQueryService');
        }

        return new AuditLogQueryService();
    }

    /**
     * AITessera 운영자 회원관리 API 클라이언트.
     */
    public static function aitesseraClient(bool $getShared = true): AitesseraClient
    {
        if ($getShared) {
            return static::getSharedInstance('aitesseraClient');
        }

        return new AitesseraClient((string) env('aitessera.baseURL'));
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
     * 인앱 메시지(수신함) 서비스.
     */
    public static function notificationService(bool $getShared = true): NotificationService
    {
        if ($getShared) {
            return static::getSharedInstance('notificationService');
        }

        return new NotificationService();
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
     * AI(Anthropic) 클라이언트 — ANTHROPIC_API_KEY 설정 시 AnthropicAiClient, 없으면 NullAiClient(no-op).
     *
     * 테스트에서 injectMock('aiClient', ...) 으로 대체 가능.
     */
    public static function aiClient(bool $getShared = true): AiClient
    {
        if ($getShared) {
            return static::getSharedInstance('aiClient');
        }

        $apiKey = (string) env('ANTHROPIC_API_KEY');
        if ($apiKey === '') {
            return new NullAiClient();
        }

        return new AnthropicAiClient($apiKey, (string) (env('ai.baseURL') ?: 'https://api.anthropic.com'));
    }

    /**
     * 수집 로그 AI 자동 분류·요약 서비스.
     */
    public static function logClassificationService(bool $getShared = true): LogClassificationService
    {
        if ($getShared) {
            return static::getSharedInstance('logClassificationService');
        }

        return new LogClassificationService(static::aiClient());
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
