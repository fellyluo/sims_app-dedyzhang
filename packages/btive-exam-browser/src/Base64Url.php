<?php

namespace Btive\ExamBrowser;

final class Base64Url
{
    public static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function decode(string $value): ?string
    {
        if (preg_match('/\A[A-Za-z0-9_-]*\z/', $value) !== 1) {
            return null;
        }

        $bytes = base64_decode(strtr($value, '-_', '+/'), true);

        return $bytes === false ? null : $bytes;
    }
}
