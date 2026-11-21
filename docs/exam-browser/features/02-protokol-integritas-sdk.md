# 02 Protokol Integritas & SDK

## Outcome

Setiap klien Exam Browser membuktikan dirinya ke server lewat handshake + header HMAC per request; SIMS dan LMS pihak ketiga dapat memverifikasi bukti itu dengan SDK siap pakai, dan integrity level ditentukan server.

## Scope

### Included

- `protocol/SPEC.md`, `protocol/THREAT-MODEL.md`, `protocol/test-vectors/` (sumber kebenaran lintas platform)
- Handshake sesi, resume, end (API-03, 04, 06)
- Verifikasi attestation: Play Integrity (Android), App Attest (iOS), device key terdaftar (macOS/Windows/L3)
- Ingest telemetri batch idempoten + pengiriman perintah remote di response (API-05)
- API integrasi: API key, introspeksi sesi, event dari target, finish (API-07, 08, 09)
- SDK PHP/Laravel dan Node (Express/Fastify middleware)
- Integrasi modul Ujian SIMS

### Excluded

- UI dashboard pengawas → fitur 07
- Implementasi lockdown per platform → fitur 04–06

## Linked artifacts

- Requirements: REQ-003–006, REQ-008, REQ-009, REQ-012, REQ-014; NFR-005, NFR-006, NFR-009
- Flows/screens: FLOW-01, FLOW-03, FLOW-05
- Entities/APIs: ENT-05–09; API-03–09

## User story / scenario

- Sebagai integrator LMS, saya ingin memasang satu middleware, agar halaman ujian hanya bisa dibuka dari Exam Browser dengan level minimum tertentu.
- Sebagai admin SIMS, saya ingin mencentang "Wajib Exam Browser L2" pada jadwal ujian, agar siswa yang memakai Chrome biasa diarahkan untuk membuka aplikasi.
- Sebagai klien yang crash, saya ingin melanjutkan sesi yang sama, agar siswa tidak kehilangan waktu.

## Protokol ringkas

Sumber kebenaran: `protocol/SPEC.md` v1 + `protocol/test-vectors/` di monorepo `btive-exam-browser`. Ringkasan:

1. **Config**: JCS-subset (§3) → Ed25519 dengan label `BTEB-CONFIG-v1` (§5); fail-closed terhadap field tak dikenal; urutan validasi klien §5.4.
2. **Challenge → handshake** (§8): `POST /sessions/challenge` (sekali pakai, 300 detik) → `att_nonce` = SHA-256(`BTEB-ATT-v1` ‖ challenge ‖ config_hash) → `POST /sessions` dengan attestation; console menghitung level (§9.1) dan mengembalikan `session_key`, `unlock_seed`, `server_time`.
3. **Request ke target** (§11): `X-EB-Request-Hash` = HMAC-SHA256(session_key, `BTEB-REQ-v1\n` ts `\n` URL kanonis WHATWG tanpa fragment); JS API `ExamBrowser.sign/finish/info`.
4. **Request ke console** (§10): `X-EB-Signature` mengikat method, URL, ts, dan SHA-256 body.
5. **Keluar** (§12): finish token HMAC, quit URL sebagai hash, TOTP SHA-256/60 detik/6 digit, password darurat Argon2id parameter tetap.
6. **Telemetri** (§13): batch idempoten per `uuid`, `seq` monoton, registry event + efek level (§9.3).

## Acceptance criteria

- Given test vectors di `protocol/test-vectors/`, when dijalankan di console, SDK PHP, SDK Node, then semua hasil identik (byte-for-byte).
- Given request dengan `X-EB-Ts` lebih dari 120 detik dari waktu server, when SDK memverifikasi, then ditolak 403 `eb_stale`.
- Given header valid tetapi `effective_level` L1 dan route mewajibkan L2, when SDK memverifikasi, then 403 `eb_level_insufficient` dengan halaman penjelasan.
- Given batch telemetri yang sama dikirim dua kali, when diproses, then tidak ada event ganda (unique `event_uuid`).
- Given API key lembaga A, when mengintrospeksi sesi lembaga B, then 404.
- Given SDK mode `report-only`, when verifikasi gagal, then request diteruskan dan kegagalan dicatat.
- Given attestation Play Integrity tidak lolos device integrity, when handshake, then sesi tetap dibuat dengan level L1 (bukan ditolak), dan profil ber-min L2 menolak di tahap handshake dengan alasan jelas.

## Edge cases and states

- Jam perangkat siswa salah: klien memakai offset waktu dari header `Date` response server untuk `X-EB-Ts`.
- Redirect 302 ke domain lain dalam whitelist: klien menandatangani ulang URL tujuan.
- Halaman ujian di iframe lintas origin: hanya main-frame yang ditandatangani; dokumentasikan bahwa target harus memverifikasi di dokumen utama.
- Play Integrity quota habis / Google down: fallback L1 + event `attestation_unavailable`.
- Resume setelah sesi ditutup pengawas → ditolak, klien menampilkan SCR-K04.

## Tasks

### TASK-001 - SPEC, threat model, test vectors ✅ (selesai 2026-11-17, SPEC v1 approved)

- Objective: tulis `protocol/SPEC.md` (format config, JCS, Ed25519, handshake, header, HMAC, TOTP, error codes, versioning), `THREAT-MODEL.md` (STRIDE), dan `test-vectors/*.json` (config-signature, request-hash, totp, level-calculation).
- Scope/files or area: `protocol/` (monorepo `btive-exam-browser`)
- Dependency: —
- Acceptance: semua aturan protokol di file ini terdokumentasi; minimal 10 vector per kategori termasuk negatif.
- Verification: review FL + generator vector (script) deterministik.
- Risk/approval gate: **GATE** auth/crypto (desain) — approval FL atas SPEC sebelum implementasi klien.

### TASK-002 - Skema sesi klien & perangkat ✅ (selesai 2026-11-17, GATE disetujui FL)

- Objective: migration `client_sessions`, `devices`, `client_events`, `remote_commands`, `integrations` (ENT-05–09) + model, encrypted cast untuk `session_key`/`unlock_seed`, index untuk dashboard.
- Scope/files or area: `console/database/migrations`, models, policies.
- Dependency: fitur 01 TASK-003
- Acceptance: tenant scope aktif di semua model; `client_events.uuid` unique.
- Verification: Pest.
- Risk/approval gate: **GATE** migration/schema + tenant boundary.

### TASK-003 - Handshake, resume, end + attestation ✅ (selesai 2026-11-17, GATE disetujui FL — verifikasi `app_attest` dipindah ke fitur 06 TASK-005)

- Objective: API-03/04/06; `AttestationVerifier` dengan driver `play_integrity` (decode token via Google Play Integrity API), `app_attest` (verifikasi CBOR attestation + assertion), `device_key` (signature nonce dengan public key terdaftar); `LevelCalculator`.
- Scope/files or area: `app/Http/Controllers/Api/SessionController.php`, `app/Attestation/*`, config `services.play_integrity`.
- Dependency: TASK-001, TASK-002
- Acceptance: AC handshake di atas; level dihitung server sesuai tabel PRD §9.
- Verification: Pest dengan fixture token; uji end-to-end dengan perangkat nyata di fitur 04/06.
- Risk/approval gate: **GATE** auth + external integration (Google, Apple).

### TASK-004 - Ingest telemetri & antrean perintah ✅ (selesai 2026-11-17 — broadcast Reverb dipindah ke fitur 07 TASK-001; uji beban k6 belum)

- Objective: API-05 batch (maks 200 event), idempoten, validasi tipe event, update `last_heartbeat_at`, kembalikan perintah remote tertunda; broadcast ke channel Reverb per exam_session.
- Scope/files or area: `EventIngestController`, job antrean, event broadcast.
- Dependency: TASK-002
- Acceptance: duplikat diabaikan; 500 event/detik per instance (load test k6).
- Verification: Pest + k6.
- Risk/approval gate: —

### TASK-005 - API integrasi ✅ (selesai 2026-11-17, GATE auth disetujui FL)

- Objective: kelola integrasi (SCR-C06: buat key tampil sekali, prefix, hash SHA-256, scopes, IP allowlist, revoke), API-07/08/09, rate limit per key.
- Scope/files or area: `IntegrationController`, middleware `AuthenticateIntegration`, Livewire UI.
- Dependency: TASK-002
- Acceptance: AC lintas lembaga 404; key dicabut langsung 401.
- Verification: Pest.
- Risk/approval gate: **GATE** auth.

### TASK-006 - SDK PHP & Node ✅ (selesai 2026-11-17 — test Node memakai node:test, bukan Vitest)

- Objective: `sdk/php` (package Composer: middleware Laravel `eb:L2`, verifier framework-agnostic, mode `report-only`/`enforce`, halaman "Buka dengan Exam Browser") dan `sdk/node` (middleware Express/Fastify + helper browser `ebFetch`).
- Scope/files or area: `sdk/php`, `sdk/node`.
- Dependency: TASK-001, TASK-005
- Acceptance: lulus test vectors; contoh app minimal di `sdk/*/examples`.
- Verification: PHPUnit + Vitest di CI.
- Risk/approval gate: —

### TASK-007 - Integrasi modul Ujian SIMS ✅ (selesai 2026-11-17, GATE disetujui FL — mode default `report`)

- Objective: di repo SIMS — setting per jadwal/paket ujian `wajib_exam_browser` + `min_level` (L0–L3), pasang SDK PHP pada route ujian siswa (mode `report-only` dulu), halaman FLOW-05 dengan deep link + tautan unduh, panggil API-09 setelah jawaban final tersimpan, simpan kredensial integrasi di `.env`.
- Scope/files or area: SIMS `app/Http/Controllers/Ujian*`, migration kolom jadwal ujian, `config/exam_browser.php`, view ujian siswa; selaraskan dengan `app/Support/ModeUjian.php` dan pensiunkan bertahap `app/Survei/SafeExamBrowser.php` untuk ujian (survei tetap).
- Dependency: TASK-006
- Acceptance: jadwal tanpa centang berperilaku sama seperti sekarang (test regresi Ujian hijau); jadwal L1 menolak Chrome biasa saat `enforce`.
- Verification: `php artisan test --filter=Ujian` + uji manual.
- Risk/approval gate: **GATE** migration + auth + alur nilai SIMS (wajib approval FL sesuai `AGENTS.md`).

### TASK-008 - Alur selesai & keluar aman

- Objective: definisi `quit_policy` (`target_finish`, `quit_url`, `proctor_only`), token finish bertanda tangan untuk `ExamBrowser.finish(token)`, quit password darurat (hash Argon2id di config), event `ended`/`unlocked_by_proctor`/`emergency_quit`.
- Scope/files or area: SPEC, console API-09, SDK helper finish.
- Dependency: TASK-003, TASK-005
- Acceptance: klien tidak dapat keluar tanpa salah satu jalur sah (diuji di fitur 04–06).
- Verification: Pest + test vectors finish token.
- Risk/approval gate: **GATE** auth.

> Catatan implementasi 2026-11-17: event `ended`/`unlocked_by_proctor`/`emergency_quit` di objective ini direalisasikan SPEC v1 sebagai satu event `session_ended` dengan `data.reason` (`target_finish`, `quit_url`, `proctor_code`, `emergency_password`, `kicked`, `time_over`) + API-06. Console mencocokkan `reason` dengan `quit_policy` snapshot & keadaan server dan mencatat `sesuai_kebijakan` di activity log (`sesi_klien`). Generator kode unlock: `console/app/Crypto/UnlockCode.php` (UI pengawas = fitur 07 TASK-003).

## Definition of done

- Acceptance criteria pass; test vectors lulus di console + kedua SDK.
- SPEC & threat model disetujui FL dan dibekukan sebagai `v1`.
- Integrasi SIMS berjalan `report-only` di staging minimal 1 minggu sebelum `enforce`.
