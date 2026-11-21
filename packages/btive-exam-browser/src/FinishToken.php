<?php

namespace Btive\ExamBrowser;

/**
 * Finish token untuk `ExamBrowser.finish(token)` di halaman (protocol/SPEC.md §12.2).
 * Diuji terhadap protocol/test-vectors/finish-token.json.
 */
final class FinishToken
{
    public const MAX_SKEW_SECONDS = 300;

    public static function create(#[\SensitiveParameter] string $sessionKey, string $sessionId, int|string $ts): string
    {
        $ts = (string) $ts;

        return "v1.{$ts}.".hash_hmac('sha256', "BTEB-FINISH-v1\n{$sessionId}\n{$ts}", $sessionKey);
    }

    /**
     * @return 'ok'|'invalid_format'|'stale'|'bad_mac'
     */
    public static function verify(#[\SensitiveParameter] string $sessionKey, string $sessionId, string $token, int $now): string
    {
        if (preg_match('/\Av1\.([0-9]{1,12})\.([0-9a-f]{64})\z/', $token, $m) !== 1) {
            return 'invalid_format';
        }

        if (abs($now - (int) $m[1]) > self::MAX_SKEW_SECONDS) {
            return 'stale';
        }

        return hash_equals(hash_hmac('sha256', "BTEB-FINISH-v1\n{$sessionId}\n{$m[1]}", $sessionKey), $m[2]) ? 'ok' : 'bad_mac';
    }
}
