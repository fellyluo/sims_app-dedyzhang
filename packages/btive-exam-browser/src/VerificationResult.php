<?php

namespace Btive\ExamBrowser;

final class VerificationResult
{
    public function __construct(
        public readonly string $code,
        public readonly ?SessionInfo $session = null,
        public readonly ?int $requiredLevel = null,
    ) {}

    public function ok(): bool
    {
        return $this->code === 'ok';
    }

    public function message(): string
    {
        return match ($this->code) {
            'ok' => 'OK',
            'eb_missing' => 'Halaman ini hanya bisa dibuka dari aplikasi B\'tive Exam Browser.',
            'eb_stale' => 'Jam perangkat tidak sesuai. Sinkronkan waktu perangkat lalu muat ulang.',
            'eb_session_unknown' => 'Sesi Exam Browser tidak dikenal. Masukkan ulang kode ujian.',
            'eb_bad_hash' => 'Permintaan tidak dapat diverifikasi. Muat ulang halaman dari Exam Browser.',
            'eb_session_inactive' => 'Sesi Exam Browser Anda sudah tidak aktif. Hubungi pengawas.',
            'eb_wrong_exam' => 'Sesi Exam Browser ini untuk ujian lain.',
            'eb_level_insufficient' => 'Perangkat ini belum memenuhi level keamanan ujian.',
            'eb_console_unavailable' => 'Layanan verifikasi sedang tidak tersedia. Coba lagi sebentar lagi.',
            default => 'Verifikasi Exam Browser gagal.',
        };
    }
}
