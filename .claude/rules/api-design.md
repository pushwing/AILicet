# API 설계 규칙

> 이 문서는 `CLAUDE.md` 에서 `@.claude/rules/api-design.md` 로 로드된다.

## API 응답 포맷

```php
// 성공
$this->success($data, $meta);
// → { "status": "success", "data": {...}, "meta": {...} }

// 실패
$this->error('ERROR_CODE', '메시지', $statusCode);
// → { "status": "error", "code": "...", "message": "..." }
```

### 페이지네이션 meta 표준

목록 API의 `meta`는 아래 4개 필드를 항상 포함한다.

```php
$this->success($items, [
    'page'      => (int) $page,
    'per_page'  => (int) $limit,
    'total'     => (int) $total,
    'last_page' => (int) ceil($total / $limit),
]);
```

### 에러 코드 네이밍 규칙

`UPPER_SNAKE_CASE` · `도메인_동사` 형식으로 통일한다.

| 에러 코드 | 용도 |
|-----------|------|
| `UNAUTHORIZED` | 인증 토큰 없음 |
| `TOKEN_EXPIRED` | 토큰 만료 |
| `INVALID_TOKEN` | 토큰 형식·서명 오류 |
| `INVALID_CREDENTIALS` | 이메일·비밀번호 불일치 |
| `VALIDATION_ERROR` | 유효성 검사 실패 |
| `NOT_FOUND` | 리소스 없음 |
| `ALREADY_EXISTS` | 중복 리소스 |
| `FORBIDDEN` | 권한 없음 |
| `INTERNAL_ERROR` | 서버 내부 오류 |

### REST URI 설계

- URI는 **복수 명사**: `/api/v1/users`, `/api/v1/campaigns`
- 버전 prefix 필수: `/api/v1/`
- URI에 **동사 금지**: `/getUser` ❌ → `GET /users/{id}` ✅
- 필터·정렬·페이지는 쿼리스트링: `?filter[status]=active&sort=-created_at&page=1&per_page=20`

### HTTP 상태코드

| 상황 | 코드 |
|------|------|
| 조회 성공 | 200 |
| 생성 성공 | 201 |
| 처리 성공 (응답 본문 없음) | 204 |
| 인증 실패 | 401 |
| 권한 없음 | 403 |
| 리소스 없음 | 404 |
| 유효성 검사 실패 | 422 |
| 서버 오류 | 500 |

## Swagger 어트리뷰트 규칙

새 API 엔드포인트마다 PHP 어트리뷰트 추가 필수.

```php
use OpenApi\Attributes as OA;

#[OA\Get(path: '/campaigns', summary: '...', security: [['bearerAuth' => []]], tags: ['Campaigns'], responses: [...])]
public function index() { ... }
```

## API 부하 분산 원칙

API 개발 시 부하 분산을 최우선으로 고려한다. 아래 원칙을 기본으로 적용한다.

### 캐시

- 변경 빈도가 낮은 조회 응답은 **Redis 캐시** 적용
- 캐시 키 규칙: `{리소스}:{식별자}:{파라미터해시}` (예: `campaigns:list:abc123`)
- TTL 기준

| 데이터 성격 | TTL |
|------------|-----|
| 설정·코드성 데이터 | 1시간 이상 |
| 목록·집계 | 5–60분 |
| 단건 상세 | 5–10분 |
| 실시간 필요 데이터 | 캐시 적용 금지 |

- 쓰기(INSERT·UPDATE·DELETE) 발생 시 관련 캐시 즉시 무효화
- 캐시 미스 시 DB 조회 후 캐시 저장 — 로직은 Service 레이어에서 처리

### 큐

- 즉시 응답이 불필요한 작업은 큐로 위임 (이메일·알림·로그·리포트 생성 등)
- API는 큐 적재 후 즉시 `202 Accepted` 반환
- 무거운 연산(배치 집계·엑셀 생성 등)은 절대 요청 사이클 안에서 처리 금지

### DB 쿼리

- `SELECT *` 금지 — 필요한 컬럼만 명시
- N+1 쿼리 금지 — 관계 데이터는 JOIN 또는 eager load
- 목록 API는 반드시 페이징 적용 (`limit` / `offset` 또는 커서 기반)
- 인덱스 없는 컬럼 `WHERE` 조건 금지 — 마이그레이션에 인덱스 함께 정의
- 집계 쿼리(`COUNT`, `SUM` 등)는 캐시 우선 적용

### API 응답

- 불필요한 필드 제거 — 응답 페이로드 최소화
- 목록 응답에 `meta.total`, `meta.page` 포함
- 대용량 데이터 응답은 스트리밍 또는 청크 분할 고려

### 기타

- 외부 API 호출은 타임아웃 설정 필수 (기본 5초)
- 외부 API 실패 시 재시도는 큐로 처리 (즉시 재시도 금지)
- 동일 엔드포인트 반복 호출 방어: Rate Limit 필터 적용 검토

## 로그 수집 파이프라인

프론트(앱/웹)에서 API로 전송되는 로그는 큐를 통해 비동기 처리한다.

### 흐름

```
앱/웹
  │
  │ POST /api/v1/logs
  ▼
API Server
  │ 큐에 적재 (즉시 응답)
  ▼
Queue (Redis)
  │
  ▼
Queue Consumer (Spark Command / Scheduler)
  ├── 원시 로그 → 파일 저장 (writable/logs/raw/YYYY-MM-DD.log)
  └── 가공 데이터 → DB INSERT
```

### 규칙

- API는 로그를 받는 즉시 큐에 넣고 `202 Accepted` 응답 — DB 직접 쓰기 금지
- 큐 드라이버: **Redis** (`predis/predis`)
- 원시(raw) 로그는 `writable/logs/raw/` 에 날짜별 파일로 append
- 가공 후 DB 저장 — 원시 파일은 보존 (감사·재처리 용도)
- Consumer는 Spark Command로 구현, CI4 Scheduler로 주기 실행
- 큐 처리 실패 시 dead-letter 로깅 필수 (`writable/logs/queue-failed/`)

### 기본 패턴

```php
// API Controller — 큐에 적재
public function store(): ResponseInterface
{
    $payload = $this->request->getJSON(true);
    // 유효성 검사 후
    Redis::lpush('log_queue', json_encode($payload));
    return $this->respond(null, 202);
}

// Spark Command — Consumer
class ProcessLogQueue extends BaseCommand
{
    public function run(array $params): void
    {
        while ($raw = Redis::rpop('log_queue')) {
            // 1. 원시 파일 저장
            file_put_contents(
                WRITEPATH . 'logs/raw/' . date('Y-m-d') . '.log',
                $raw . PHP_EOL,
                FILE_APPEND
            );
            // 2. 가공 후 DB 저장
            $data = $this->transform(json_decode($raw, true));
            model(LogModel::class)->insert($data);
        }
    }
}
```
