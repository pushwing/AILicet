<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Exceptions\AiException;

/**
 * AI(Anthropic) 호출 공용 계약.
 *
 * 로그 분류·요약, 이상탐지 보강, 문의 답변 초안 등 여러 유스케이스가 공유한다.
 * 프롬프트 구성·응답 파싱은 각 Service 레이어의 책임이고, 이 계약은 저수준 호출만 담당한다.
 */
interface AiClient
{
    /**
     * API 키가 설정되어 실제 호출이 가능한지 여부.
     * 미설정이면 각 Service 는 AI 처리를 건너뛰어(no-op) 파이프라인이 깨지지 않게 한다.
     */
    public function isConfigured(): bool;

    /**
     * 단일 메시지 완성 요청. system 지침과 user 프롬프트를 주고 모델의 텍스트 응답을 받는다.
     *
     * @param string $model     모델 ID (예: claude-haiku-4-5, claude-sonnet-5)
     * @param string $system    system 지침
     * @param string $prompt    user 프롬프트
     * @param int    $maxTokens 최대 출력 토큰
     *
     * @return string 모델이 생성한 텍스트
     *
     * @throws AiException 미설정·통신 실패·비2xx 응답
     */
    public function complete(string $model, string $system, string $prompt, int $maxTokens = 1024): string;
}
