<?php

declare(strict_types=1);

namespace Tests\Support;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\Fabricator;

/**
 * DB 를 쓰는 테스트의 공통 기반.
 *
 * ## 왜 이 클래스가 있는가
 *
 * CI4 `DatabaseTestTrait` 의 `$refresh = true` 는 **테스트 메서드마다**
 * `migrations->regress(0)` → `migrations->latest()` 를 돌린다. 테스트가
 * 필요로 하는 건 "빈 스키마"지 "새 스키마"가 아닌데, 마이그레이션 수에
 * 비례해 테스트 1건마다 테이블 전체 DROP + CREATE 비용을 낸다.
 *
 * 그래서 스키마는 **프로세스당 한 번만** 만들고(`$migrateOnce = true`),
 * 테스트마다는 **행이 남아 있는 테이블만 TRUNCATE** 한다. TRUNCATE 는
 * AUTO_INCREMENT 까지 되돌리므로 id 값에 의존하는 테스트도 regress 때와
 * 동일하게 동작한다.
 *
 * ## 주의
 *
 * 스키마 자체를 바꾸는 테스트(마이그레이션을 직접 regress 하거나 forge 로
 * 테이블을 드롭하는 테스트)는 이 기반을 쓰면 안 된다 — 뒤따르는 테스트가
 * 사라진 테이블을 만난다. 그런 테스트는 `$refresh = true` 를 직접 지정한다.
 */
abstract class DatabaseTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;

    /** 스키마는 프로세스당 한 번만 만든다. */
    protected $migrateOnce = true;

    /** regress→latest 대신 아래 truncate 로 비운다. */
    protected $refresh = false;

    protected $namespace = 'App';

    /** TRUNCATE 대상에서 제외할 테이블. 마이그레이션 이력은 지우면 안 된다. */
    private const KEEP_TABLES = ['migrations'];

    /**
     * 마이그레이션 뒤·시드 앞에 데이터를 비운다.
     *
     * 순서가 `$refresh = true` 일 때(regress → latest → seed)와 같아야
     * 시더가 빈 테이블 위에서 도는 기존 동작이 유지된다.
     */
    protected function setUpDatabase()
    {
        $this->loadDependencies();
        $this->setUpMigrate();
        $this->truncateDirtyTables();
        $this->setUpSeed();
    }

    /**
     * 행이 하나라도 있는 테이블만 TRUNCATE 한다.
     *
     * 전수 TRUNCATE 도 가능하지만, 대부분의 테스트는 테이블 몇 개만 건드리므로
     * `SELECT 1 ... LIMIT 1` 로 걸러내는 쪽이 왕복 수를 줄인다.
     */
    private function truncateDirtyTables(): void
    {
        $dirty = [];

        foreach ($this->db->listTables() as $table) {
            if (in_array($table, self::KEEP_TABLES, true)) {
                continue;
            }

            // 테이블명은 DB 가 돌려준 값이라 외부 입력이 아니다.
            if ($this->db->query('SELECT 1 FROM ' . $this->db->escapeIdentifiers($table) . ' LIMIT 1')->getNumRows() > 0) {
                $dirty[] = $table;
            }
        }

        if ($dirty === []) {
            return;
        }

        // FK 순서를 따지지 않고 지우려면 잠깐 꺼야 한다. 세션 한정이다.
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($dirty as $table) {
            $this->db->query('TRUNCATE TABLE ' . $this->db->escapeIdentifiers($table));
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');

        // regress 경로가 하던 일. 팩토리 카운터도 같이 되돌린다.
        Fabricator::resetCounts();
    }
}
