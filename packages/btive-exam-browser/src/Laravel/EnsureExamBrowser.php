<?php

namespace Btive\ExamBrowser\Laravel;

use Btive\ExamBrowser\CanonicalUrl;
use Btive\ExamBrowser\VerificationResult;
use Btive\ExamBrowser\Verifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware `eb:L2` atau `eb:L2,jadwal` — mewajibkan Exam Browser dengan level minimum.
 *
 *  - Argumen 1: level minimum (`L0`–`L3` atau `0`–`3`).
 *  - Argumen 2 (opsional): nama parameter route berisi exam_session_id yang harus cocok dengan sesi klien.
 *
 * Hasil verifikasi disimpan di `$request->attributes->get('exam_browser')` (VerificationResult).
 * Mode `report` (default) hanya mencatat; mode `enforce` menolak.
 */
final class EnsureExamBrowser
{
    public function __construct(private readonly Verifier $verifier) {}

    public function handle(Request $request, Closure $next, string $level = 'L1', ?string $examParam = null): Response
    {
        $required = (int) ltrim(strtoupper($level), 'L');
        $expected = $examParam !== null ? $request->route($examParam) : null;

        $result = $this->verifier->verify(
            $request->header('X-EB-Session'),
            $request->header('X-EB-Ts'),
            $request->header('X-EB-Request-Hash'),
            self::canonicalUrl($request),
            max(0, min(3, $required)),
            is_string($expected) ? $expected : null,
        );

        $request->attributes->set('exam_browser', $result);

        if ($result->ok()) {
            return $next($request);
        }

        Log::warning('Verifikasi Exam Browser gagal', [
            'code' => $result->code,
            'mode' => config('exam-browser.mode'),
            'path' => $request->path(),
            'client_session' => $result->session?->sessionId,
        ]);

        if (config('exam-browser.mode') !== 'enforce') {
            return $next($request);
        }

        return self::tolak($request, $result);
    }

    /**
     * Skema/host/port mengikuti TrustProxies aplikasi; request-target diambil mentah (REQUEST_URI).
     */
    public static function canonicalUrl(Request $request): string
    {
        $port = $request->getPort();

        return CanonicalUrl::fromParts($request->getScheme(), $request->getHost(), is_numeric($port) ? (int) $port : null, $request->getRequestUri());
    }

    private static function tolak(Request $request, VerificationResult $result): Response
    {
        $status = $result->code === 'eb_console_unavailable' ? 503 : 403;

        if ($request->expectsJson()) {
            return response()->json(['error' => ['code' => $result->code, 'message' => $result->message()]], $status);
        }

        return response()->view('exam-browser::buka-dengan-exam-browser', [
            'result' => $result,
            'downloadUrl' => config('exam-browser.download_url'),
        ], $status);
    }
}
