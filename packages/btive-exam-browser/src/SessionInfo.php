<?php

namespace Btive\ExamBrowser;

/**
 * Hasil introspeksi API-07 (protocol/SPEC.md §14.2).
 */
final class SessionInfo
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $examSessionId,
        public readonly string $lembagaId,
        public readonly string $status,
        public readonly int $effectiveLevel,
        #[\SensitiveParameter] private readonly string $sessionKeyB64u,
        public readonly ?string $pesertaRef,
        public readonly string $platform,
        public readonly string $appVersion,
        public readonly int $startedAt,
        public readonly int $expiresAt,
        public readonly bool $fromCache = false,
    ) {}

    /**
     * @param  array<string, mixed>  $d
     */
    public static function fromArray(array $d, bool $fromCache = false): ?self
    {
        foreach (['session_id', 'exam_session_id', 'lembaga_id', 'status', 'session_key', 'platform', 'app_version'] as $k) {
            if (! is_string($d[$k] ?? null)) {
                return null;
            }
        }

        if (! is_int($d['effective_level'] ?? null) || ! is_int($d['started_at'] ?? null) || ! is_int($d['expires_at'] ?? null)) {
            return null;
        }

        return new self(
            $d['session_id'], $d['exam_session_id'], $d['lembaga_id'], $d['status'], $d['effective_level'],
            $d['session_key'], is_string($d['peserta_ref'] ?? null) ? $d['peserta_ref'] : null,
            $d['platform'], $d['app_version'], $d['started_at'], $d['expires_at'], $fromCache,
        );
    }

    /**
     * Kunci HMAC 32 byte mentah.
     */
    public function sessionKey(): string
    {
        return (string) Base64Url::decode($this->sessionKeyB64u);
    }

    /**
     * @return array<string, mixed> untuk cache (berisi session_key — simpan terenkripsi)
     */
    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId, 'exam_session_id' => $this->examSessionId, 'lembaga_id' => $this->lembagaId,
            'status' => $this->status, 'effective_level' => $this->effectiveLevel, 'session_key' => $this->sessionKeyB64u,
            'peserta_ref' => $this->pesertaRef, 'platform' => $this->platform, 'app_version' => $this->appVersion,
            'started_at' => $this->startedAt, 'expires_at' => $this->expiresAt,
        ];
    }

    public function __debugInfo(): array
    {
        return ['sessionId' => $this->sessionId, 'status' => $this->status, 'effectiveLevel' => $this->effectiveLevel];
    }
}
