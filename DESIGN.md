---
name: AILicet
description: "Permission, verified. — 온·오프라인 라이선스 인증·관리 콘솔"
colors:
  primary: "#0F6E56"
  primary-dark: "#0A5241"
  primary-soft: "#E6F2ED"
  secondary: "#1D9E75"
  secondary-soft: "#E4F5EE"
  background: "#F4F6F5"
  surface: "#FFFFFF"
  border: "#E3E8E6"
  border-strong: "#CBD4D0"
  text: "#1B2420"
  text-muted: "#66756F"
  nav-text: "#DCEAE4"
  nav-item-text: "#C6D8D1"
  white: "#FFFFFF"
  success: "#1D9E75"
  warning: "#C9820B"
  danger: "#D64545"
  info: "#2F80C4"
  warning-soft: "#FBF0DA"
  danger-soft: "#FBE3E3"
  muted-soft: "#EDF1EF"
  danger-border: "#F3C6C6"
  danger-text: "#A83232"
  info-soft: "#E7F1FB"
  info-border: "#C9E0F5"
  info-text: "#245C8C"
  backdrop: "rgba(16, 40, 32, 0.4)"
typography:
  body:
    fontFamily: "Pretendard, -apple-system, BlinkMacSystemFont, Apple SD Gothic Neo, Segoe UI, Roboto, Noto Sans KR, sans-serif"
    fontSize: "14px"
    fontWeight: 400
    lineHeight: 1.5
  title:
    fontFamily: "Pretendard, -apple-system, BlinkMacSystemFont, Apple SD Gothic Neo, Segoe UI, Roboto, Noto Sans KR, sans-serif"
    fontSize: "22px"
    fontWeight: 700
    lineHeight: 1.5
  metric:
    fontFamily: "Pretendard, -apple-system, BlinkMacSystemFont, Apple SD Gothic Neo, Segoe UI, Roboto, Noto Sans KR, sans-serif"
    fontSize: "26px"
    fontWeight: 700
    lineHeight: 1.5
  subtitle:
    fontFamily: "Pretendard, -apple-system, BlinkMacSystemFont, Apple SD Gothic Neo, Segoe UI, Roboto, Noto Sans KR, sans-serif"
    fontSize: "20px"
    fontWeight: 600
  topbar:
    fontFamily: "Pretendard, -apple-system, BlinkMacSystemFont, Apple SD Gothic Neo, Segoe UI, Roboto, Noto Sans KR, sans-serif"
    fontSize: "16px"
    fontWeight: 600
  navigation:
    fontFamily: "Pretendard, -apple-system, BlinkMacSystemFont, Apple SD Gothic Neo, Segoe UI, Roboto, Noto Sans KR, sans-serif"
    fontSize: "18px"
    fontWeight: 700
  label:
    fontFamily: "Pretendard, -apple-system, BlinkMacSystemFont, Apple SD Gothic Neo, Segoe UI, Roboto, Noto Sans KR, sans-serif"
    fontSize: "13px"
    fontWeight: 600
  small:
    fontFamily: "Pretendard, -apple-system, BlinkMacSystemFont, Apple SD Gothic Neo, Segoe UI, Roboto, Noto Sans KR, sans-serif"
    fontSize: "12px"
    fontWeight: 400
  caption:
    fontFamily: "Pretendard, -apple-system, BlinkMacSystemFont, Apple SD Gothic Neo, Segoe UI, Roboto, Noto Sans KR, sans-serif"
    fontSize: "11px"
    fontWeight: 400
rounded:
  sm: "6px"
  md: "10px"
  lg: "16px"
  icon: "2px"
  pill: "999px"
spacing:
  compact: "8px"
  field: "16px"
  section: "24px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.surface}"
    rounded: "{rounded.sm}"
    padding: "9px 16px"
    height: "36px"
  button-ghost:
    backgroundColor: "transparent"
    textColor: "{colors.text}"
    rounded: "{rounded.sm}"
    padding: "9px 16px"
    height: "36px"
  card:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.md}"
    padding: "20px"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.text}"
    rounded: "{rounded.sm}"
    padding: "11px 13px"
---

# Design System: AILicet

## Overview

**Creative North Star: "검증 가능한 운영 콘솔"**

AILicet은 운영자가 라이선스 상태와 고객 정보를 빠르게 판독하고 안전하게 변경하는 데 집중하는 차분한 업무용 인터페이스다. 밝은 표면과 짙은 녹색 사이드바를 분명히 대비시키고, 주요 액션에는 제한적으로 Primary를 사용한다.

정보 밀도는 관리 콘솔에 맞게 유지하되, 카드·폼·배지는 반복되는 업무 단위를 명확히 구분한다. 모바일에서는 업무 경로를 숨기지 않고 drawer 내비게이션과 단일 열 레이아웃으로 같은 정보를 제공한다.

**Key Characteristics:**
- 녹색 기반의 신뢰감 있는 상태·권한 표현
- 얕은 그림자와 얇은 테두리로 구분한 밝은 작업 표면
- 짧고 명확한 한국어 레이블 및 상태 배지

## Colors

주 색상은 승인·정상·주요 액션을 위한 짙은 녹색이며, 경고·위험·정보는 의미별 시맨틱 토큰만 사용한다.

### Primary
- **검증 녹색** (`#0F6E56`): 주요 버튼, 링크, 활성 컨트롤의 기본색이다.
- **짙은 탐색 녹색** (`#0A5241`): 사이드바와 Primary hover에 사용한다.
- **연한 녹색 표면** (`#E6F2ED`): 아바타·보조 강조 배경에 사용한다.

### Secondary
- **활성 청록 녹색** (`#1D9E75`): 활성 내비게이션, 성공 상태와 포커스의 강조색이다.

### Neutral
- **작업 배경** (`#F4F6F5`): 앱 전체의 배경이다.
- **표면** (`#FFFFFF`): 카드·입력 필드·상단바의 기본 배경이다.
- **본문** (`#1B2420`), **보조 본문** (`#66756F`): 정보 위계를 표현한다.

### Named Rules
**의미 우선 규칙.** 성공·경고·위험·정보는 각 시맨틱 토큰을 사용하고, 상태를 색상만으로 구분하지 않는다.

## Typography

**Body Font:** Pretendard 및 시스템 한글 sans-serif fallback

**Character:** 짧은 관리 업무 레이블을 빠르게 판독할 수 있는 단정한 sans-serif 체계다.

### Hierarchy
- **Title** (700, 22px): 페이지 제목에 사용한다.
- **Body** (400, 14px, 1.5): 기본 본문·입력값·운영 정보에 사용한다.
- **Label** (600, 13px): 필드 이름과 카드 헤더에 사용한다.

## Layout

데스크톱은 240px 고정 사이드바, 60px 상단바, 24px 콘텐츠 여백을 기본으로 한다. 폼은 2열, 상세 정보는 주·보조 패널의 2열을 사용한다.

900px 이하에서는 메뉴를 drawer로 전환하고 폼·상세 정보는 한 열로 재배치한다. 640px 이하에서는 콘텐츠 여백을 16px로 줄이고 주요 버튼의 최소 높이를 44px로 유지한다.

## Elevation & Depth

기본 표면은 1px 테두리와 낮은 앰비언트 그림자로 구분한다. 작은 카드에는 `0 1px 2px rgba(16, 40, 32, 0.06)`, drawer에는 `0 4px 16px rgba(16, 40, 32, 0.08)`을 사용한다.

## Shapes

입력과 버튼은 6px, 카드와 통계 패널은 10px, 인증 카드에는 16px 반경을 사용한다. 입력은 강한 테두리를 기본으로 두고 포커스 시 보조색 테두리와 부드러운 링을 사용한다.

## Components

### Buttons
- **Shape:** 6px 반경, 기본 최소 높이 36px, 모바일 44px
- **Primary:** `#0F6E56` 바탕과 흰색 텍스트를 사용한다.
- **Ghost:** 표면 배경을 유지하고 강한 테두리로 보조 액션을 표현한다.
- **Focus:** 3px 보조색 outline과 3px offset을 제공한다.

### Chips
- **Style:** 둥근 pill 배지와 상태 텍스트를 함께 사용한다.
- **State:** 성공·경고·위험·보관 상태는 시맨틱 색상과 레이블을 같이 제공한다.

### Cards / Containers
- **Corner Style:** 10px
- **Background:** 흰색 표면
- **Border:** `#E3E8E6` 1px
- **Internal Padding:** 헤더 16px 20px, 본문 20px

### Inputs / Fields
- **Style:** 흰색 배경, `#CBD4D0` 1px 테두리, 6px 반경
- **Focus:** Secondary 테두리와 soft 색상 링

### Navigation
- **Desktop:** 짙은 녹색 고정 사이드바와 활성 메뉴의 Secondary 배경
- **Mobile:** 메뉴 버튼으로 여는 drawer, backdrop 클릭과 Escape 닫기, 현재 페이지 `aria-current` 제공

## Do's and Don'ts

### Do:
- **Do** CSS 변수로 정의된 색상·반경·그림자 토큰을 사용한다.
- **Do** 상태를 배지 텍스트와 시맨틱 색상으로 함께 표현한다.
- **Do** 모바일에서 모든 운영 메뉴와 주요 액션을 접근 가능하게 유지한다.
- **Do** 키보드 포커스를 분명히 표시한다.

### Don't:
- **Don't** 페이지별 인라인 색상·간격을 반복해 공통 토큰 체계를 우회한다.
- **Don't** 모바일에서 핵심 내비게이션이나 운영 기능을 숨긴다.
- **Don't** 색상만으로 성공·경고·위험 상태를 구분한다.
