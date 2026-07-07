<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * AILicet 스캐폴딩 스모크 테스트.
 *
 * 프레임워크 부팅과 핵심 상수/설정 로딩을 검증한다.
 * 실 DB가 필요 없는 단위 테스트로, CI(PHPUnit) 그린 여부의 최소 보증선이다.
 *
 * @internal
 */
final class SmokeTest extends CIUnitTestCase
{
    public function testFrameworkPathsAreDefined(): void
    {
        $this->assertTrue(defined('APPPATH'));
        $this->assertTrue(defined('SYSTEMPATH'));
        $this->assertTrue(defined('WRITEPATH'));
    }

    public function testAppNamespaceIsAutoloaded(): void
    {
        // App\Config\App 이 오토로드되는지(psr-4 매핑) 확인
        $this->assertTrue(class_exists(\Config\App::class));
    }

    public function testEnvironmentIsTesting(): void
    {
        $this->assertSame('testing', ENVIRONMENT);
    }
}
