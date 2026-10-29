<?php

namespace Dcplibrary\Requests\Tests\Unit;

use Dcplibrary\Requests\Services\NotificationService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Patron-supplied token values must never inject HTML into email bodies,
 * nor expand other tokens, nor break subject lines.
 */
class EmailTokenEscapingTest extends TestCase
{
    private function map(array $overrides = []): array
    {
        return array_merge([
            '{title}'        => '<a href="https://evil.example">Click</a> <script>x</script>',
            '{author}'       => 'Tom & Jerry',
            '{patron_email}' => 'patron@example.org',
            '{request_url}'  => '<a href="https://requests.example/staff/1">View Request</a>',
        ], $overrides);
    }

    #[Test]
    public function body_values_are_html_escaped(): void
    {
        $out = NotificationService::applyTokenMap('<td>{title}</td><td>{author}</td>', $this->map());

        $this->assertStringNotContainsString('<a href="https://evil', $out);
        $this->assertStringNotContainsString('<script>', $out);
        $this->assertStringContainsString('&lt;a href=&quot;https://evil.example&quot;&gt;', $out);
        $this->assertStringContainsString('Tom &amp; Jerry', $out);
    }

    #[Test]
    public function system_button_tokens_stay_html(): void
    {
        $out = NotificationService::applyTokenMap('{request_url}', $this->map());

        $this->assertSame('<a href="https://requests.example/staff/1">View Request</a>', $out);
    }

    #[Test]
    public function values_containing_tokens_are_not_expanded(): void
    {
        $out = NotificationService::applyTokenMap('{title}', $this->map(['{title}' => 'Leak {patron_email} {unknown}']));

        $this->assertSame('Leak {patron_email} {unknown}', $out);
    }

    #[Test]
    public function action_button_marker_cannot_be_injected(): void
    {
        $out = NotificationService::applyTokenMap('{title}', $this->map(['{title}' => '<!--REQUESTS_ACTION_BUTTONS-->']));

        $this->assertStringNotContainsString('<!--REQUESTS_ACTION_BUTTONS-->', $out);
    }

    #[Test]
    public function unknown_template_tokens_are_stripped(): void
    {
        $this->assertSame('Hi ', NotificationService::applyTokenMap('Hi {nope}', $this->map()));
    }

    #[Test]
    public function subjects_are_plain_text_on_one_line(): void
    {
        $out = NotificationService::applyTokenMap(
            'New: {title} / {author} {request_url}',
            $this->map(['{title}' => "Line one\r\nBcc: x@example.org"]),
            html: false
        );

        $this->assertSame('New: Line one Bcc: x@example.org / Tom & Jerry View Request', $out);
    }
}
