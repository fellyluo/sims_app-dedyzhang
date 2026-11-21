<?php

namespace App\Services;

use App\Models\UjianAttempt;
use App\Models\UjianPelanggaran;
use Illuminate\Support\Facades\DB;

/**
 * Buka-kunci attempt yg "terkeluar" (dikunci=true krn keluar fullscreen/pindah
 * tab) — logika diekstrak dari UjianMonitorController::resetLock() SUPAYA
 * dipakai bersama oleh monitor ujian lama (otorisasi manage() — guru pengampu
 * mapel) DAN monitor ruangan baru (otorisasi berbasis penugasan pengawas
 * ruangan) TANPA duplikasi & tanpa melebarkan cakupan otorisasi resetLock lama.
 */
class UjianKuncian
{
    /** @param string $tipeLog Nilai UjianPelanggaran::tipe pencatat siapa yg membuka (reset_oleh_guru|reset_oleh_pengawas). */
    public function bukaKunci(UjianAttempt $attempt, string $tipeLog = 'reset_oleh_guru'): void
    {
        DB::transaction(function () use ($attempt, $tipeLog) {
            // Audit M1: membuka kunci = izin pengawas untuk melanjutkan, termasuk dari perangkat
            // Exam Browser lain → lepas ikatan sesi; request terverifikasi berikutnya mengikat ulang.
            $attempt->forceFill(['dikunci' => false, 'eb_client_session_id' => null])->save();
            UjianPelanggaran::create(['id_attempt' => $attempt->uuid, 'id_siswa' => $attempt->id_siswa, 'tipe' => $tipeLog]);
        });
    }
}
