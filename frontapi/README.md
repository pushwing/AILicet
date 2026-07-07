# AILicet frontApi

클라이언트 프로그램용 **라이선스 인증 API** — 프레임워크 없는 pure PHP.

CI4 Admin 앱과 **별도 프로젝트**(별도 composer)이며, 같은 DB(`ailicet`)를 PDO prepared statement 로 읽는다.

## 스택

| 영역 | 채택 |
|------|------|
| PSR-7 | `nyholm/psr7` + `nyholm/psr7-server` |
| 라우팅 | `nikic/fast-route` |
| 미들웨어 | PSR-15 파이프라인 (`relay/relay`) |
| DI | `php-di/php-di` |
| DB | PDO (prepared statement 전용, raw query 금지) |
| 인증 | AITessera JWT(HS256) 검증 — `JWT_SECRET` 공유 |
| Rate Limit | Redis(`predis`) · 테스트는 인메모리 |
| API 문서 | `zircote/swagger-php` + Swagger UI |

## 로컬 실행

```bash
cd frontapi
cp env.example .env      # DB · JWT_SECRET 설정
composer install
php -S localhost:8400 -t public
```

## 엔드포인트

| 경로 | 인증 | 설명 |
|------|------|------|
| `GET /health` | 공개 | 헬스체크(DB 연결 포함) |
| `GET /api/docs` | 공개 | Swagger UI |
| `GET /api/v1/openapi.json` | 공개 | OpenAPI 스펙 |
| `GET /api/v1/ping` | Bearer | 인증 확인 + PDO 바인딩 확인 |

응답 포맷: 성공 `{status, data, meta}` · 실패 `{status, code, message}`

## 검증

```bash
composer check   # PHPStan level 6 + PHPUnit
```

> 라이선스 인증 엔드포인트(노드락·플로팅)는 후속 이슈(#15·#16)에서 추가된다.
