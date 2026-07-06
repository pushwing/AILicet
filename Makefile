.PHONY: serve serve-spark migrate test analyse check swagger routes cache-clear help

PORT ?= 8300

## serve: 개발 서버 — FrankenPHP (포트 8300, 권장)
serve:
	frankenphp php-server --listen :$(PORT) --root public/ --watch

## serve-spark: 개발 서버 — CI4 내장 (포트 8300)
serve-spark:
	php spark serve --port $(PORT)

## migrate: DB 마이그레이션
migrate:
	php spark migrate

## test: PHPUnit 단독 실행
test:
	composer test

## analyse: PHPStan 단독 실행 (level 6)
analyse:
	composer analyse

## check: PHPStan + PHPUnit 순차 실행
check:
	composer check

## swagger: OpenAPI 스펙 생성 (public/swagger.json)
swagger:
	php spark swagger:generate

## routes: 라우트 목록
routes:
	php spark routes

## cache-clear: 캐시 삭제
cache-clear:
	php spark cache:clear

## help: 커맨드 목록
help:
	@grep -E '^## ' $(MAKEFILE_LIST) | sed 's/## //'
