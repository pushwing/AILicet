<?php

namespace Config;

use App\Libraries\JwtLibrary;
use App\Libraries\LicenseSigner;
use App\Licensing\Storage\LicenseStorageInterface;
use App\Licensing\Storage\LocalLicenseStorage;
use App\Licensing\Strategy\LicensePayloadStrategyResolver;
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
}
