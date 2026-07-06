<?php

namespace Config;

use App\Libraries\JwtLibrary;
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
}
