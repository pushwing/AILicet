# 코드 스타일 규칙

> 이 문서는 `CLAUDE.md` 에서 `@.claude/rules/code-style.md` 로 로드된다.

## 코딩 규칙

- PSR-12 준수
- 입력값은 반드시 CI4 Validation 또는 `esc()` 처리
- SQL은 CI4 Query Builder만 사용 (raw query 금지)
- 시크릿은 `.env`에서만 관리 (`env('KEY')`)
- POST 폼에는 `<?= csrf_field() ?>` 필수 (Admin 뷰)
- Model의 `$returnType`은 `'array'`로 통일 — `'object'` 혼용 금지

## 네이밍 규칙

### PHP

| 대상 | 규칙 | 예시 |
|------|------|------|
| 클래스 | PascalCase | `CampaignController`, `JwtLibrary` |
| 인터페이스 | PascalCase + `Interface` | `PGInterface`, `AdapterInterface` |
| 추상 클래스 | `Base` 접두어 | `BaseApiController`, `BaseAdminController` |
| 메서드 | camelCase | `getAccessToken()`, `buildPaymentParams()` |
| 변수 | camelCase | `$accessToken`, `$campaignId` |
| 프로퍼티 | camelCase | `$authUserId`, `$refreshTtl` |
| 상수 | UPPER_SNAKE_CASE | `MAX_RETRY`, `DEFAULT_TTL` |
| 배열 키 | snake_case | `$data['access_token']`, `$payload['user_id']` |
| 파일명 | 클래스와 동일 | `CampaignController.php` |

### DB

| 대상 | 규칙 | 예시 |
|------|------|------|
| 테이블 | snake_case · 복수형 | `campaigns`, `ad_creatives`, `stock_logs` |
| 컬럼 | snake_case | `created_at`, `discount_price` |
| PK | `id` | `id` |
| FK | `{단수테이블명}_id` | `campaign_id`, `user_id` |
| 불리언 | `is_` 접두어 | `is_active`, `is_deleted` |
| 타임스탬프 | CI4 표준 | `created_at`, `updated_at`, `deleted_at` |
| 일반 인덱스 | `idx_{테이블}_{컬럼}` | `idx_campaigns_status` |
| 유니크 인덱스 | `uniq_{테이블}_{컬럼}` | `uniq_users_email` |
| Pivot 테이블 | 두 테이블 알파벳순 · 단수 | `campaign_tag` |

## PHP 절대 금지

### 코드 품질

| 금지 | 이유 |
|------|------|
| `@` 에러 억제 연산자 | 에러를 숨겨 디버깅 불가 |
| `extract($array)` | 변수 충돌·추적 불가 |
| `global $변수` | 상태 추적 불가, 테스트 불가 |
| `die()` / `exit()` 비즈니스 로직 안에 | 응답 흐름 단절, 테스트 불가 |
| 함수 하나에 100줄 이상 | 단일 책임 원칙 위반 |
| 의미 없는 변수명 (`$a`, `$tmp`, `$data2`) | 가독성 저하 |
| 주석으로 코드 비활성화 후 방치 | 죽은 코드 |
| `var_dump()` / `print_r()` 커밋 | 디버그 코드 노출 |

### PHP 특성 함정

| 금지 | 이유 | 대신 |
|------|------|------|
| `==` 타입 비교 | `0 == "a"` → true | `===` 사용 |
| `intval()` 없이 문자열을 숫자로 연산 | 타입 오염 | 명시적 형변환 또는 타입 선언 |
| 타입 선언 없는 함수 파라미터 | PHPStan 레벨 6 통과 불가 | `string $id`, `int $count` 명시 |
| `null` 반환과 `false` 반환 혼용 | 호출부 처리 혼란 | 반환 타입 통일 |
| `catch` 후 예외 무시 | 버그가 조용히 삼켜짐 | 최소한 로깅 |

### CI4 한정

| 금지 | 이유 |
|------|------|
| Controller에 비즈니스 로직 작성 | Model/Service로 위임 |
| `$db->query("... WHERE id = $id")` | SQL Injection |
| `allowedFields` 없는 Model | 의도치 않은 mass assignment |
| CSRF 예외 라우트 무분별 추가 | 보호 구멍 |
| `env()` 없이 Config에 직접 시크릿 작성 | `.env` 관리 원칙 위반 |
| 뷰에서 Model을 직접 호출해 데이터 조회 | MVC 책임 분리 위반, 테스트·유지보수 불가 |
| `new UserModel()` 직접 인스턴스화 | `model()` 헬퍼 우회 — `model(UserModel::class)` 사용 |

뷰는 컨트롤러가 전달한 데이터만 렌더링한다.

```php
// ❌ 금지 — 뷰에서 직접 조회
$campaigns = new \App\Models\CampaignModel();
foreach ($campaigns->findAll() as $item) { ... }

// ✅ 올바른 방식 — 컨트롤러에서 전달
// Controller
return $this->render('admin/campaigns/index', [
    'campaigns' => model(CampaignModel::class)->findAll(),
]);

// View
foreach ($campaigns as $item) { ... }
```

## PHP 모던 스타일 (8.4+)

상태·타입 관리는 배열·상수 대신 **readonly DTO**·**Backed Enum**을 우선한다.

```php
// ✅ readonly DTO — 요청·응답 데이터 매핑
final readonly class CreateUserRequest
{
    public function __construct(
        public string $email,
        public string $name,
        public UserRole $role = UserRole::Member,
    ) {}
}

// ✅ Backed Enum — 상태·타입은 Enum으로
enum UserRole: string
{
    case Admin  = 'admin';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Admin  => '관리자',
            self::Member => '일반회원',
        };
    }
}

// ❌ 금지 — 배열·define()로 상태/타입 관리
define('ROLE_ADMIN', 1);
```

- 메서드·프로퍼티에 타입 선언(return type 포함) 완전 적용 (PHPStan 레벨 6 전제)
- `match` 표현식 우선 (`switch` 지양)
- DTO는 `final readonly`, 정적 팩토리(`fromRequest()`, `fromArray()`)로 생성

## 레이어 책임 (Controller · Service)

- **Controller는 얇게(thin)**: 유효성 검사 → Service 호출 → 응답 반환만 수행
- 비즈니스 로직이 Controller에 생기면 즉시 Service로 추출
- **하나의 Service 메서드 = 하나의 유스케이스**
- **DB 트랜잭션은 Service 레이어**에서 관리 (`$db->transStart()` / `transComplete()`)
- 데이터 접근은 `model(XxxModel::class)` 헬퍼 경유 (네이밍·MVC 규칙 준수, 직접 `new` 금지)

```php
// ✅ 얇은 컨트롤러
class UserController extends BaseApiController
{
    public function store(): ResponseInterface
    {
        $dto    = CreateUserRequest::fromRequest($this->request);
        $result = service('userService')->create($dto);
        return $this->success($result, statusCode: 201);
    }
}
```

> 참고: 이 프로젝트는 별도 Repository 레이어를 두지 않고 CI4 `Model`을 데이터 접근 계층으로 사용한다. 복잡한 쿼리는 Model에 메서드로 캡슐화한다.

## 도메인 예외 처리

- 도메인 예외는 `app/Exceptions/` 에 커스텀 클래스로 정의
- 예외는 **HTTP 상태코드 + 에러 코드(문자열)** 를 반드시 포함 (에러 코드 네이밍 규칙 준수)
- 전역 핸들러는 `app/Config/Exceptions.php` 에 등록

```php
// app/Exceptions/DomainException.php
abstract class DomainException extends \RuntimeException
{
    abstract public function httpStatusCode(): int;
    abstract public function errorCode(): string;   // 예: 'USER_NOT_FOUND'
}
```
