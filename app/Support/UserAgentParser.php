<?php

namespace App\Support;

/**
 * Turns a raw User-Agent header into a short "Browser Version (OS)"
 * description for the profile sessions page - not a fingerprinting tool, just
 * a friendlier label than the raw header string.
 *
 * One real limitation: Windows 11 never changed its User-Agent token from
 * Windows 10's ("Windows NT 10.0") - browsers stopped exposing a distinct
 * value, so this reports "Windows 10" for both. Telling them apart would
 * need the Sec-CH-UA-Platform-Version client hint header, which isn't
 * captured anywhere today (only the User-Agent header is stored on the
 * session record).
 */
class UserAgentParser
{
    public static function describe(?string $userAgent): ?string
    {
        if (empty($userAgent)) {
            return null;
        }

        $browser = self::browser($userAgent);
        $os = self::os($userAgent);

        return $os !== null ? "{$browser} ({$os})" : $browser;
    }

    private static function browser(string $userAgent): string
    {
        return match (true) {
            (bool) preg_match('/Edg\/([\d.]+)/', $userAgent, $m) => "Edge {$m[1]}",
            (bool) preg_match('/OPR\/([\d.]+)/', $userAgent, $m) => "Opera {$m[1]}",
            (bool) preg_match('/Firefox\/([\d.]+)/', $userAgent, $m) => "Firefox {$m[1]}",
            (bool) preg_match('/CriOS\/([\d.]+)/', $userAgent, $m) => "Chrome {$m[1]}",
            (bool) preg_match('/Chrome\/([\d.]+)/', $userAgent, $m) => "Chrome {$m[1]}",
            (bool) preg_match('/Version\/([\d.]+).*Safari\//', $userAgent, $m) => "Safari {$m[1]}",
            (bool) preg_match('/Safari\/([\d.]+)/', $userAgent, $m) => "Safari {$m[1]}",
            default => 'Unknown browser',
        };
    }

    private static function os(string $userAgent): ?string
    {
        return match (true) {
            (bool) preg_match('/Windows NT ([\d.]+)/', $userAgent, $m) => self::windowsVersion($m[1]),
            (bool) preg_match('/iPhone OS ([\d_]+)/', $userAgent, $m) => 'iOS '.str_replace('_', '.', $m[1]),
            (bool) preg_match('/CPU OS ([\d_]+)/', $userAgent, $m) => 'iPadOS '.str_replace('_', '.', $m[1]),
            (bool) preg_match('/Android ([\d.]+)/', $userAgent, $m) => "Android {$m[1]}",
            (bool) preg_match('/Mac OS X ([\d_.]+)/', $userAgent, $m) => 'macOS '.str_replace('_', '.', $m[1]),
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };
    }

    private static function windowsVersion(string $ntVersion): string
    {
        // Windows 11 reports the same "NT 10.0" token as Windows 10 - see
        // the class doc comment, this really can't be told apart here.
        return match ($ntVersion) {
            '10.0' => 'Windows 10',
            '6.3' => 'Windows 8.1',
            '6.2' => 'Windows 8',
            '6.1' => 'Windows 7',
            default => "Windows (NT {$ntVersion})",
        };
    }
}
