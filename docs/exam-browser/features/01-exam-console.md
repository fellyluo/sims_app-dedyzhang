# 01 Exam Console

## Outcome

Guru dan admin lembaga dapat membuat profil ujian sekali, membuka sesi ujian dengan kode join/QR, dan klien di semua platform mengambil config yang ditandatangani dan tidak bisa dipalsukan.

## Scope

### Included

- Repo monorepo `btive-exam-browser/` + aplikasi `console/` (Laravel 12, Blade/Livewire/Alpine)
- Tenancy `lembaga_id` + global scope, role & permission Spatie, Policy
- CRUD profil ujian dan sesi ujian (snapshot profil immutable per sesi)
- Penandatanganan config Ed25519 + rotasi kunci (`kid`)
- Kode join 6 karakter + QR + endpoint publik `GET /api/v1/join/{code}`
- Activity log untuk perubahan profil, sesi, dan kunci

### Excluded

- Handshake sesi, telemetri, integrasi API → fitur 02
- Dashboard pengawas live → fitur 07
- Rilis aplikasi → fitur 08

## Linked artifacts

- Requirements: REQ-001, REQ-002, REQ-003; NFR-001–006
- Flows/screens: FLOW-01; SCR-C01, SCR-C02, SCR-C03
- Entities/APIs: ENT-01–04, ENT-10; API-01, API-02

## User story / scenario

- Sebagai guru, saya ingin membuat profil ujian dengan start URL dan aturan URL, agar siswa hanya bisa membuka halaman ujian.
- Sebagai guru, saya ingin menampilkan QR besar di proyektor, agar seluruh kelas bisa join dalam satu menit.
- Sebagai admin lembaga, saya ingin menetapkan level integritas minimum default, agar guru tidak memilih level yang terlalu lemah.
- Sebagai super admin, saya ingin merotasi kunci penandatangan tanpa memutus sesi yang sedang berjalan.

## Acceptance criteria

- Given guru lembaga A, when membuka daftar profil, then hanya profil lembaga A yang tampil (tanpa `where lembaga_id` manual di controller).
- Given profil dengan start URL di luar whitelist, when disimpan, then validasi menolak dengan pesan Bahasa Indonesia.
- Given sesi ujian dibuka, when profil induk diedit, then `profile_snapshot` sesi tidak berubah.
- Given kode join valid dan sesi dalam jadwal, when `GET /api/v1/join/{code}`, then response berisi config kanonis JCS + `signature` + `kid` yang lolos verifikasi test vector.
- Given kode salah > 30 kali/menit dari satu IP (default `EB_JOIN_MAX_FAILURES`; diubah dari 10 karena NAT sekolah, dikonfirmasi FL 2026-11-17), when request berikutnya, then 429.
- Given sesi di luar jadwal atau ditutup, when join, then 410 dengan alasan.
- Given kunci aktif dirotasi, when klien lama memverifikasi config bertanda `kid` lama yang masih `retiring`, then tetap valid hingga dicabut.

## Edge cases and states

- Kode join bentrok (generate ulang dalam transaksi, unique index per sesi aktif).
- Zona waktu: jadwal disimpan UTC, ditampilkan WIB/WITA/WIT sesuai setting lembaga.
- Profil dipakai sesi aktif tidak boleh dihapus (soft delete + blokir).
- Aturan URL tidak valid (pattern `prefix`/`glob` bukan URL kanonis, wildcard host selain `*.` di depan, pola terlalu lebar seperti `https://*`) → validasi + peringatan. Regex tidak didukung di v1 (SPEC §6).
- Empty state daftar profil dengan CTA "Buat profil ujian pertama".

## Tasks

### TASK-001 - Scaffold monorepo & console ✅ (selesai 2026-11-17)

- Objective: repo `btive-exam-browser/` dengan `console/` Laravel 12, `protocol/`, folder klien kosong, CI (Pint, Larastan, Pest).
- Scope/files or area: root repo, `console/`, `.github/workflows/console.yml`.
- Dependency: —
- Acceptance: `php artisan test` hijau di CI; README menjelaskan struktur.
- Verification: CI run hijau.
- Risk/approval gate: — (repo baru; tidak menyentuh SIMS)

### TASK-002 - Tenancy, user, role ✅ (selesai 2026-11-17, GATE disetujui FL)

- Objective: tabel `lembaga`, `users` (UUID), trait `BelongsToLembaga` (global scope + auto-fill), Spatie roles: `super_admin`, `admin_lembaga`, `guru`, `pengawas`.
- Scope/files or area: migrations, `app/Models`, `app/Concerns/BelongsToLembaga.php`, seeder role/permission.
- Dependency: TASK-001
- Acceptance: test IDOR — user lembaga A tidak dapat membaca/mengubah record lembaga B lewat route mana pun.
- Verification: Pest feature test lintas tenant.
- Risk/approval gate: **GATE** migration/schema + tenant boundary + auth.

### TASK-003 - Skema profil & sesi ujian ✅ (selesai 2026-11-17, GATE disetujui FL)

- Objective: migration `exam_profiles`, `exam_sessions` sesuai ENT-03/04; model, Policy, factory.
- Scope/files or area: migrations, `ExamProfile`, `ExamSession`, `ExamProfilePolicy`, `ExamSessionPolicy`.
- Dependency: TASK-002
- Acceptance: snapshot profil tersalin saat sesi dibuat dalam `DB::transaction()`; Policy menolak lintas lembaga.
- Verification: Pest unit + feature.
- Risk/approval gate: **GATE** migration/schema.

### TASK-004 - UI profil ujian ✅ (selesai 2026-11-17, termasuk login/logout)

- Objective: SCR-C01 & SCR-C02 (tab Umum, Akses URL, Keamanan, Keluar, Lanjutan) + preview JSON config.
- Scope/files or area: Livewire component `ProfilUjianForm`, views Blade, validasi URL rules.
- Dependency: TASK-003
- Acceptance: semua field REQ-001 dapat disimpan; aturan URL divalidasi dengan `url-rules.json`; teks Bahasa Indonesia.
- Verification: Pest Livewire test + review visual di browser.
- Risk/approval gate: —

### TASK-005 - Penandatanganan config Ed25519 ✅ (selesai 2026-11-17, GATE disetujui FL)

- Objective: service `ConfigSigner` (kanonisasi JCS RFC 8785, Ed25519 via sodium), tabel `signing_keys` (public only), kunci privat dari env/secret manager, command `eb:keys:rotate`, `eb:keys:revoke`, endpoint `GET /api/v1/keys`.
- Scope/files or area: `app/Crypto/`, migration `signing_keys`, artisan commands, `protocol/test-vectors/config-signature.json`.
- Dependency: TASK-003, fitur 02 TASK-001 (SPEC kanonisasi)
- Acceptance: output cocok dengan test vectors; rotasi tidak mematahkan sesi aktif; kunci privat tidak pernah tercetak di log.
- Verification: Pest + test vectors.
- Risk/approval gate: **GATE** auth/crypto + migration.

### TASK-006 - Kode join, QR, endpoint join ✅ (selesai 2026-11-17)

- Objective: generate kode 6 karakter (alfabet tanpa karakter ambigu), QR (deep link `btive-eb://join/{code}` + fallback https), SCR-C03 mode proyektor, `GET /api/v1/join/{code}` dengan rate limit.
- Scope/files or area: `JoinCodeGenerator`, `JoinController`, route API, view sesi.
- Dependency: TASK-005
- Acceptance: AC join di atas lulus (200/410/429).
- Verification: Pest feature + uji QR dengan kamera HP.
- Risk/approval gate: —

### TASK-007 - Activity log console ✅ (selesai 2026-11-17)

- Objective: spatie/activitylog untuk profil, sesi, signing key (create/update/delete/rotate), dengan `causer` dan `lembaga_id`.
- Scope/files or area: model traits, halaman log sederhana untuk admin lembaga.
- Dependency: TASK-003, TASK-005
- Acceptance: setiap perubahan tercatat; admin lembaga hanya melihat log lembaganya.
- Verification: Pest.
- Risk/approval gate: —

## Definition of done

- Acceptance criteria pass.
- Pest, Pint, Larastan hijau di CI.
- Test vectors kanonisasi & tanda tangan lulus.
- `laravel-security-audit` checklist bagian tenancy & auth dijalankan untuk TASK-002/003.
