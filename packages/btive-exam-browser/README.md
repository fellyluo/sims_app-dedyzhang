# btive/exam-browser — SDK PHP / Laravel

Verifikasi bahwa request ke aplikasi ujian (SIMS, LMS, CBT) datang dari **B'tive Exam Browser** dengan level keamanan minimum (protocol/SPEC.md §11).

## Pasang (Laravel)

```bash
composer require btive/exam-browser
```

`.env` aplikasi ujian:

```dotenv
EXAM_BROWSER_CONSOLE_URL=https://console.btive.id
EXAM_BROWSER_API_KEY=ebk_xxxxxxxx_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx   # Console → Integrasi
EXAM_BROWSER_MODE=report   # report (catat saja) → enforce (tolak) setelah masa uji
```

## Pakai

```php
// Wajib Exam Browser level 2; {jadwal} harus = exam_session_id sesi klien.
Route::get('/ujian/{jadwal}/soal', SoalController::class)->middleware('eb:L2,jadwal');

// Di controller:
$hasil = $request->attributes->get('exam_browser');   // Btive\ExamBrowser\VerificationResult
$hasil->session?->pesertaRef;                          // identitas peserta dari handshake

// Setelah jawaban final tersimpan:
app(\Btive\ExamBrowser\Laravel\ExamBrowserClient::class)->finish($hasil->session->sessionId);
// atau di halaman: ExamBrowser.finish("{{ $client->finishToken($sessionId) }}")
```

- Browser biasa / level kurang → halaman "Buka dengan B'tive Exam Browser" (403) atau JSON `{error: {code, message}}`.
- Console tidak terjangkau → 503 `eb_console_unavailable` (mode `enforce`).
- `EXAM_BROWSER_CONSOLE_URL` wajib `https://` (http hanya localhost) — selain itu SDK tidak mengirim API key dan verifikasi menjadi `eb_console_unavailable`.
- Hasil introspeksi di-cache (default 30 detik, maks 300) **terenkripsi** (APP_KEY); bila hash gagal dengan data cache (kunci dirotasi setelah resume), SDK mengambil ulang sekali.
- URL kanonis = skema + host + port non-default + request-target **mentah** (`REQUEST_URI`). Di balik reverse proxy, atur TrustProxies supaya skema/host benar.

## Tanpa Laravel

`Btive\ExamBrowser\Verifier` + implementasi `Introspector` sendiri; `RequestHash`, `FinishToken`, `CanonicalUrl` murni PHP.

## Test

```bash
composer install && composer test
```

Termasuk `protocol/test-vectors/request-hash.json` dan `finish-token.json`.
