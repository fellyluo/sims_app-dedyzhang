<?php

namespace Btive\ExamBrowser;

/**
 * Verifikasi request dari Exam Browser di sisi target (protocol/SPEC.md §11.3), framework-agnostic.
 *
 * Urutan: format header → skew waktu → introspeksi → HMAC → status → ujian → level.
 * Bila HMAC gagal dengan data dari CACHE, introspeksi diulang sekali tanpa cache (session_key berganti
 * setelah resume, SPEC §8.5).
 */
final class Verifier
{
    public function __construct(private readonly Introspector $introspector) {}

    public function verify(
        ?string $sessionHeader,
        ?string $tsHeader,
        ?string $hashHeader,
        string $canonicalUrl,
        int $requiredLevel,
        ?string $expectedExamSessionId = null,
        ?int $now = null,
    ): VerificationResult {
        $now ??= time();

        if (! RequestHash::headersValid($sessionHeader, $tsHeader, $hashHeader)) {
            return new VerificationResult('eb_missing', requiredLevel: $requiredLevel);
        }

        if (abs($now - (int) $tsHeader) > RequestHash::MAX_SKEW_SECONDS) {
            return new VerificationResult('eb_stale', requiredLevel: $requiredLevel);
        }

        try {
            $info = $this->introspector->fetch((string) $sessionHeader);
            if ($info === null) {
                return new VerificationResult('eb_session_unknown', requiredLevel: $requiredLevel);
            }

            $hashOk = RequestHash::verify($info->sessionKey(), $sessionHeader, $tsHeader, $hashHeader, $canonicalUrl, $now) === 'ok';

            // Ulang tanpa cache HANYA bila data berasal dari cache (mencegah amplifikasi oleh request palsu).
            if (! $hashOk && $info->fromCache) {
                $info = $this->introspector->fetch((string) $sessionHeader, fresh: true);
                if ($info === null) {
                    return new VerificationResult('eb_session_unknown', requiredLevel: $requiredLevel);
                }
                $hashOk = RequestHash::verify($info->sessionKey(), $sessionHeader, $tsHeader, $hashHeader, $canonicalUrl, $now) === 'ok';
            }
        } catch (ConsoleUnavailable) {
            return new VerificationResult('eb_console_unavailable', requiredLevel: $requiredLevel);
        }

        if (! $hashOk) {
            return new VerificationResult('eb_bad_hash', $info, $requiredLevel);
        }

        if ($info->status !== 'active') {
            return new VerificationResult('eb_session_inactive', $info, $requiredLevel);
        }

        if ($expectedExamSessionId !== null && ! hash_equals($expectedExamSessionId, $info->examSessionId)) {
            return new VerificationResult('eb_wrong_exam', $info, $requiredLevel);
        }

        if ($info->effectiveLevel < $requiredLevel) {
            return new VerificationResult('eb_level_insufficient', $info, $requiredLevel);
        }

        return new VerificationResult('ok', $info, $requiredLevel);
    }
}
