<?php

namespace Btive\ExamBrowser;

/**
 * URL kanonis sisi server (protocol/SPEC.md §7): skema & host huruf kecil, port default dihapus,
 * request-target PERSIS seperti di request line (jangan pakai helper yang mengurutkan query).
 */
final class CanonicalUrl
{
    public static function fromParts(string $scheme, string $host, ?int $port, string $requestTarget): string
    {
        $scheme = strtolower($scheme);
        $host = strtolower($host);
        $default = ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);
        $hostPort = ($port === null || $default) ? $host : "{$host}:{$port}";
        $target = $requestTarget === '' ? '/' : $requestTarget;

        // Fragment tidak pernah dikirim browser; buang bila ada (defensif).
        $hash = strpos($target, '#');
        if ($hash !== false) {
            $target = substr($target, 0, $hash);
        }

        return "{$scheme}://{$hostPort}{$target}";
    }
}
