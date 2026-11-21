<?php

namespace App\Http\Middleware;

use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianPelanggaran;
use Btive\ExamBrowser\Laravel\EnsureExamBrowser;
use Btive\ExamBrowser\VerificationResult;
use Btive\ExamBrowser\Verifier;
use Btive\ExamBrowser\RequestHash;
use Btive\ExamBrowser\SessionInfo;
use Illuminate\Support\Facades\Crypt;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * B'tive Exam Browser per ujian (docs/exam-browser fitur 02 TASK-007).
 *
 * Ujian dengan `exam_browser_level` NULL → tidak disentuh sama sekali (perilaku lama).
 * Selain itu request diverifikasi SDK btive/exam-browser (X-EB-Request-Hash, level, sesi ujian Console).
 * Mode dari config('exam-browser.mode'): `report` (default — catat, tetap lanjut) atau `enforce` (tolak).
 * Hasil disimpan di atribut request `exam_browser` (dipakai submit untuk memanggil finish).
 */
final class WajibExamBrowser
{
    private const KUNCI_GATE = 'exam_browser.gate.';

    private const KUNCI_ATTEMPT = 'exam_browser.attempt.';

    public const ALASAN_PERANGKAT_LAIN = 'Ujian ini sudah dikerjakan dari perangkat Exam Browser lain, jadi ujian dikunci. '
        .'Bila Anda memang pindah perangkat, minta pengawas membuka kunci. Jawaban yang sudah tersimpan tidak hilang.';

    /**
     * Route pengerjaan yang boleh fail-open saat Console mati (M2). Review 2026-11-21 #6: halaman kerjakan
     * & status ikut, supaya muat ulang halaman saat Console putus tidak menghentikan ujian.
     */
    private const ROUTE_FAIL_OPEN = [
        'ujian.siswa.kerjakan', 'ujian.siswa.status',
        'ujian.siswa.jawab', 'ujian.siswa.sinkronBatch', 'ujian.siswa.submit',
    ];

    /** Atribut request: SessionInfo dari bukti offline yang sah (dipakai submit untuk finish token). */
    public const ATRIBUT_OFFLINE = 'exam_browser_offline';

    public function __construct(private readonly Verifier $verifier) {}

    public function handle(Request $request, Closure $next): Response
    {
        $ujian = $request->route('ujian');

        $attempt = $request->route('attempt');
        if ($ujian instanceof Ujian && $attempt instanceof UjianAttempt) {
            abort_unless($attempt->ujianKelas?->id_ujian === $ujian->uuid, 404);
        }

        if (! $ujian instanceof Ujian || ! $ujian->wajibExamBrowser()) {
            return $next($request);
        }

        $result = $this->verifier->verify(
            $request->header('X-EB-Session'),
            $request->header('X-EB-Ts'),
            $request->header('X-EB-Request-Hash'),
            EnsureExamBrowser::canonicalUrl($request),
            (int) $ujian->exam_browser_level,
            $ujian->exam_browser_session_id,
            now()->timestamp, // jam yang sama dengan jalur bukti offline (dan bisa di-travel di tes)
        );

        $request->attributes->set('exam_browser', $result);
        $attempt = $request->route('attempt');

        if ($result->ok() && $attempt instanceof UjianAttempt && ! $this->sesiCocok($request, $attempt, $result)) {
            // Audit M1: header sah, tetapi dari sesi Exam Browser lain dari yang pertama mengerjakan attempt ini.
            Log::warning('Ujian: attempt dibuka dari sesi Exam Browser lain', [
                'mode' => config('exam-browser.mode'),
                'ujian' => $ujian->uuid,
                'attempt' => $attempt->uuid,
                'user' => $request->user()?->getKey(),
                'route' => $request->route()?->getName(),
            ]);

            if (config('exam-browser.mode') === 'enforce') {
                return $this->tolakSesiLain($request, $ujian, $attempt);
            }
        }

        if ($result->ok()) {
            $this->tandaiLolos($request, $ujian, $attempt);

            return $next($request);
        }

        if ($result->code !== 'eb_console_unavailable' && $attempt instanceof UjianAttempt && $request->hasSession()) {
            $request->session()->forget(self::KUNCI_ATTEMPT.$attempt->uuid.'.proof');
        }

        // Audit 2026-11-17 M2: Console tak terjangkau tidak boleh menghentikan penyimpanan jawaban
        // untuk attempt yang SUDAH lolos verifikasi di sesi login ini (fail-open terbatas, tercatat).
        if ($result->code === 'eb_console_unavailable'
            && $request->routeIs(...self::ROUTE_FAIL_OPEN)
            && $attempt instanceof UjianAttempt
            && $request->hasSession()
            && ($offline = $this->buktiOfflineSah($request, $ujian, $attempt)) !== null) {
            $request->attributes->set(self::ATRIBUT_OFFLINE, $offline);
            Log::warning('Ujian: Console tidak terjangkau, penyimpanan diteruskan (fail-open terbatas)', [
                'ujian' => $ujian->uuid,
                'attempt' => $attempt->uuid,
                'route' => $request->route()?->getName(),
            ]);

            return $next($request);
        }

        Log::warning('Ujian: verifikasi Exam Browser gagal', [
            'code' => $result->code,
            'mode' => config('exam-browser.mode'),
            'ujian' => $ujian->uuid,
            'user' => $request->user()?->getKey(),
            'route' => $request->route()?->getName(),
        ]);

        if (config('exam-browser.mode') !== 'enforce') {
            return $next($request);
        }

        return $this->tolak($request, $result);
    }

    /**
     * Ikat attempt ke sesi klien pertama yang lolos (atomik: dua request bersamaan tidak bisa sama-sama
     * menang). Hanya attempt milik user yang login & masih berjalan — attempt orang lain ditolak
     * controller (pastikanMilikSiswa) dan tidak boleh terikat/terkunci oleh request ini.
     */
    private function sesiCocok(Request $request, UjianAttempt $attempt, VerificationResult $result): bool
    {
        $sesi = $result->session?->sessionId;

        if ($sesi === null
            || $attempt->id_siswa !== $request->user()?->uuid
            || $attempt->status !== UjianAttempt::STATUS_IN_PROGRESS) {
            return true;
        }

        if ($attempt->eb_client_session_id === null) {
            UjianAttempt::whereKey($attempt->getKey())->whereNull('eb_client_session_id')->update(['eb_client_session_id' => $sesi]);
            $attempt->eb_client_session_id = UjianAttempt::whereKey($attempt->getKey())->value('eb_client_session_id');
        }

        return hash_equals((string) $attempt->eb_client_session_id, $sesi);
    }

    private function tolakSesiLain(Request $request, Ujian $ujian, UjianAttempt $attempt): Response
    {
        DB::transaction(function () use ($attempt) {
            $baris = UjianAttempt::whereKey($attempt->getKey())->lockForUpdate()->first();
            if ($baris && ! $baris->isLocked()) {
                $baris->forceFill(['dikunci' => true])->save();
                UjianPelanggaran::create(['id_attempt' => $baris->uuid, 'id_siswa' => $baris->id_siswa, 'tipe' => 'eb_perangkat_lain']);
            }
        });

        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'code' => 'eb_wrong_session', 'dikunci' => true, 'message' => self::ALASAN_PERANGKAT_LAIN], 403);
        }

        return response()->view('ujian.siswa.terkunci', [
            'ujian' => $ujian,
            'attempt' => $attempt->fresh(),
            'alasan' => self::ALASAN_PERANGKAT_LAIN,
        ], 403);
    }

    /**
     * Penanda di sesi login: gate lolos (syarat POST mulai, audit L2) dan attempt lolos (fail-open M2).
     */
    private function tandaiLolos(Request $request, Ujian $ujian, mixed $attempt): void
    {
        if (! $request->hasSession()) {
            return;
        }

        if ($request->routeIs('ujian.siswa.gate')) {
            $request->session()->put(self::KUNCI_GATE.$ujian->uuid, now()->timestamp);
        }

        $hasil = $request->attributes->get('exam_browser');
        if ($attempt instanceof UjianAttempt && $attempt->id_siswa === $request->user()?->uuid
            && $hasil instanceof VerificationResult && $hasil->ok() && $hasil->session !== null) {
            $request->session()->put(self::KUNCI_ATTEMPT.$attempt->uuid.'.proof', Crypt::encryptString(json_encode([
                'verified_at' => now()->timestamp, 'info' => $hasil->session->toArray(),
            ], JSON_THROW_ON_ERROR)));
        }

        if ($attempt instanceof UjianAttempt && ! $request->session()->has(self::KUNCI_ATTEMPT.$attempt->uuid)) {
            $request->session()->put(self::KUNCI_ATTEMPT.$attempt->uuid, now()->timestamp);
        }
    }

    /**
     * Dipakai UjianSiswaController::start(): POST mulai tak bisa diberi header Exam Browser, jadi saat
     * enforce disyaratkan halaman gate ujian ini lolos verifikasi dalam 15 menit terakhir (audit L2).
     */
    public static function gateBaruLolos(Request $request, Ujian $ujian): bool
    {
        $t = $request->hasSession() ? $request->session()->get(self::KUNCI_GATE.$ujian->uuid) : null;

        return is_int($t) && $t >= now()->subMinutes(15)->timestamp;
    }

    /**
     * Outage fallback still authenticates the URL, SID, attempt, exam, level and a bounded proof.
     * Jendela waktu dari config exam-browser.offline_grace_seconds (default 30 menit), dibatasi expires_at sesi.
     */
    private function buktiOfflineSah(Request $request, Ujian $ujian, UjianAttempt $attempt): ?SessionInfo
    {
        if ($attempt->id_siswa !== $request->user()?->uuid || $attempt->eb_client_session_id === null) {
            return null;
        }
        try {
            $encrypted = $request->session()->get(self::KUNCI_ATTEMPT.$attempt->uuid.'.proof');
            if (! is_string($encrypted)) { return null; }
            $proof = json_decode(Crypt::decryptString($encrypted), true, flags: JSON_THROW_ON_ERROR);
            $info = SessionInfo::fromArray($proof['info'] ?? []);
            $now = now()->timestamp;
            $jendela = max(60, (int) config('exam-browser.offline_grace_seconds', 1800));
            if ($info === null || ! is_int($proof['verified_at'] ?? null)
                || $proof['verified_at'] < $now - $jendela || $proof['verified_at'] > $now
                || $info->expiresAt <= $now || $info->status !== 'active'
                || $info->effectiveLevel < (int) $ujian->exam_browser_level
                || ! hash_equals($attempt->eb_client_session_id, $info->sessionId)
                || ! hash_equals($info->sessionId, (string) $request->header('X-EB-Session'))
                || ($ujian->exam_browser_session_id !== null && ! hash_equals($ujian->exam_browser_session_id, $info->examSessionId))) {
                return null;
            }
            $sah = RequestHash::verify($info->sessionKey(), $request->header('X-EB-Session'),
                $request->header('X-EB-Ts'), $request->header('X-EB-Request-Hash'),
                EnsureExamBrowser::canonicalUrl($request), $now) === 'ok';

            return $sah ? $info : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Halaman dibuka dari Exam Browser yang sah (online, atau bukti offline yang sah). */
    public static function perangkatSah(Request $request): bool
    {
        $hasil = $request->attributes->get('exam_browser');

        return ($hasil instanceof VerificationResult && $hasil->ok())
            || $request->attributes->get(self::ATRIBUT_OFFLINE) instanceof SessionInfo;
    }
    private function tolak(Request $request, VerificationResult $result): Response
    {
        $status = $result->code === 'eb_console_unavailable' ? 503 : 403;

        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'code' => $result->code, 'message' => $result->message()], $status);
        }

        return response()->view('exam-browser::buka-dengan-exam-browser', [
            'result' => $result,
            'downloadUrl' => config('exam-browser.download_url'),
        ], $status);
    }
}
