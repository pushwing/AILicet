# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- 주 사용자: 라이선스 발급·상태 관리·상품·회원 관리를 수행하는 운영자와 대행사 담당자.
- 보조 사용자: 자신의 라이선스와 고객센터를 이용하는 고객.

## Product Purpose

AILicet은 AIvance 제품군을 위한 온·오프라인 라이선스 인증·관리 시스템이다. 운영자와 대행사가 고객·상품·모듈·라이선스를 관리하고 발급하며, 클라이언트 프로그램은 인증 API로 라이선스 유효성을 확인한다.

## Positioning

하나의 제품 안에서 노드락의 Ed25519 서명 파일 기반 오프라인 검증과 플로팅의 서버 기반 온라인 활성화·사용량 검증을 함께 제공한다. 회원 인증은 AITessera JWT 검증에 위임하며, 라이선스 발급·상태 이력·인증 API를 분리된 책임으로 운영한다.

## Operating Context

- 운영자 콘솔은 회원, 상품·모듈, 라이선스, 감사 로그, 문의와 알림을 관리한다.
- 대행사는 자신이 담당하는 고객과 라이선스를 소유권 범위 안에서 관리·발급한다.
- 고객은 자신의 라이선스, 프로필, 고객센터와 알림을 사용한다.
- 클라이언트 프로그램은 노드락 또는 플로팅 유형에 맞는 frontApi 인증 경로를 호출한다.

## Capabilities and Constraints

- 서버 렌더링 콘솔은 CodeIgniter 4/PHP 기반이며, 클라이언트 인증 API는 pure PHP frontApi로 제공한다.
- 노드락은 호스트 ID에 바인딩된 서명 파일을 배포하고, 플로팅은 관리키와 온라인 활성화·유효성·사용량 차감으로 동작한다.
- 역할별 권한과 고객·대행사 소유권 범위를 유지해야 한다.
- 기존 UI는 자체 디자인 시스템, AG Grid Community, Chart.js를 사용한다.
- 화면 출력에는 이스케이프를 적용하고 상태 변경 폼에는 CSRF 보호를 적용한다.

## Brand Commitments

- 제품명은 AILicet이며, 태그라인은 "Permission, verified."다.
- 기존 로고 자산은 `assets/logo/`에 있고, UI 기본 색상은 `--color-primary: #0F6E56`, 보조 색상은 `--color-secondary: #1D9E75`다.
- 기존 화면의 CSS 변수와 공통 컴포넌트 클래스는 `docs/ui-guide.md`와 `public/assets/css/aicura.css`를 기준으로 보존한다.

## Evidence on Hand

- 제품 범위와 아키텍처: `README.md`, `docs/ailicet-architecture.md`
- UI 규약: `docs/ui-guide.md`, `public/assets/css/aicura.css`
- 로고 자산: `assets/logo/`
- 정식 접근성 표준 또는 사용자별 보조기술 요구사항은 저장소에서 확인되지 않았다.

## Product Principles

1. 라이선스 유형별 인증 방식과 관리 정보를 명확히 구분한다.
2. 역할과 소유권에 맞는 최소 권한을 유지한다.
3. 발급·상태 변경·사용량 이력의 운영 추적성을 보장한다.
4. 오프라인 검증과 온라인 검증의 제약을 사용자 흐름에 정확히 반영한다.
5. 운영 콘솔의 반복 업무를 일관된 UI 구성과 안전한 입력 처리로 지원한다.
