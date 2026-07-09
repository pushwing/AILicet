<?php

declare(strict_types=1);

use App\Libraries\HtmlSanitizer;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * HTML 화이트리스트 정화기 단위 테스트.
 *
 * @internal
 */
final class HtmlSanitizerTest extends CIUnitTestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new HtmlSanitizer();
    }

    public function testKeepsAllowedTags(): void
    {
        $html   = '<p>안녕 <strong>굵게</strong> <em>기울임</em></p>';
        $result = $this->sanitizer->sanitize($html);

        $this->assertStringContainsString('<strong>굵게</strong>', $result);
        $this->assertStringContainsString('<em>기울임</em>', $result);
        $this->assertStringContainsString('<p>', $result);
    }

    public function testKeepsListStructure(): void
    {
        $html   = '<ul><li>하나</li><li>둘</li></ul>';
        $result = $this->sanitizer->sanitize($html);

        $this->assertStringContainsString('<ul>', $result);
        $this->assertStringContainsString('<li>하나</li>', $result);
    }

    public function testRemovesScriptTagButKeepsText(): void
    {
        $html   = '<p>정상</p><script>alert("xss")</script>';
        $result = $this->sanitizer->sanitize($html);

        $this->assertStringNotContainsString('<script', $result);
        $this->assertStringContainsString('<p>정상</p>', $result);
    }

    public function testStripsEventHandlerAttributes(): void
    {
        $html   = '<p onclick="steal()">클릭</p>';
        $result = $this->sanitizer->sanitize($html);

        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringContainsString('클릭', $result);
    }

    public function testStripsInlineStyle(): void
    {
        $html   = '<p style="color:red">스타일</p>';
        $result = $this->sanitizer->sanitize($html);

        $this->assertStringNotContainsString('style=', $result);
    }

    public function testBlocksJavascriptSchemeHref(): void
    {
        $html   = '<a href="javascript:alert(1)">링크</a>';
        $result = $this->sanitizer->sanitize($html);

        $this->assertStringNotContainsString('javascript:', $result);
        // 태그 자체는 남고 텍스트도 보존되나 위험 href 는 제거된다.
        $this->assertStringContainsString('링크', $result);
        $this->assertStringNotContainsString('href="javascript', $result);
    }

    public function testBlocksJavascriptSchemeWithEmbeddedControlChars(): void
    {
        // 브라우저는 URL 파싱 시 탭·개행을 제거하므로 `java\tscript:` 는 실행 가능한 우회다.
        foreach (["java\tscript:alert(1)", "java\nscript:alert(1)", "java\r\nscript:alert(1)"] as $payload) {
            $result = $this->sanitizer->sanitize('<a href="' . $payload . '">링크</a>');
            $this->assertStringNotContainsString('script:', str_replace(["\t", "\n", "\r"], '', $result));
            $this->assertStringNotContainsString('href', $result);
        }
    }

    public function testBlocksHtmlEntityEncodedTabInScheme(): void
    {
        // &#9; 는 DOMDocument 파싱 시 실제 탭으로 디코딩된다.
        $result = $this->sanitizer->sanitize('<a href="java&#9;script:alert(1)">링크</a>');
        $this->assertStringNotContainsString('href', $result);
        $this->assertStringContainsString('링크', $result);
    }

    public function testKeepsSafeHttpHref(): void
    {
        $html   = '<a href="https://example.com">사이트</a>';
        $result = $this->sanitizer->sanitize($html);

        $this->assertStringContainsString('href="https://example.com"', $result);
    }

    public function testUnwrapsDisallowedTagPreservingContent(): void
    {
        $html   = '<div><span>내용</span></div>';
        $result = $this->sanitizer->sanitize($html);

        $this->assertStringNotContainsString('<div>', $result);
        $this->assertStringNotContainsString('<span>', $result);
        $this->assertStringContainsString('내용', $result);
    }

    public function testEmptyInputReturnsEmptyString(): void
    {
        $this->assertSame('', $this->sanitizer->sanitize(''));
        $this->assertSame('', $this->sanitizer->sanitize('   '));
    }

    public function testEmptyBlockOnlyReturnsEmptyString(): void
    {
        // 텍스트 없는 빈 블록은 빈 콘텐츠로 취급한다.
        $this->assertSame('', $this->sanitizer->sanitize('<p></p>'));
        $this->assertSame('', $this->sanitizer->sanitize('<p><br></p>'));
    }

    public function testRemovesImgTag(): void
    {
        $html   = '<p>텍스트</p><img src="x" onerror="alert(1)">';
        $result = $this->sanitizer->sanitize($html);

        $this->assertStringNotContainsString('<img', $result);
        $this->assertStringNotContainsString('onerror', $result);
    }
}
