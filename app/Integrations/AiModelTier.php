<?php

declare(strict_types=1);

namespace App\Integrations;

/**
 * AI 작업 등급 — 서비스는 구체 모델명 대신 등급을 넘기고, 각 AiClient 가 제공자별 모델로 매핑한다.
 *
 * 제공자(Anthropic·Groq)마다 모델명이 달라, 이 추상화로 서비스 코드가 제공자에 독립적이게 한다.
 */
enum AiModelTier
{
    /** 저비용·저지연 — 분류·요약 등 단순 작업. */
    case Cheap;

    /** 추론 — 이상 탐지·자연어 질의 등 판단이 필요한 작업. */
    case Reasoning;
}
