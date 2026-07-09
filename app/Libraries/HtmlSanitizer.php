<?php

declare(strict_types=1);

namespace App\Libraries;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * 리치 텍스트 HTML 화이트리스트 정화기.
 *
 * 운영자가 리치 에디터로 입력한 HTML 에서 허용 태그·속성만 남기고
 * 나머지(스크립트·이벤트 핸들러·인라인 스타일·위험 스킴 링크 등)를 제거한다.
 * DOM 파싱 기반이라 문자열 정규식 방식보다 우회가 어렵다.
 */
final class HtmlSanitizer
{
    /**
     * 허용 태그 → 허용 속성 목록.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        'p'          => [],
        'br'         => [],
        'strong'     => [],
        'b'          => [],
        'em'         => [],
        'i'          => [],
        'u'          => [],
        's'          => [],
        'ul'         => [],
        'ol'         => [],
        'li'         => [],
        'h1'         => [],
        'h2'         => [],
        'h3'         => [],
        'blockquote' => [],
        'a'          => ['href'],
    ];

    /** href 에 허용할 URL 스킴(그 외 javascript: 등은 제거). */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto'];

    /**
     * 입력 HTML 을 정화해 안전한 HTML 문자열로 반환한다.
     * 빈 입력·태그만 있는 빈 콘텐츠는 빈 문자열을 반환한다.
     */
    public function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $dom = new DOMDocument();

        // libxml 경고를 억제하고(불완전 마크업 허용), UTF-8 을 강제한다.
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $wrapper = $dom->getElementsByTagName('div')->item(0);
        if (! $wrapper instanceof DOMElement) {
            return '';
        }

        $this->cleanChildren($wrapper);

        $result = '';
        foreach (iterator_to_array($wrapper->childNodes) as $child) {
            $result .= $dom->saveHTML($child);
        }
        $result = trim($result);

        // 텍스트 없이 빈 블록(<p></p>·<p><br></p>)만 남았다면 빈 콘텐츠로 취급한다.
        if (trim(strip_tags($result)) === '') {
            return '';
        }

        return $result;
    }

    /**
     * 노드의 자식들을 순회하며 비허용 태그를 언랩(내용 보존)하거나 제거하고,
     * 허용 태그의 비허용 속성을 제거한다.
     */
    private function cleanChildren(DOMNode $node): void
    {
        // 순회 중 DOM 을 변경하므로 자식 스냅샷을 먼저 확보한다.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                // 텍스트·주석 등 — 주석은 제거, 텍스트는 유지.
                if ($child->nodeType === XML_COMMENT_NODE) {
                    $child->parentNode?->removeChild($child);
                }

                continue;
            }

            $tag = strtolower($child->nodeName);

            // 먼저 자식을 재귀 정화한다.
            $this->cleanChildren($child);

            if (! isset(self::ALLOWED[$tag])) {
                // 비허용 태그: 태그는 벗기고 내부 콘텐츠는 부모로 끌어올려 보존한다.
                $this->unwrap($child);

                continue;
            }

            $this->stripAttributes($child, self::ALLOWED[$tag]);
        }
    }

    /**
     * 허용 속성 외 모두 제거한다. href 는 안전 스킴만 통과시킨다.
     *
     * @param list<string> $allowedAttrs
     */
    private function stripAttributes(DOMElement $element, array $allowedAttrs): void
    {
        // 속성 노드도 순회 중 변경되므로 이름 스냅샷 확보.
        $attrNames = [];
        foreach (iterator_to_array($element->attributes ?? []) as $attr) {
            $attrNames[] = $attr->nodeName;
        }

        foreach ($attrNames as $name) {
            if (! in_array(strtolower($name), $allowedAttrs, true)) {
                $element->removeAttribute($name);

                continue;
            }

            if (strtolower($name) === 'href' && ! $this->isSafeHref($element->getAttribute($name))) {
                $element->removeAttribute($name);
            }
        }
    }

    /** href 가 허용 스킴이거나 상대·앵커 경로면 안전으로 판정한다. */
    private function isSafeHref(string $href): bool
    {
        // 브라우저는 URL 파싱 전에 탭·개행 등 C0 제어문자를 제거한다.
        // 정화기도 동일하게 제거해야 `java&#9;script:` 같은 스킴 우회를 막는다.
        $href = trim((string) preg_replace('/[\x00-\x1F\x7F]+/', '', $href));
        if ($href === '') {
            return false;
        }

        // 스킴이 없으면(상대경로·#앵커) 허용.
        if (! preg_match('/^([a-z][a-z0-9+.-]*):/i', $href, $m)) {
            return true;
        }

        return in_array(strtolower($m[1]), self::ALLOWED_SCHEMES, true);
    }

    /** 요소를 제거하되 자식 노드들을 같은 위치의 부모로 이동해 텍스트를 보존한다. */
    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }
}
