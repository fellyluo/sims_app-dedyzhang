<?php

namespace Btive\ExamBrowser;

/**
 * X-EB-Request-Hash (protocol/SPEC.md §11.1). Kunci = 32 byte MENTAH session_key (bukan teks b64u).
 * Diuji terhadap protocol/test-vectors/request-hash.json.
 */
final class RequestHash
{
    public const MAX_SKEW_SECONDS = 120;

    public static function compute(#[\SensitiveParameter] string $sessionKey, string $ts, string $canonicalUrl): string
    {
        return hash_hmac('sha256', "BTEB-REQ-v1\n{$ts}\n{$canonicalUrl}", $sessionKey);
    }

    /**
     * Pemeriksaan format header (langkah 1 SPEC §11.3).
     */
    public static function headersValid(?string $session, ?string $ts, ?string $hash): bool
    {
        return $session !== null && $ts !== null && $hash !== null
            && preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/', $session) === 1
            && preg_match('/\A[0-9]{1,12}\z/', $ts) === 1
            && preg_match('/\A[0-9a-f]{64}\z/', $hash) === 1;
    }

    /**
     * @return 'ok'|'eb_missing'|'eb_stale'|'eb_bad_hash'
     */
    public static function verify(#[\SensitiveParameter] string $sessionKey, ?string $session, ?string $ts, ?string $hash, string $canonicalUrl, int $now): string
    {
        if (! self::headersValid($session, $ts, $hash)) {
            return 'eb_missing';
        }

        if (abs($now - (int) $ts) > self::MAX_SKEW_SECONDS) {
            return 'eb_stale';
        }

        return hash_equals(self::compute($sessionKey, (string) $ts, $canonicalUrl), (string) $hash) ? 'ok' : 'eb_bad_hash';
    }
}
