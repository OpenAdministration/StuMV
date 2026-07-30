<?php

use App\Support\UserAgentParser;

test('a Firefox on Windows user agent is described as requested', function (): void {
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0';

    expect(UserAgentParser::describe($ua))->toBe('Firefox 153.0 (Windows 10)');
});

test('a Chrome on Windows user agent is parsed correctly', function (): void {
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    expect(UserAgentParser::describe($ua))->toBe('Chrome 126.0.0.0 (Windows 10)');
});

test('an Edge user agent is not misidentified as Chrome', function (): void {
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 Edg/126.0.2592.68';

    expect(UserAgentParser::describe($ua))->toBe('Edge 126.0.2592.68 (Windows 10)');
});

test('a desktop Safari on macOS user agent is parsed correctly', function (): void {
    $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15';

    expect(UserAgentParser::describe($ua))->toBe('Safari 17.4 (macOS 10.15.7)');
});

test('a mobile Safari on iOS user agent is parsed correctly', function (): void {
    $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1';

    expect(UserAgentParser::describe($ua))->toBe('Safari 17.4 (iOS 17.4)');
});

test('a Chrome on Android user agent is parsed correctly', function (): void {
    $ua = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36';

    expect(UserAgentParser::describe($ua))->toBe('Chrome 126.0.0.0 (Android 14)');
});

test('a Firefox on Linux user agent is parsed correctly', function (): void {
    $ua = 'Mozilla/5.0 (X11; Linux x86_64; rv:126.0) Gecko/20100101 Firefox/126.0';

    expect(UserAgentParser::describe($ua))->toBe('Firefox 126.0 (Linux)');
});

test('an empty or missing user agent yields null', function (): void {
    expect(UserAgentParser::describe(null))->toBeNull()
        ->and(UserAgentParser::describe(''))->toBeNull();
});

test('an unrecognized user agent still returns a fallback label instead of erroring', function (): void {
    expect(UserAgentParser::describe('some-bot/1.0'))->toBe('Unknown browser');
});
