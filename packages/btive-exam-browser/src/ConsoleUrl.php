<?php

namespace Btive\ExamBrowser;

/**
 * URL Exam Console wajib HTTPS — API key dan session_key ikut di request/respons introspeksi.
 * http:// hanya diizinkan untuk pengembangan lokal (localhost / 127.0.0.1 / ::1).
 */
final class ConsoleUrl
{
    public static function aman(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower(trim($parts['host'], '[]'));

        return $scheme === 'https' || ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1', '::1'], true));
    }

    /**
     * @throws ConsoleUnavailable
     */
    public static function pastikanAman(string $url): void
    {
        if (! self::aman($url)) {
            throw new ConsoleUnavailable('EXAM_BROWSER_CONSOLE_URL wajib https:// — permintaan ke Console tidak dikirim.');
        }
    }
}
