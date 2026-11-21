<?php

namespace App\Services;

use App\Models\UjianAttempt;
use Btive\ExamBrowser\ConsoleUnavailable;
use Btive\ExamBrowser\Laravel\ExamBrowserClient;
use Illuminate\Support\Facades\Log;

/**
 * Fitur 02 TASK-008: attempt yang selesai TANPA lewat tombol Kumpulkan (waktu habis lewat cron/halaman,
 * diselesaikan paksa guru) tetap memberi tahu Exam Console (API-09) agar perangkat siswa terbuka sendiri,
 * memakai sesi Exam Browser yang terikat ke attempt (audit M1). Gagal memanggil Console tidak pernah
 * menggagalkan penilaian — siswa masih bisa keluar lewat kode pengawas / password darurat.
 */
class ExamBrowserSelesai
{
    /** Setelah Console sekali tidak terjangkau, panggilan berikutnya dalam instance ini dilewati (loop massal). */
    private bool $consoleMati = false;

    public function __construct(private readonly ExamBrowserClient $client) {}

    public function beritahu(UjianAttempt $attempt): void
    {
        $sesi = $attempt->eb_client_session_id;

        if ($sesi === null || $this->consoleMati
            || (string) config('exam-browser.console_url') === '' || (string) config('exam-browser.api_key') === '') {
            return;
        }

        try {
            $this->client->finish($sesi);
        } catch (ConsoleUnavailable $e) {
            $this->consoleMati = true;
            Log::warning('Ujian: gagal memanggil finish Exam Console (auto-submit)', ['attempt' => $attempt->uuid, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::warning('Ujian: gagal memanggil finish Exam Console (auto-submit)', ['attempt' => $attempt->uuid, 'error' => $e->getMessage()]);
        }
    }
}
