# PROGRESS — B'tive Exam Browser

Tracker eksekusi untuk [`PRD.md`](PRD.md). Update setelah setiap task. Task berlabel **GATE** wajib approval FL terpisah sebelum dikerjakan.

## Status

| Fase | Fitur | Status |
|---|---|---|
| 1 | 01 Exam Console | **Selesai** (TASK-001 s.d. TASK-007); sisa DoD: uji MySQL 8 |
| 1 | 02 Protokol Integritas & SDK | **Selesai** (TASK-001 s.d. TASK-008); sisa DoD: masa uji `report` ≥ 1 minggu sebelum `enforce`, L3 audit SIMS |
| 2 | 03 Mode Browser SIMS | **Berjalan** — gerbang layar penuh dan deteksi klien terverifikasi di halaman Ujian SIMS |
| 2 | 04 Klien Android | Berjalan — scaffold, verifikasi join, dan diagnostik Lock Task tersedia di salinan pengembangan. Build dan uji tablet masih tertunda. |
| 3 | 05 Klien Windows | Belum mulai |
| 4 | 06 Klien macOS & iOS | Belum mulai (TASK-006 pengajuan entitlement sebaiknya dimulai lebih awal) |
| 5 | 07 Monitoring Pengawas | **Berjalan** — TASK-001–003 selesai (polling; Reverb ditunda); berikutnya TASK-004 (GATE auth) |
| 5 | 08 Distribusi & Rilis | Belum mulai |

## Log

### 2026-11-17 — Fitur 02 TASK-001: SPEC, threat model, test vectors

- [x] `protocol/SPEC.md` v1 (draft) — 18 bagian: encoding, JCS-subset, kunci Ed25519 + rotasi, config + urutan validasi, aturan URL, URL kanonis, challenge/handshake/resume/end, integrity level, tanda tangan API, header target + JS API, jalur keluar (finish token, quit URL hash, TOTP, Argon2id), telemetri + registry event, API integrasi, kode join, error codes, versioning
- [x] `protocol/THREAT-MODEL.md` — 22 ancaman STRIDE, batas yang diterima, 7 keputusan desain turunan
- [x] `protocol/test-vectors/` — 12 file, 210 kasus (positif + negatif), termasuk self-test terhadap RFC 6238, RFC 8032, RFC 8785
- [x] Generator Node (`--check` deterministik) + verifier PHP independen
- [x] PRD & fitur 01/02 disinkronkan (regex → host/prefix/glob, API-03a challenge, rumus header berlabel domain)
- [x] **GATE: approval FL atas SPEC v1** — disetujui 2026-11-17; SPEC & threat model berstatus *Approved & frozen*

**Verifikasi:**

```text
node tools/generate-vectors.mjs --check   → all vectors match (12 file)
php tools/verify-vectors.php              → 180 passed, 0 failed, 36 skipped (tidak berlaku untuk PHP)
mutation test (1 expected value dirusak)  → kedua verifier gagal sesuai harapan, lalu dipulihkan
```

**Perubahan keputusan vs PRD awal (sudah disinkronkan):**

1. Regex URL ditunda ke v2 — v1 hanya `host`/`prefix`/`glob` (ReDoS + perbedaan engine regex antar platform).
2. Handshake dua langkah (challenge server sekali pakai) — mencegah replay attestation.
3. Device key memakai ECDSA P-256 (bukan Ed25519) — satu-satunya kurva yang didukung Secure Enclave, Android Keystore lama, dan TPM.
4. Request ke console memakai `X-EB-Signature` yang mengikat body; request ke target memakai `X-EB-Request-Hash` (URL + waktu).

**Berikutnya:** lihat log fitur 01 TASK-001 di bawah.

### 2026-11-17 — Fitur 01 TASK-001: Scaffold monorepo & console

Lokasi: `/Users/macbookpro/Downloads/Proyek/btive-exam-browser` (git init, branch `main`, **belum ada commit**).

- [x] Struktur monorepo: `console/`, `protocol/`, `clients/{android,windows,apple}/`, `sdk/{php,node}/`, `.github/workflows/`
- [x] `console/` — Laravel 12.72 (PHP 8.3), Pest 3 + Larastan 3 (level 6) + Pint; script `composer lint|analyse|test|check`; locale `id`
- [x] `protocol/` dipindahkan dari repo SIMS (isi identik, diverifikasi `diff -r`); di SIMS tinggal pointer `docs/exam-browser/protocol/README.md`
- [x] CI: `.github/workflows/console.yml` (Pint, PHPStan, Pest) dan `protocol.yml` (`--check` + verifier PHP) — **belum pernah dijalankan di GitHub** (belum ada remote)
- [x] README root (struktur, konvensi, perintah), README console, placeholder README klien/SDK

**Verifikasi (lokal):**

```text
composer check (console)                  → Pint passed, PHPStan [OK] No errors, Pest 2 passed
node protocol/tools/generate-vectors.mjs --check → all vectors match
php protocol/tools/verify-vectors.php     → 180 passed, 0 failed, 36 skipped
```

**Catatan:**

- Migration bawaan Laravel (`users` ber-ID integer, `cache`, `jobs`) belum diubah — diganti di TASK-002.
- Livewire, Spatie permission/activitylog, Reverb belum dipasang — masuk task yang membutuhkannya.
- PRD/features/PROGRESS masih di repo SIMS; belum diputuskan apakah ikut pindah ke monorepo.

**Berikutnya:** lihat log fitur 01 TASK-002 di bawah.

### 2026-11-17 — Fitur 01 TASK-002: Tenancy, user, role (GATE disetujui FL)

Area: `btive-exam-browser/console/` — **belum ada commit**.

- [x] Migration: `lembaga` (UUID, slug unik, status, zona_waktu); `users` UUID + `lembaga_id` nullable (NULL = akun sistem) + FK restrict; `sessions.user_id` UUID; tabel spatie/permission dengan PK & morph key UUID
- [x] `App\Tenancy`: `TenantContext` (kosong / lembaga / sistem, scoped), `LembagaScope`, `LembagaSelfScope`, trait `BelongsToLembaga` (auto-fill, tolak tulis lintas lembaga, tolak ganti `lembaga_id`), exception `TenantContextMissing` & `CrossTenantWrite`
- [x] Middleware `SetTenantContext` di grup `web`, diprioritaskan setelah autentikasi dan sebelum route model binding; menolak lembaga nonaktif dan user tanpa lembaga yang bukan super admin
- [x] `TenantlessUserProvider` untuk lookup login (sebelum konteks diketahui)
- [x] Spatie permission 8.5: model `Role`/`Permission` UUID; enum `Peran` (4 peran) & `Izin` (3 izin awal); `RolePermissionSeeder` idempoten; `Gate::before` untuk super admin
- [x] `UserPolicy` (termasuk `tetapkanPeran` — admin lembaga tidak bisa memberi `super_admin`) dan `LembagaPolicy`
- [x] Factory `Lembaga`/`User`, `DemoSeeder` (local saja)

**Verifikasi (lokal, SQLite):**

```text
composer check                 → Pint passed, PHPStan level 6 [OK] No errors, Pest 38 passed (98 assertions)
php artisan migrate:fresh --seed → semua migration + seeder DONE
mutation: hapus filter LembagaScope        → test IDOR & scope gagal (terdeteksi), lalu dipulihkan
mutation: hapus priority SetTenantContext  → 4 test IDOR gagal (terdeteksi), lalu dipulihkan
```

Cakupan test: fail-closed tanpa konteks; scope baca/ubah/hapus lewat query builder; auto-fill & mass assignment `lembaga_id`; tulis lintas lembaga; route model binding lintas lembaga → 404 (GET/PUT/DELETE user & lembaga) dengan data tidak berubah; guru 403; super admin lintas lembaga; peran `super_admin` pada akun ber-lembaga tidak memberi akses sistem; lembaga tidak diambil dari header/query.

**Belum diverifikasi / catatan:**

- Migration belum dijalankan di MySQL 8 (target produksi) — hanya SQLite.
- Belum ada halaman login/kelola pengguna; test IDOR memakai route uji yang didaftarkan di test dengan pola `auth` + binding + policy. Ulangi pola test ini untuk setiap controller nyata.
- Job antrean belum membawa `TenantContext` otomatis (belum ada job).
- Audit `laravel-security-audit` untuk TASK-002/003 belum dijalankan (DoD fitur 01) — jalankan setelah TASK-003.

**Berikutnya:** lihat log fitur 01 TASK-003 di bawah.

### 2026-11-17 — Fitur 01 TASK-003: Skema profil & sesi ujian (GATE disetujui FL)

Area: `btive-exam-browser/console/` — **belum ada commit**.

- [x] Migration `exam_profiles` (ENT-03, soft delete, `version`) dan `exam_sessions` (ENT-04 + `nama`, `profile_version`, `config_hash`, `emergency_password` terenkripsi; `join_code` unik nullable)
- [x] Model `ExamProfile` (versi naik saat atribut config berubah; hapus ditolak bila ada sesi belum ditutup) dan `ExamSession` (snapshot selalu dari profil, immutable; `buka()`, `tutup()`, `menerimaPeserta()`)
- [x] Action `CreateExamSession`: validasi jadwal, `lockForUpdate` profil, `DB::transaction()`, waktu UTC
- [x] Enum `ExamSessionStatus`, `Platform`, `IntegrityLevel`; 4 izin baru (`profil_ujian.*`, `sesi_ujian.*`) — guru & admin lembaga; pengawas belum diberi akses
- [x] `ExamProfilePolicy`, `ExamSessionPolicy` (hanya sesi draf yang boleh dihapus; `forceDelete` profil selalu ditolak)
- [x] Factory profil & sesi

**Verifikasi (lokal, SQLite):**

```text
composer check                   → Pint passed, PHPStan level 6 [OK] No errors, Pest 66 passed (203 assertions)
php artisan migrate:fresh --seed → DONE
mutation A: hapus DB::transaction di CreateExamSession → 1 test gagal (terdeteksi)
mutation B: snapshot tidak immutable                   → 3 test gagal (terdeteksi)
mutation C: snapshot boleh dari input pemanggil        → 1 test gagal (terdeteksi)
mutation D: policy profil tanpa cek selembaga          → 1 test gagal (terdeteksi)
(semua mutasi dipulihkan; suite kembali hijau)
```

**Perbedaan dari ENT di PRD (penambahan, tidak ada yang dihapus):** `exam_sessions.nama`, `profile_version`, `config_hash`, `emergency_password`, `created_by`; `exam_profiles.deleted_at`. Password darurat dibuat per sesi, bukan per profil (SPEC §12.4).

**Belum diverifikasi / catatan:**

- Migration belum dijalankan di MySQL 8.
- Isi JSON (`url_rules`, `options`, `quit_policy`, `platforms`) belum divalidasi terhadap SPEC — masuk TASK-004 (form) dan TASK-005 (builder config).
- Akses pengawas ke sesi butuh tabel penugasan (fitur 07) — saat ini pengawas ditolak.
- Audit `laravel-security-audit` untuk TASK-002/003 belum dijalankan (DoD fitur 01).

**Berikutnya:** audit keamanan TASK-002/003 (DoD), lalu fitur 01 TASK-004 — UI profil ujian (bukan GATE; butuh halaman login + layout + Livewire).

### 2026-11-17 — Audit keamanan TASK-002/003 (`laravel-security-audit`)

Laporan: `btive-exam-browser/docs/audit/2026-11-17-fitur-01-task-002-003.md`. **Hanya audit — belum ada patch yang diterapkan.**

- Hasil: 0 CRITICAL, 2 HIGH, 4 MEDIUM, 7 LOW. Tidak ada celah yang bisa dieksploitasi hari ini (belum ada route input); semuanya laten untuk TASK-004+.
- HIGH: H1 profil menerima nilai di luar SPEC; H2 rule `exists`/`unique` tidak ter-scope lembaga.
- MEDIUM: M1 `quit_url` ikut serialisasi sesi; M2 DB tidak menjamin sesi–profil selembaga (**GATE migration**); M3 builder `update()` bisa memindahkan baris antar lembaga; M4 belum ada audit trail (TASK-007).
- Dibuktikan dengan 8 probe test sementara (sudah dihapus); `composer audit` bersih.

**Berikutnya:** lihat log patch audit di bawah.

### 2026-11-17 — Patch audit H1, H2, M1, M3 (tanpa migration; disetujui FL)

- [x] M1 — `ExamSession`: `profile_snapshot` hidden + `snapshotTanpaRahasia()`
- [x] H2 — `TenantRule` (`ExistsInLembaga`, `UniqueInLembaga`, `EmailPenggunaTersedia`) + `ArchTest` yang melarang rule `exists`/`unique` bawaan dan bypass scope di luar daftar
- [x] M3 — `TenantBuilder` untuk semua model ber-tenant: menolak `lembaga_id` di `update`/`increment`/`upsert`, memeriksa & mengisi `lembaga_id` di `insert`, berlaku juga untuk `saveQuietly()`
- [x] H1 — `ExamProfileRules` (tipe ketat, sesuai SPEC) dipanggil hook `saving`; `Support\Protocol\CanonicalUrl` & `UrlRules` diuji terhadap test vectors protokol
- [x] M2 — FK komposit sesi–profil (**GATE migration disetujui FL**): `unique(id, lembaga_id)` di `exam_profiles`, FK `(exam_profile_id, lembaga_id)` di `exam_sessions`; migration yang ada diubah langsung (belum ada data/commit). Verifikasi: Pest 136 passed (366 assertions), insert/update lintas lembaga lewat `DB::table` ditolak DB

**Verifikasi (lokal, SQLite):**

```text
composer check                   → Pint passed, PHPStan level 6 [OK] No errors, Pest 135 passed (360 assertions)
php artisan migrate:fresh --seed → DONE
6 mutasi pada patch              → semuanya terdeteksi test, lalu dipulihkan
```

**Catatan:** `CanonicalUrl` lebih ketat dari SPEC §7 (tanpa userinfo/IDN mentah) karena PHP 8.3 belum punya parser WHATWG. Belum diuji di MySQL 8.

**Berikutnya:** fitur 01 TASK-004 — UI profil ujian (sekaligus L1–L3, L5–L7). Prasyarat yang belum ada di breakdown: halaman login/logout + layout + Livewire (menyentuh auth → perlu keputusan FL). Sisa audit: M4 (TASK-007), L4 (checklist deploy).

### 2026-11-17 — Fitur 01 TASK-004: UI profil ujian + login/logout (disetujui FL)

Area: `btive-exam-browser/console/` — **belum ada commit**.

- [x] Livewire 4.4 + Tailwind 4 (aset di-build `npm run build`); layout `components/layouts/{app,guest}`
- [x] Login `/masuk` (limiter 5 percobaan per email+IP + `throttle:30,1`, regenerasi sesi, pesan generik, tolak lembaga nonaktif/akun tanpa lembaga), logout `/keluar`, beranda `/`
- [x] `UserTenant` (satu aturan "boleh masuk" untuk login & middleware); logout tetap bisa untuk user lembaga nonaktif (audit L3); halaman error 403/404/419/429/500 berbahasa Indonesia
- [x] Action `CreateExamProfile`, `UpdateExamProfile` (lockForUpdate + cek versi → audit L1), `DeleteExamProfile` (lock → audit L2)
- [x] SCR-C01 daftar profil (cari, empty state, hapus) dan SCR-C02 form bertab (Umum, Akses URL, Keamanan, Keluar, Lanjutan, Pratinjau config); `#[Locked]` untuk ID & versi; otorisasi di mount dan setiap action
- [x] `lang/id` (validasi, pagination, auth) + `lang/id.json`; welcome bawaan dihapus (audit L7)
- [x] Entry `exam-console` (port 8791) ditambahkan ke `sims_app-main/.claude/launch.json` untuk preview

**Verifikasi:**

```text
composer check → Pint passed, PHPStan level 6 [OK] No errors, Pest 175 passed (540 assertions)
Browser (localhost:8791, akun demo guru): login salah → pesan error; login benar; buat profil
  (error → pindah ke tab Akses URL → "Izinkan domain URL awal" → tersimpan versi 1);
  ubah level ke L2 → versi 2; pratinjau config; mobile 375px tanpa overflow horizontal; console tanpa error
```

**Belum diverifikasi / sisa:**

- Hapus profil dan logout belum diklik di browser (hanya lewat test); CSRF hanya dicek lewat perilaku bawaan Laravel.
- Belum ada mutation test untuk kode TASK-004.
- Audit L5 (admin terakhir) dan L6 (validasi zona waktu/slug) belum — belum ada UI pengguna/lembaga. L4 → checklist deploy. M4 → TASK-007.
- MySQL 8 belum diuji. Desain belum disamakan dengan design system SIMS (pakai Tailwind polos).
- ~~README console belum memuat bagian TASK-004~~ — ditambahkan bersama TASK-005.

**Berikutnya:** fitur 01 TASK-005 — penandatanganan config Ed25519 (**GATE** auth/crypto + migration `signing_keys`).

### 2026-11-17 — Fitur 01 TASK-005: Penandatanganan config Ed25519 (GATE crypto + migration; disetujui FL)

Area: `btive-exam-browser/console/` — **belum ada commit**.

- [x] Migration `signing_keys` (UUID, `kid` unik, hanya kunci publik, status pending/active/retiring/revoked, `status_changed_at`) + kolom `exam_sessions.config_canonical`, `config_signed_at`
- [x] `Support\Protocol\Jcs` (subset RFC 8785), `Base64Url`; `Crypto\ProtocolSigner` (label domain), `KeyRing` (seed dari `EB_SIGNING_KEYS`, wajib cocok dengan kunci publik DB), `EmergencyPassword` (12 karakter acak, PHC argon2id m=65536,t=3,p=1, format diverifikasi)
- [x] `Crypto\ConfigBuilder` (hanya field SPEC §5.2; validasi ulang snapshot) + action `OpenExamSession` (lock + transaksi; tanda tangan ulang tidak mengganti password darurat)
- [x] Command `eb:keys:generate` (file 0600 / tampil sekali), `eb:keys:rotate`, `eb:keys:revoke` (konfirmasi; kunci aktif ditolak), `eb:keys:list`
- [x] `GET /api/v1/keys` (API-02): dokumen kunci bertanda tangan, tanpa kunci pending, `issued_at` = perubahan status terakhir, cache 5 menit, 503 aman bila kunci tidak siap
- [x] Penambahan non-normatif: status `pending` (console saja) dan error `eb_keys_unavailable` (SPEC §17 mengizinkan error code baru)

**Verifikasi:**

```text
composer check → Pint passed, PHPStan level 6 [OK] No errors, Pest 204 passed (724 assertions)
Test vectors   → JCS (semua kasus valid + negatif yang terdeteksi), signature config & dokumen kunci IDENTIK
                 byte-per-byte dengan config-signature.json, emergency-password.json
Cek silang Node (implementasi referensi protokol) atas envelope buatan console:
                 verifyEnvelope → ok; platform lain → eb_platform_not_allowed; build 0 → eb_app_outdated;
                 config kanonis menurut Node; start_url kanonis WHATWG & diizinkan; argon2 Node cocok;
                 envelope dimanipulasi → eb_signature_invalid
5 mutasi (JCS tanpa urut key, tanpa label domain, tanpa cek seed↔publik, tanpa transaksi,
                 dokumen kunci memuat pending) → semuanya terdeteksi, lalu dipulihkan
```

**Belum / catatan:**

- Belum ada UI membuka sesi; `OpenExamSession` baru dipanggil dari test/tinker (UI sesi menyusul).
- `ExamSession::buka()` hanya mengubah status — endpoint join TASK-006 wajib menolak sesi tanpa `config_canonical`.
- Aksi kunci belum tercatat di activity log (audit M4 → TASK-007).
- `min_app_version` dari env `EB_MIN_BUILD_*` sampai registry rilis (fitur 08 TASK-002).
- MySQL 8 belum diuji. Kunci produksi belum dibuat (butuh secret manager di server).

**Berikutnya:** fitur 01 TASK-006 — kode join, QR, `GET /api/v1/join/{code}` (bukan GATE).

### 2026-11-17 — Fitur 01 TASK-006: Kode join, QR, endpoint join

Area: `btive-exam-browser/console/` — **belum ada commit**.

- [x] `Support\JoinCode` (6 karakter, alfabet SPEC §15, normalisasi input) — ditetapkan saat `OpenExamSession`, unik global dengan coba-ulang dalam savepoint; `CloseExamSession` melepas kode
- [x] `GET /api/v1/join/{code}` (API-01): envelope byte-identik, `Date` + `no-store`; 404/425/410 sesuai SPEC §16; batas kode salah per IP (`EB_JOIN_MAX_FAILURES`, default 30)
- [x] `Tenancy\JoinCodeResolver` — satu-satunya lookup lintas lembaga di jalur publik; hanya sesi `dibuka` yang config-nya sudah ditandatangani
- [x] `/j/{code}` (tujuan QR) + QR SVG (`bacon/bacon-qr-code`, quiet zone 4 modul) berisi `https://<console>/j/{CODE}`
- [x] UI sesi ujian (di luar breakdown awal, dibutuhkan SCR-C03): daftar + filter, buat sesi (zona waktu lembaga → UTC), detail (buka/tutup, kode, QR, password darurat on-demand), mode proyektor; tautan "Buat sesi" dari daftar profil; menu & beranda
- [x] Perbaikan `ArchTest`: pola regex menganggap `TenantRule::exists(` sebagai `Rule::exists(` (false positive) — diperbaiki + test untuk polanya

**Verifikasi:**

```text
composer check → Pint passed, PHPStan level 6 [OK] No errors, Pest 246 passed (1048 assertions)
Mutasi: join tanpa syarat config bertanda tangan, kode salah tidak dihitung, join sebelum jadwal,
        tanpa retry bentrok kode, password darurat tanpa otorisasi, tutup tanpa otorisasi → semua terdeteksi
        (dua mutasi awalnya lolos → ditambah test filter ganda & test user "lihat saja")
Browser (localhost:8791, kunci dev lokal): buat profil → buat sesi → buka → kode 5CG GRY + QR;
        QR didekode BarcodeDetector Chromium = http://localhost:8791/j/5CGGRY; /api/v1/join/5cg-gry → 200
        (16 field config, kid dev-lokal, no-store, Date); kode salah → 404; mode proyektor; mobile 375px
        tanpa overflow; /j/5cg-gry; tutup sesi → join 404
```

**Perbedaan dari AC / catatan:**

- Batas kode salah per IP: **30/menit (dikonfirmasi FL 2026-11-17)**, bukan 10 seperti AC awal — satu kelas biasanya berbagi satu IP (NAT sekolah). Dapat diatur lewat `EB_JOIN_MAX_FAILURES`; AC di fitur 01 sudah diperbarui.
- `.env` lokal console sekarang berisi kunci dev `dev-lokal` (hanya lokal, di-ignore git).
- Melihat password darurat belum tercatat di activity log (TASK-007).
- Belum ada halaman admin lembaga untuk mengatur zona waktu (default Asia/Jakarta).
- Belum diuji di MySQL 8; QR belum dipindai dengan kamera ponsel sungguhan.

**Berikutnya:** fitur 01 TASK-007 — activity log (spatie/activitylog) untuk profil, sesi, kunci, dan tampilan password darurat (menutup audit M4).

### 2026-11-17 — Fitur 01 TASK-007: Activity log console (menutup audit M4)

Area: `btive-exam-browser/console/` — **belum ada commit**.

- [x] `spatie/laravel-activitylog` 4.12 + migration sendiri (UUID, `lembaga_id`, gabungan 3 migration paket)
- [x] `App\Models\Activity`: `LembagaScope` (fail closed; aktivitas sistem hanya untuk super admin), `lembaga_id` dari subjek lalu konteks (tolak subjek lembaga lain), append-only
- [x] Dicatat: profil (buat/ubah/hapus, `quit_url` → `[disembunyikan]`), sesi (dibuat/dibuka/ditandatangani ulang/ditutup; tanpa rahasia), kunci (sistem, `via: cli`, tanpa seed), setiap tampilan kata sandi darurat
- [x] `OpenExamSession` kini satu UPDATE (status + kode join) → satu catatan "Sesi ujian dibuka"
- [x] Izin `aktivitas.lihat` (admin lembaga) + `ActivityPolicy` + halaman `/aktivitas` (filter jenis ber-whitelist, ringkasan perubahan, sorot tampilan password darurat) + menu

**Verifikasi:**

```text
composer check → Pint passed, PHPStan level 6 [OK] No errors, Pest 260 passed (1109 assertions)
6 mutasi (tanpa scope, bisa diubah, quit_url tidak disembunyikan, tampilan password tidak dicatat,
          sesi logAll(), tapActivity menimpa catatan manual) → semuanya terdeteksi
Browser (admin lembaga demo): /aktivitas tampil, quit_url tidak muncul ("[disembunyikan]"), menu sesuai izin
```

**Bug yang ditemukan saat cek browser:** catatan "kata sandi darurat ditampilkan" tertimpa deskripsi "Sesi ujian diubah" oleh `tapActivity` model sesi (test hanya memeriksa `event`). Diperbaiki: `tapActivity` hanya untuk event model; test kini memeriksa deskripsi. Satu entri lokal yang salah tetap ada (log append-only, data dev).

**Belum / catatan:**

- Perubahan peran/pengguna dan login belum dicatat (belum ada UI kelola pengguna).
- Retensi 365 hari = default sementara (OQ3).
- Migration belum diuji di MySQL 8 — satu-satunya sisa DoD fitur 01.

**Fitur 01 selesai.** Berikutnya sesuai roadmap Fase 1: fitur 02 TASK-002 (skema `client_sessions`, `devices`, `client_events`, `remote_commands`, `integrations` — **GATE** migration/tenant).

### 2026-11-17 — Fitur 02 TASK-002: Skema sesi klien & perangkat (GATE migration/tenant disetujui FL)

Area: `btive-exam-browser/console/` — **belum ada commit**.

- [x] Migration `devices`, `client_sessions`, `client_events`, `remote_commands`, `integrations` (ENT-05..09) — semua UUID + `lembaga_id`; FK komposit `(…_id, lembaga_id)` antar tabel ber-tenant; `exam_sessions` mendapat unique `(id, lembaga_id)`
- [x] Model ber-tenant `Device`, `ClientSession`, `ClientEvent`, `RemoteCommand`, `Integration`; enum `ClientSessionStatus`, `EventSeverity`, `RemoteCommandType`
- [x] `session_key` & `unlock_seed` cast `encrypted` + hidden; `integrations.key_hash` hidden + unik; `devices.public_key_sha256` selalu diturunkan dari `public_key`
- [x] `client_events.id` = uuid klien (idempoten lewat `insertOrIgnore`), append-only; sesi klien/event/perintah tanpa mass assignment
- [x] Index untuk dashboard pengawas: `(exam_session_id, status, last_heartbeat_at)`, event per sesi & per severity
- [x] Factory kelima model

**Verifikasi:** `composer check` → Pint passed, PHPStan level 6 [OK], Pest 275 passed (1161 assertions). 5 mutasi (FK tanpa lembaga, event tanpa FK komposit, event bisa diubah, session_key tidak terenkripsi, sesi klien tanpa scope) → semuanya terdeteksi.

**Perubahan dari PRD (sudah disinkronkan di PRD §10):** `devices.lembaga_id` wajib — hanya perangkat terdaftar lembaga yang disimpan; perangkat pribadi tidak (info teknisnya di `client_sessions.device_info`). Kolom tambahan: `client_sessions.app_build`, `device_info`, `lockdown_mode`, `handshake_level`, `level_reasons`, `last_seq`, `last_heartbeat`, `expires_at`; `client_events.seq`; `devices.nama`, `public_key_sha256`, `registered_at`, `revoked_at`, `last_seen_at`.

**Catatan:** belum ada policy untuk model baru (belum ada route) — dibuat bersama TASK-005 (integrasi) dan fitur 07 (dashboard). Migration belum diuji di MySQL 8.

**Berikutnya:** fitur 02 TASK-003 — challenge, handshake, resume, end + verifikasi attestation (**GATE** auth + external integration: Google Play Integrity, Apple App Attest).

### 2026-11-17 — Fitur 02 TASK-003: Handshake, resume, end + attestation (GATE auth & integrasi eksternal disetujui FL)

Area: `btive-exam-browser/console/` — **belum ada commit**.

- [x] API-03a challenge (`ChallengeStore`: 300 detik, sekali pakai dengan lock, terikat sesi + config_hash; replay → 409, kedaluwarsa → 410)
- [x] API-03 handshake: `HandshakeRequest` (validasi tipe ketat, batas 64 KiB, info perangkat dibatasi model/os_version/locale), `HandshakeEvaluator`, `LevelCalculator` (SPEC §9), `StartClientSession`
- [x] API-04 resume (kunci sesi dirotasi, unlock_seed tetap, cap `risk_detected` terbawa) dan API-06 end (idempoten; waktu dari server)
- [x] `VerifyClientSignature` (SPEC §10) — URL kanonis dari request-target mentah
- [x] Attestation: `device_key` lengkap; `play_integrity` lengkap (OAuth service account + decodeIntegrityToken; cek requestHash, package, umur token ≤10 menit, verdict); `app_attest` **fail closed** — dipindah ke fitur 06 TASK-005 (butuh fixture perangkat Apple nyata; tidak membuat verifier kripto tanpa data uji)
- [x] Test terhadap test vectors: `attestation-nonce.json`, `level.json` (handshake & session), `api-signature.json`, `device-key.json`

**Verifikasi:**

```text
composer check → Pint passed, PHPStan level 6 [OK] No errors, Pest 323 passed (1488 assertions)
8 mutasi (tanpa challenge, challenge dipakai ulang, perangkat dicabut dihitung, Play Integrity tanpa cek
  requestHash, endpoint tanpa verifikasi tanda tangan, resume tanpa rotasi kunci, config_hash tidak dicek,
  kunci b64u sebagai HMAC) → semuanya terdeteksi
Klien Node independen (implementasi referensi protokol) lewat HTTP ke console lokal:
  verifyEnvelope ok → challenge → handshake device_key (ECDSA Node) 201 L2 → replay 409 →
  resume bertanda tangan 200 (kunci dirotasi) → end dengan kunci lama 401 → end dengan kunci baru 200
```

**Bug interop yang ditemukan cek silang Node:** console memakai teks base64url `session_key` sebagai kunci HMAC, padahal SPEC/test vectors memakai 32 byte mentah. Test PHP lolos karena helper test memakai kesalahan yang sama. Diperbaiki (`ClientSession::sessionKeyBytes()`), helper test dikoreksi, test regresi ditambah; mutasi kesalahan ini kini menggagalkan 7 test.

**Errata SPEC v1.0.1 (disetujui FL 2026-11-17, sudah diterapkan di `protocol/SPEC.md` §9.1, §16, §19):** kode error `eb_invalid_request` (400) dan `eb_keys_unavailable` (503); `level_reasons` `risk_detected_earlier` saat resume. Non-breaking; test vectors tidak berubah (`--check` & verifier PHP tetap lolos).

**Belum / catatan:**

- Play Integrity hanya diuji dengan respons Google palsu (Http::fake); uji nyata butuh akun Google Cloud + aplikasi Android (fitur 04 TASK-005).
- Event `attestation_unavailable` belum dicatat saat Google tidak terjangkau (menunggu ingest telemetri TASK-004); saat ini hanya log server.
- MySQL 8 belum diuji; cache challenge memakai store default (database) — produksi sebaiknya Redis.

**Berikutnya:** fitur 02 TASK-004 — ingest telemetri batch (API-05) + antrean perintah remote (bukan GATE).

### 2026-11-17 — Fitur 02 TASK-004: Ingest telemetri & antrean perintah

Area: `btive-exam-browser/console/` — **belum ada commit**.

- [x] API-05 `POST /api/v1/sessions/{id}/events` (bertanda tangan): `TelemetryBatch` (validasi ketat, ≤200 event, ≤256 KiB → 413), `IngestTelemetry` (idempoten, `last_seq`, heartbeat, level SPEC §9.3, ack, perintah tertunda)
- [x] `EventRegistry`: severity type yang dikenal ditetapkan server (klien tidak bisa menurunkan bobot pelanggaran)
- [x] Privasi: `data` event hanya object datar berisi skalar ≤200 karakter; heartbeat hanya 4 field dikenal
- [x] Event `ClientTelemetryReceived` per batch (siap dijadikan broadcast Reverb di fitur 07)
- [x] Handshake mencatat event server `attestation_unavailable` (menutup catatan TASK-003)
- [x] **Rate limiter ramah NAT** (memperbaiki batas lama 120/menit/IP untuk semua endpoint protokol yang akan memblokir satu kelas di balik satu IP): named limiter `eb-public`, `eb-handshake`, `eb-session` (per sesi klien + per IP), dapat diatur di `config/exam.php`

**Verifikasi:**

```text
composer check → Pint passed, PHPStan level 6 [OK] No errors, Pest 349 passed (1762 assertions)
6 mutasi (event tidak idempoten, severity dari klien dipercaya, ack perintah sesi lain, level tidak
  dihitung ulang, heartbeat menyimpan field asing, tanpa limit per sesi klien) → semuanya terdeteksi
Benchmark in-process (SQLite lokal, 1 proses PHP, tanpa HTTP):
  40.000 event / 200 batch → ±5.800 event/detik (34 ms/batch 200 event); kirim ulang duplikat 12 ms/batch;
  heartbeat kosong 4,8 ms/request
NAT: 40 perangkat join + challenge dari satu IP lolos tanpa 429
```

**Belum / catatan:**

- AC "500 event/detik per instance (k6)" baru terbukti untuk biaya kode (in-process ±5.800/detik), **belum** lewat HTTP di stack produksi (php-fpm/Octane + MySQL + Redis) — k6 tidak terpasang; uji beban nyata dijadwalkan sebelum pilot.
- Broadcast Reverb dipindah ke fitur 07 TASK-001 (Reverb belum dipasang; event Laravel sudah ada).
- Retensi event telemetri mengikuti OQ3 (belum ada job pembersihan).

**Berikutnya:** fitur 02 TASK-005 — API integrasi untuk target (API key, introspeksi sesi, event L0, finish) + UI kelola integrasi (**GATE** auth).

### 2026-11-17 — Fitur 02 TASK-005: API integrasi target (GATE auth disetujui FL)

Area: `btive-exam-browser/console/` — **belum ada commit**.

- [x] API key `ebk_<prefix8>_<secret32>` (`IntegrationToken`), DB hanya prefix + SHA-256; enum `IntegrationScope` (`sessions.read`, `events.write`, `sessions.finish`); IP allowlist IP/CIDR
- [x] `AuthenticateIntegration` + `IntegrationResolver` (lookup prefix lintas lembaga, constant-time compare, dicabut → 401 seketika, konteks dikunci ke lembaga integrasi) + rate limit per key
- [x] API-07 introspeksi, API-08 event L0 (sesi klien `platform=web` per peserta, satu kali dibuat dengan lock), API-09 finish (idempoten; perintah `finish` sampai ke klien lewat telemetri)
- [x] SCR-C06 `/integrasi` (admin lembaga): buat (token tampil sekali, `#[Locked]`), cabut, daftar dengan prefix tersamar; izin `integrasi.kelola`; log aktivitas tanpa hash

**Verifikasi:**

```text
composer check → Pint passed, PHPStan level 6 [OK] No errors, Pest 367 passed (1894 assertions)
7 mutasi (secret tidak dicek, kunci dicabut tetap berlaku, IP allowlist diabaikan, scope diabaikan,
  konteks sistem untuk integrasi, finish tidak idempoten, …) → semuanya terdeteksi
Browser (admin lembaga demo): buat API key → token tampil sekali → token diterima API (auth lolos) →
  "Sudah saya simpan" → token tidak ada lagi di halaman maupun setelah muat ulang
```

**Temuan saat cek browser:** admin lembaga lokal mendapat 403 karena izin baru belum ada di DB lama — perlu `php artisan db:seed --class=RolePermissionSeeder` setiap kali `Izin` bertambah. Sudah didokumentasikan di README console (bagian Deploy). Usul ke depan: jadikan langkah wajib di skrip deploy.

**Errata SPEC v1.0.2 (disetujui FL 2026-11-17, diterapkan di `protocol/SPEC.md` §14.1, §14.2, §16, §19):** kode error `eb_unauthorized`, `eb_ip_not_allowed`, `eb_scope_missing`; scope API key & IP allowlist; bentuk body/respons API-07/08/09 (sesi L0 `platform: "web"`). Non-breaking; test vectors tidak berubah.

**Berikutnya:** fitur 02 TASK-006 — SDK PHP (Laravel middleware `eb:L2`) & SDK Node (Express/Fastify + `ebFetch`) yang memverifikasi `X-EB-Request-Hash` lewat API-07.

### 2026-11-17 — Fitur 02 TASK-006: SDK PHP & Node

Area: `btive-exam-browser/sdk/{php,node}` — **belum ada commit**.

- [x] `sdk/php` (`btive/exam-browser`): inti framework-agnostic (`RequestHash`, `FinishToken`, `CanonicalUrl`, `Verifier` urutan SPEC §11.3, `Introspector`) + adapter Laravel (middleware `eb:L2[,param_ujian]`, mode `report`/`enforce`, halaman "Buka dengan B'tive Exam Browser", `HttpIntrospector` dengan cache **terenkripsi** APP_KEY, `ExamBrowserClient::finish()`/`finishToken()`)
- [x] `sdk/node` (`@btive/exam-browser`): inti tanpa dependensi, `createIntrospector` (cache memori), middleware Express & hook Fastify, `ebFetch` untuk halaman
- [x] Keduanya: hash gagal dengan data cache → ambil ulang sekali (kunci dirotasi saat resume); tanpa cache → tidak ada permintaan kedua (cegah amplifikasi)
- [x] CI `.github/workflows/sdk.yml` (PHPUnit + node:test)

**Verifikasi:**

```text
sdk/php  → PHPUnit 25 tests OK (78 assertions) — termasuk request-hash.json & finish-token.json, middleware Laravel (Testbench)
sdk/node → node:test 8/8 pass — termasuk request-hash.json & finish-token.json, middleware via server HTTP sungguhan
Interop dengan console asli (localhost): klien referensi Node handshake → header X-EB-Request-Hash →
  SDK Node & SDK PHP sama-sama: sah=ok, URL lain=eb_bad_hash, minta L2=eb_level_insufficient,
  ujian lain=eb_wrong_exam, API key salah=eb_console_unavailable; kunci introspeksi = kunci handshake
```

**Catatan:**

- AC menyebut Vitest; SDK Node memakai `node:test` bawaan supaya tanpa dependensi.
- API key salah/dicabut terbaca sebagai `eb_console_unavailable` (503 di mode enforce) — log server SDK menyebut HTTP 401. Usul: bedakan sebagai `eb_sdk_misconfigured` bila diperlukan.
- Paket belum dipublikasikan (Packagist/npm privat) — untuk SIMS dipasang lewat path/VCS repository.

**Berikutnya:** fitur 02 TASK-007 — integrasi modul Ujian SIMS (setting wajib Exam Browser + level per jadwal, middleware `eb`, halaman FLOW-05, panggil finish). **GATE**: migration + auth + alur nilai di repo SIMS (wajib approval FL sesuai `AGENTS.md`).

### 2026-11-17 — Fitur 02 TASK-007: Integrasi modul Ujian SIMS (GATE migration + auth + alur nilai disetujui FL)

Area: **repo SIMS** (`sims_app-main`) — **belum ada commit**. File milik pekerjaan lain yang sudah termodifikasi di working tree (survei, ModeUjianLockdownTest, dll.) **tidak disentuh**.

- [x] SDK `btive/exam-browser` dipasang lewat path repository `../btive-exam-browser/sdk/php` (pola sama dengan paket `ludensa`); `composer.lock` hanya bertambah paket ini
- [x] Migration `2026_11_17_090000_tambah_exam_browser_ke_ujians`: `ujians.exam_browser_level` (NULL = tidak wajib; 1–3) + `exam_browser_session_id` (opsional, UUID sesi ujian Console) — dipilih per **ujian** (halaman Pengaturan ujian), bukan per jadwal
- [x] Middleware `exam.browser` (`App\Http\Middleware\WajibExamBrowser`): ujian tanpa syarat → tidak disentuh; selain itu SDK `Verifier` (level + sesi Console); mode `report` (default) / `enforce`; halaman & JSON tolak dari SDK
- [x] Dipasang di route siswa: `mulai` (GET), `kerjakan`, `jawab`, `sinkron-batch`, `status`, `kumpul`. **Tidak** di POST `mulai` (navigasi form POST tak bisa diberi header di WebView; dilanjut redirect GET yang dijaga) dan `keluar-fullscreen` (sendBeacon tak bisa diberi header)
- [x] Halaman pengerjaan: semua XHR ke server memakai `_ebHeaders()` (`window.ExamBrowser.sign`) bila di Exam Browser; setelah kumpul, server memanggil API-09 `finish` dan mengembalikan `exam_browser_finish_token` → halaman memanggil `ExamBrowser.finish(token)` (perangkat terbuka seketika). Gagal memanggil Console **tidak** menggagalkan pengumpulan
- [x] Pengaturan ujian (hanya admin/pengelola — `bolehAturKelas`): syarat perangkat L1–L3 + ID sesi Console opsional; peringatan bila server masih mode report
- [x] `.env.example`: `EXAM_BROWSER_CONSOLE_URL`, `EXAM_BROWSER_API_KEY`, `EXAM_BROWSER_MODE=report`, `EXAM_BROWSER_DOWNLOAD_URL`

**Verifikasi (repo SIMS):**

```text
Baseline sebelum perubahan : php -d memory_limit=1G vendor/bin/phpunit --filter="Ujian|ModeUjian" → OK 268 tests
Sesudah                    : OK 277 tests (1248 assertions) — 268 lama tetap hijau + 9 baru (UjianExamBrowserTest)
Mutasi middleware          : enforce diabaikan → 3 gagal; sesi Console tidak dicocokkan → 1 gagal (terdeteksi)
JS halaman pengerjaan      : node --check OK; _ebHeaders diuji dengan window tiruan (tanpa EB → {}, dengan EB → 5 header, sign gagal → {})
```

Catatan uji: `php artisan test` di repo SIMS kehabisan memori (batas 128 MB) — **sudah terjadi sebelum perubahan ini**; jalankan `php -d memory_limit=1G vendor/bin/phpunit`.

**Belum / catatan:**

- Belum diuji dengan aplikasi Exam Browser sungguhan (klien Android/Windows/Apple belum ada); alur end-to-end di atas memakai header buatan test.
- Ujian yang auto-submit karena waktu habis (`ujian:auto-submit`) tidak memanggil `finish` — siswa keluar lewat kode pengawas / password darurat. Bisa ditambah kemudian.
- Path repository mengharuskan folder `btive-exam-browser` ada di server saat `composer install` — sebelum deploy, terbitkan SDK ke repository privat (VCS/Packagist privat).
- SEB (`app/Survei/SafeExamBrowser.php`) hanya dipakai modul Survei, bukan Ujian — tidak ada yang perlu dipensiunkan di Ujian.
- Sebelum mengubah ke `enforce` di sekolah: jalankan audit `laravel-security-audit` untuk perubahan ini dan uji `report` minimal 1 minggu (DoD fitur 02).

**Berikutnya:** fitur 02 TASK-008 — alur selesai & keluar aman (quit_policy, token finish, password darurat — sebagian besar sudah ada di console; sisa: event `ended/unlocked_by_proctor/emergency_quit` & dokumentasi).

### 2026-11-17 — Audit keamanan TASK-007 (repo SIMS)

Laporan: `docs/exam-browser/audit/2026-11-17-task-007-sims.md`. **Hanya audit — belum ada patch.**

- **CRITICAL C1 (sudah ada sebelum TASK-007):** `POST /ujian/restore` tanpa otorisasi + `Model::unguard()` → siswa terbukti bisa mengubah `skor_objektif` attempt-nya menjadi 100, mengganti judul ujian, dan mencabut syarat Exam Browser (probe P2).
- MEDIUM: M1 sesi Exam Browser tidak diikat ke attempt (patch butuh **GATE migration**); M2 mode enforce + Console mati → penyimpanan jawaban 503.
- LOW: L1 cache 120 detik menunda status kunci Console; L2 POST mulai tak dijaga; L3 `trustProxies('*')` (lama); L4 URL Console tidak dipaksa HTTPS.
- 6 probe test sementara (dihapus setelahnya).

**Berikutnya:** keputusan FL untuk patch C1 (segera), lalu M2/L1/L2/L4 sebelum `enforce`; M1 = GATE migration.

### 2026-11-17 — Patch audit C1, M2, L1, L2, L4 (tanpa migration; disetujui FL)

- [x] **C1** — `UjianPolicy::restoreBackup()` (admin / `manage_ujian`); `UjianBackupController::restore()` memanggil `authorize('restoreBackup')` paling awal + `authorize('manage')` untuk ujian yang sudah ada; file wajib `mimetypes:application/json,text/plain` (maks 50 MB). Tombol & modal restore di `ujian/index` dibungkus `@can('restoreBackup')`. `Model::unguard()` **masih** dipakai (whitelist kolom = perubahan perilaku → keputusan FL).
- [x] **M2** — `WajibExamBrowser`: bila Console tidak terjangkau (`eb_console_unavailable`), request **penyimpanan** (`jawab`, `sinkron-batch`, `kumpul`) untuk attempt yang sebelumnya sudah lolos verifikasi di sesi login yang sama tetap diteruskan + `Log::warning`. Halaman (`kerjakan`, `status`, `mulai`) tetap fail-closed; request tanpa header tetap ditolak.
- [x] **L1** — default `cache_ttl` SDK 120 → 30 detik; `EXAM_BROWSER_CACHE_TTL=30` di `.env.example`.
- [x] **L2** — GET `mulai` yang lolos verifikasi menyimpan penanda gate di sesi; POST `mulai` saat ujian wajib + `enforce` ditolak (redirect ke gate, attempt tidak dibuat) bila penanda tidak ada / > 15 menit. Mode `report` tidak terpengaruh.
- [x] **L4** — SDK `ConsoleUrl`: URL Console wajib `https://` (http hanya untuk `localhost`/`127.0.0.1`/`::1`); dicek sebelum API key dikirim (`HttpIntrospector`, `ExamBrowserClient::finish`).

**Verifikasi:**

```text
SIMS  : php -d memory_limit=1G vendor/bin/phpunit --filter="Ujian|ModeUjian" → OK 286 tests (1293 assertions)
        (277 sebelumnya + 4 di UjianExamBrowserTest + 5 di UjianBackupRestoreSecurityTest baru)
SDK   : vendor/bin/phpunit → OK 28 tests (89 assertions)
Mutasi: tanpa authorize restoreBackup → gagal; policy selalu true → gagal; tanpa mimetypes → gagal;
        tanpa fail-open → gagal; fail-open meluas ke kerjakan → gagal; tanpa cek gate di start() → 2 gagal.
        authorize('manage', $existing) bertahan (mutan ekuivalen: `manage` sudah meloloskan admin/manage_ujian) — pertahanan berlapis.
```

**Belum:** M1 (ikat sesi Exam Browser ke attempt — **GATE migration** `ujian_attempts.eb_client_session_id`), L3 (`trustProxies('*')`, perlu IP proxy hosting). Pint melaporkan gaya lama di `UjianPolicy.php` (bukan dari patch; tidak diformat ulang).

**Berikutnya:** keputusan FL untuk M1 (GATE) / L3, lalu fitur 02 TASK-008.

### 2026-11-17 — Patch audit M1: ikat sesi Exam Browser ke attempt (GATE migration, disetujui FL)

- [x] Migration `2026_11_17_100000_tambah_eb_client_session_id_ke_ujian_attempts`: `ujian_attempts.eb_client_session_id` (string 64, nullable). Diuji up → rollback → up di DB lokal (SQLite).
- [x] `WajibExamBrowser`: request terverifikasi pertama pada attempt **milik user yang login** dan masih `in_progress` mengikat attempt ke `session_id` klien (UPDATE … WHERE NULL — atomik). Request berikutnya dari sesi lain:
  - `enforce` → attempt dikunci (`lockForUpdate`) + `UjianPelanggaran` tipe `eb_perangkat_lain` ("Exam Browser dari Perangkat Lain"); JSON 403 `eb_wrong_session` / halaman `ujian.siswa.terkunci` dengan alasan khusus.
  - `report` → hanya `Log::warning`.
- [x] Request siswa lain ke attempt orang lain tidak mengikat dan tidak mengunci (tetap ditolak `pastikanMilikSiswa`).
- [x] `UjianKuncian::bukaKunci()` (monitor ujian & monitor ruangan) melepas ikatan → pindah perangkat sah = pengawas membuka kunci; perangkat berikutnya mengikat ulang. Resume aplikasi (crash/restart) tidak terkena karena `session_id` tetap (SPEC §8.5).
- [x] `terkunci.blade.php` menerima `$alasan` opsional (teks lama tetap default).

**Verifikasi:**

```text
SIMS  : php -d memory_limit=1G vendor/bin/phpunit --filter="Ujian|ModeUjian" → OK 289 tests (1323 assertions) — +3 test M1
Mutasi: sesi tidak dicocokkan / tanpa cek pemilik / enforce tidak menolak / tidak mengunci / buka kunci tidak melepas ikatan → masing-masing 1 gagal.
        Ikat tanpa `whereNull` bertahan — penjaga race dua request pertama bersamaan, tak bisa dipicu test sekuensial.
Pint  : lolos (middleware + migration)
```

**Catatan:** "Buka Kembali Akses" (`bukaAksesSelesai`) untuk attempt yang sudah selesai tidak melepas ikatan — bila siswa melanjutkan dari perangkat lain, attempt terkunci dan pengawas cukup membuka kunci. Sisa audit: **L3** (`trustProxies`).

**Berikutnya:** L3 (butuh IP proxy hosting) atau fitur 02 TASK-008.

### 2026-11-17 — Fitur 02 TASK-008: Alur selesai & keluar aman (GATE auth, disetujui FL)

Sebagian besar sudah ada sejak TASK-002–007 (SPEC §8.6/§12, `ConfigBuilder` quit_policy, `FinishToken` SDK PHP/Node, `EmergencyPassword`, API-06/API-09). Yang ditambahkan:

- [x] **Console `App\Crypto\UnlockCode`** — TOTP SPEC §12.3 (HMAC-SHA256, 60 detik, 6 digit, jendela ±1, tolak counter ≤ terakhir dipakai, `secondsLeft()` untuk countdown). Lolos seluruh `totp.json` (code_cases + verify_cases). UI "Lihat kode" tetap fitur 07 TASK-003.
- [x] **API-06 mencocokkan alasan keluar** dengan `quit_policy` snapshot & keadaan server: `target_finish` butuh status `finished` (API-09 sudah dipanggil) + diaktifkan; `quit_url` butuh quit_url diatur; `proctor_code`/`emergency_password` harus diaktifkan; `kicked` butuh perintah `kick`; `time_over` butuh `expires_at`/`closes_at` lewat (toleransi 120 detik) atau sesi ujian ditutup. Sesi **tetap diakhiri** (server tak bisa menahan klien), lalu dicatat di activity log `sesi_klien` (`sesi_klien_diakhiri` / `sesi_klien_diakhiri_di_luar_kebijakan`, properti `sesuai_kebijakan`, `catatan`, `status_sebelumnya`; tanpa quit_url mentah). Panggilan ulang tidak menambah catatan.
- [x] **SIMS: finish saat selesai tanpa tombol Kumpulkan** — `UjianGrader::autoSubmitKarenaWaktuHabis()` (cron `ujian:auto-submit`, halaman saat waktu habis, "Selesaikan paksa" guru/semua) memanggil API-09 lewat `App\Services\ExamBrowserSelesai` untuk attempt yang terikat sesi Exam Browser (M1). Di luar transaksi; gagal → log, penilaian tetap jalan; setelah Console sekali tak terjangkau, sisa attempt dalam loop yang sama dilewati (tidak menunggu timeout berulang).
- [x] Catatan pemetaan event (`ended`/`unlocked_by_proctor`/`emergency_quit` → `session_ended` + `reason`) di `features/02-protokol-integritas-sdk.md`.

**Verifikasi:**

```text
Console : php vendor/bin/pest → 378 passed (2001 assertions); +10 EndPolicyTest, +1 vektor TOTP
          phpstan → No errors; pint → passed
SIMS    : php -d memory_limit=1G vendor/bin/phpunit --filter="Ujian|ModeUjian" → OK 293 tests (1341 assertions); +4 test TASK-008
Mutasi  : Console — target_finish tanpa cek API-09, quit_url/kick/time_over/password selalu sah, TOTP tanpa anti-reuse,
          jendela TOTP melebar → masing-masing 1 gagal.
          SIMS — grader tidak memanggil finish → 3 gagal; tanpa penanda Console mati → 1 gagal.
          Tanpa cek ikatan NULL bertahan (finish(null) gagal TypeError → tertangkap; hanya mencegah log palsu).
```

**Belum / di luar task ini:** penguncian keluar di klien (AC diuji di fitur 04–06); UI kode unlock, kick, `finish_forced` otomatis saat sesi ditutup (fitur 07); verifikasi TOTP di server SIMS untuk mode web L0 (fitur 03 TASK — butuh `UnlockCode` versi SDK PHP).

**Berikutnya:** fitur 03 (mode browser SIMS) atau fitur 07 (monitoring pengawas) — keduanya punya task GATE.

### 2026-11-21 — Fitur 07 TASK-001: Dashboard pemantauan (GATE auth + migration, disetujui FL)

Keputusan FL: penugasan pengawas lewat tabel baru; **polling dulu**, Reverb ditunda (tanpa paket/proses websocket baru).

- [x] Migration console `2026_11_21_000001_create_exam_session_pengawas_table`: `exam_session_pengawas` (UUID, `lembaga_id`, unik per sesi+user, FK komposit ke `exam_sessions(id, lembaga_id)` dan `users(id, lembaga_id)`; `users` mendapat unique `(id, lembaga_id)`). Diuji up → rollback → up di DB lokal console (SQLite).
- [x] Policy `ExamSessionPolicy`: `pantau` (izin lihat sesi **atau** ditugaskan ke sesi itu), `pantauAny` (menu), `aturPengawas` (= kelola sesi). Penugasan **tidak** membuka detail sesi (kode join, password darurat).
- [x] SCR-C03: komponen `SesiUjian\Pengawas` — tugaskan/cabut pengawas (hanya peran `pengawas` di lembaga yang sama, diambil ulang dari server), `DB::transaction()` + activity log `pengawas_ditugaskan` / `pengawas_dicabut`; tombol "Pantau peserta".
- [x] SCR-C04: `/pantau` (daftar sesi yang boleh dipantau; pengawas hanya sesi tugasnya) dan `/sesi-ujian/{id}/pantau` — kartu peserta (status warna, level, platform/versi/model, jumlah pelanggaran & berat, pelanggaran terakhir menurut `occurred_at`), filter Semua/Online/Offline/Terkunci-melanggar/Selesai, pencarian, peringatan multi-perangkat.
- [x] Polling JSON `/sesi-ujian/{id}/pantau/data?since=` tiap 5 detik (10 detik setelah gagal), hanya peserta yang berubah (baris sesi klien diperbarui atau event baru) — `App\Monitor\DataPantau`. Offline = heartbeat > 2× interval (dihitung di peramban dari interval snapshot profil). Tanpa `session_key`/`unlock_seed`/payload event. Throttle 60/menit.

**Verifikasi:**

```text
Console : php vendor/bin/pest → 390 passed (2064 assertions); +12 tests/Feature/Pantau/PantauTest.php
          phpstan → No errors; pint → passed; npm run build (kelas Tailwind baru)
Mutasi  : pantau selalu boleh, penugasan tanpa cek sesi, aturPengawas selalu boleh, calon tanpa cek peran, delta mengembalikan
          semua, multi-perangkat menghitung sesi selesai, pelanggaran terakhir via received_at, endpoint data tanpa authorize
          → masing-masing ≥ 1 gagal.
Beban   : 1.000 peserta + 10.000 event (SQLite lokal): muat penuh median 174 ms / 355 KB; delta 70 peserta median 31 ms / 25 KB.
Browser : login pengawas demo (salinan DB di scratchpad) → kartu, filter, multi-perangkat, peralihan offline → online lewat delta
          tanpa reload, label "Offline · dikunci"; 375 px tanpa scroll horizontal; konsol tanpa error.
```

**Belum / catatan:** Reverb (AC 1 ≤ 5 detik sudah terpenuhi lewat polling 5 detik); uji beban di MySQL 8 produksi; detail peserta, kode unlock, perintah remote = TASK-002–004. `.claude/launch.json` SIMS: entri `exam-console` dipindah ke path `Documents/Proyek`, ditambah `exam-console-preview` (DB salinan di scratchpad).

**Berikutnya:** fitur 07 TASK-002 — detail peserta (timeline event, perangkat, riwayat level & perintah; bukan GATE).

### 2026-11-21 — Fitur 07 TASK-002: Detail peserta (SCR-C05)

- [x] `Livewire\Pantau\PesertaDetail` di `/sesi-ujian/{sessionId}/pantau/{clientId}` (otorisasi `pantau`; sesi klien harus milik sesi pada URL, selain itu 404; refresh `wire:poll.10s`).
- [x] `App\Monitor\RiwayatPeserta`: timeline urut `occurred_at` lalu `seq` (maks 500 terbaru, ada penanda terpotong), durasi dari `data.duration_ms`, penanda "(offline)" bila diterima > 60 dtk setelah kejadian; riwayat level direkonstruksi dengan `LevelCalculator` yang sama dengan ingest (tanpa tabel baru, tanpa migration).
- [x] Info perangkat, riwayat perintah (penerbit, issued/delivered/ack). `session_key`/`unlock_seed` tidak pernah dirender. Kartu di dashboard menaut ke detail.

**Verifikasi:** `composer check` → Pint, PHPStan, Pest 396 passed (+6 `PesertaDetailTest`).
**Belum:** review visual di peramban; verifikasi mutasi belum dijalankan.
**Berikutnya:** (lihat TASK-003 di bawah)

### 2026-11-21 — Fitur 07 TASK-003: Kode unlock per peserta (GATE auth; lanjut atas arahan FL, review visual TASK-002 dilewati)

- [x] `PesertaDetail::tampilkanKode()` — kode TOTP (`App\Crypto\UnlockCode`, sudah cocok `totp.json`) dari `unlock_seed` sesi klien; countdown di UI; server mengosongkan kode setelah jendela 60 dtk.
- [x] Policy `ExamSessionPolicy::lihatKodeUnlock` (= hak `pantau`: pemegang izin lihat sesi atau pengawas yang ditugaskan). Diperiksa ulang di aksi, jadi pencabutan penugasan setelah halaman terbuka langsung berlaku.
- [x] Hanya sesi klien berjalan (aktif/dikunci/jawaban tersimpan); rate limit 10 tampilan/menit/pengguna.
- [x] Setiap tampilan → activity log `kode_unlock_dilihat` (pengguna, `client_session_id`, `peserta_ref`, `counter`) dalam `DB::transaction()` sebelum kode diberikan; **kode dan seed tidak pernah masuk log/HTML selain kode yang ditampilkan**.
- Tanpa migration; tanpa perubahan protokol.

**Verifikasi:** `composer check` → Pint, PHPStan [OK], Pest 404 passed (+8 `KodeUnlockTest`).
**Mutasi:** hapus authorize di aksi, hapus pemanggilan activity log, hapus rate limit → masing-masing ≥ 1 tes gagal (mutasi authorize awalnya lolos; ditutup tes "penugasan dicabut setelah halaman terbuka").
**Belum:** review visual TASK-002 dan TASK-003 di peramban (ekstensi Chrome tidak bisa membuka host lokal); verifikasi kode di klien native (fitur 04–06).
**Catatan keputusan:** guru dan admin lembaga ikut boleh melihat kode (hak lihat sesi), bukan hanya pengawas. Bila FL ingin membatasi ke pengawas/admin, ubah satu method policy.
**Berikutnya:** fitur 07 TASK-004 — perintah remote (**GATE** auth).


### Fitur 04 — Fondasi Android dan diagnostik kiosk

- Tambah proyek Kotlin/Compose, Gradle Wrapper, CI, verifikasi konfigurasi Ed25519, serta pengujian Kotlin yang membaca test vectors v1.
- Tambah join dengan kode/deep link, konfigurasi Device Owner untuk Lock Task, dan diagnostik debug 60 detik tanpa soal. Uji QR provisioning dan perilaku tablet masih tertunda.
- Generator dan verifier protokol Node/PHP lulus. Gradle Wrapper berjalan sampai pengunduhan distribusi; DNS sandbox menolak services.gradle.org. Belum ada hasil build, lint, atau tes Kotlin.
- Regresi SQLite lulus 293 tes SIMS dan 416 tes Console. Batas perintah tambah waktu 1–120 menit dipulihkan di salinan Console. Tes SDK Node berbasis socket tidak dapat bind di sandbox dan alur middleware diverifikasi dalam proses.
- Milestone UAS masih membutuhkan GATE registrasi perangkat dan alur keluar Fitur 04 TASK-007, sesi L3, WebView dan integrasi SIMS, pemulihan, serta verifikasi staging dan tablet.

### 2026-11-21 — Sinkron origin/main SIMS

- Integrasikan dedyzhang/sims_app main pada 5657b78 dengan perubahan kerja lokal melalui stash. Konflik saat pemulihan ada di skrip fokus dan tes survei.
- Pertahankan token kelas dan penghentian UI/rute SEB dan SIMS Kios. Survei dengan mode lama ditolak secara eksplisit tanpa mengubah data periode. Fitur survei dan cetak soal dari upstream serta middleware Exam Browser pada ujian SIMS ikut dipertahankan.
- Simpan stash pemulihan dari salinan pengembangan sampai perubahan lokal selesai ditinjau.

### 2026-11-21 — Review awal dan regresi di Windows

- Review engineering:code-review + laravel-security-audit; laporan di btive-exam-browser/docs/audit/2026-11-21-windows-preflight.md.
- Perbaiki normalisasi path ArchTest, kunci sementara khusus tes, dan default locale Indonesia. Pisahkan tes izin POSIX dari tes fungsi kunci; pemeriksaan POSIX dilewati pada Windows, ACL belum diverifikasi.
- Console final: **416 passed, 1 skipped (2218 assertions)**, tanpa warning. Pint passed; PHPStan No errors. Protokol Node seluruh vektor cocok; PHP 180 passed, 0 failed, 36 not applicable.
- TASK-004 monitoring: implementasi perintah remote dan PerintahRemoteTest sudah ada pada salinan ini; regresi server lulus. E2E klien native belum selesai, sehingga task belum memenuhi seluruh verification.
- Build Android dilanjutkan: Gradle 8.13 berhasil diunduh; build berhenti karena JDK 17 belum ditemukan. Instalasi Temurin 17 via winget telah dimulai tetapi belum memberikan hasil sukses. Tes Kotlin, lint Android dan APK masih belum terverifikasi.
- Risiko review: ekspor seed KeysGenerate mengklaim chmod 0600 pada Windows tanpa verifikasi ACL. Belum dipatch; hindari menganggap ekspor tersebut terproteksi sebelum validasi ACL.
- Berikutnya: selesaikan instalasi JDK 17 dan ulangi :core-protocol:test :app:lintDebug :app:assembleDebug; lanjutkan uji tablet dan E2E native. Tidak ada migration produksi, commit, push, atau deploy.

### 2026-11-21 — Mode Exam Browser di Ujian SIMS

- Halaman siswa merender panel soal setelah gerbang layar penuh dipenuhi. Mode dasar tersedia pada browser tanpa Fullscreen API. Klien Exam Browser yang diverifikasi server langsung masuk ke soal.
- Tes SIMS memakai masa berlaku sesi yang realistis. Saat Console terputus, tes memastikan sinkronisasi sesi aktif dengan tanda tangan yang cocok tetap berjalan dan tanda tangan untuk URL lain ditolak.
- Verifikasi lokal: 38 tes PHP (252 assertions) dan 4 tes frontend lulus. Uji manual di browser dan perangkat sekolah masih tertunda.

### 2026-11-21 — Firebase FCM untuk Exam Browser Android

- Klien mendaftarkan token ke SIMS setelah pengguna mengaktifkan notifikasi dan membuka ujian di origin SIMS yang dikonfigurasi. Token khusus `android_exam` menerima sinyal data-only generik melalui antrean SIMS.
- Ketukan notifikasi membuka Dashboard SIMS di luar Lock Task. Saat ujian dikunci, pesan pengawas tetap menggunakan jalur Exam Console di dalam aplikasi.
- Verifikasi lokal: 59 tes PHP (310 assertions), 4 tes frontend, tes protokol Kotlin, lint debug, APK debug, dan AAB berhasil. AAB masih tanpa tanda tangan rilis.
- Uji pengiriman nyata masih memerlukan konfigurasi Firebase staging yang cocok dengan package Android, akses SIMS staging, dan tablet sekolah.

### 2026-11-21 — Integrasi Android L1 pilot dengan modul Ujian SIMS

- JDK 17, Gradle 8.13, Build Tools 35 dan dependency Android berhasil dipasang; dependency SDK PHP lokal SIMS dipulihkan dari composer.lock. File placeholder link arsip di vendor dipindahkan ke cadangan sebelum Composer membuat junction valid.
- Android: handshake/resume, penyimpanan sesi/outbox AES-GCM Keystore + AtomicFile, WebView origin bridge sign/finish/info, pembatasan navigasi, telemetri/pesan/lock/unlock, finish token dan kode pengawas. Kode dan rate limit dipersist; pemulihan offline tidak membuka soal sebelum resume. Debug diagnostik tidak boleh melepas sesi nyata.
- SIMS: tutup pasangan ujian-attempt berbeda, batasi fallback offline dengan HMAC/ikatan SID/ujian/level/bukti terenkripsi 5 menit, wajibkan ID sesi Console pada pengaturan, bypass gate L0 hanya dari verifikasi server. ACK jawaban tidak menghapus revisi lebih baru; jawaban lokal dipulihkan ke antrean; submit berhenti saat flush gagal, dengan timeout dan status rekonsiliasi.
- Regresi: SIMS 305 passed (1406 assertions); Node 3 passed. Android core vectors + SessionAccess serta assembleDebug/lintDebug telah lulus; build terbaru perlu memperhitungkan perubahan FCM paralel yang masuk workspace.
- Panduan: INTEGRASI-UAS-PILOT.md. Skrip build dengan URL/pin publik: clients/android/tools/Build-SimsPilot.ps1. APK default tidak memiliki konfigurasi staging dan belum untuk distribusi UAS.
- Masih terbuka: resume idempoten bila respons rotasi kunci hilang; kebijakan final batch terhadap deadline keras dan rekonsiliasi; E2E staging; pesan pengawas dengan Lock Task pada tablet nyata; L2/L3 dan rilis produksi. Tidak ada perangkat adb terhubung.
- Tidak ada commit, push, deployment, atau migration produksi pada sesi ini.
**Verifikasi Android final:** core 9 metode tes, 0 failures/errors; lintDebug dan assembleDebug BUILD SUCCESSFUL pada kondisi terbaru termasuk source FCM yang masuk paralel. APK debug terverifikasi apksigner; SHA-256 31E27F1FBB88386595A19472FC5261FF18A5B86B35205715505883698FB27905. Tidak ada klaim push atau Lock Task perangkat nyata lulus. Tes SIMS kini dapat diisolasi dengan SIMS_TEST_DATABASE=:memory: agar tidak berebut database/testing.sqlite dengan proses lain.
**Regresi SIMS final terisolasi:** SIMS_TEST_DATABASE=:memory: → OK 305 tests, 1408 assertions. Node 3 tests pass. Run pada database testing bersama sempat gagal akibat tabel hilang di tengah tes; hasil final menggunakan database terisolasi.

### 2026-11-21 — Perbaikan hasil review Claude (REVIEW-CLAUDE.md)

- **Deadline (#1–2):** jawaban diterima selama masa tenggang sinkron 45 detik setelah `batas_waktu_pada` (`UjianAttempt::MASA_TENGGANG_SINKRON_DETIK`); cron `ujian:auto-submit`, halaman kerjakan, dan status baru memfinalisasi setelah tenggang. Auto-submit waktu habis dan error 4xx permanen tetap mengumpulkan (salinan lokal jawaban yang belum diakui dipertahankan untuk rekonsiliasi); kumpul manual saat jaringan putus tetap meminta ulang. Auto-submit gagal diulang tiap 10 detik (bukan tiap detik). Submit 422 → kembali ke gate/hasil. Textarea esai `maxlength=10000`.
- **Console offline (#6):** fail-open terbatas kini juga untuk GET kerjakan & status; jendela bukti offline dari `EXAM_BROWSER_OFFLINE_GRACE` (default 1800 detik, dibatasi `expires_at`). Submit offline tetap memberi finish token dari bukti offline. Verifier memakai jam Carbon yang sama dengan jalur offline.
- **Console resume (#3):** kunci lama disimpan sebagai `previous_session_key` (migration `2026_11_21_000002`) dan diterima sampai klien memakai kunci baru atau 30 menit; SPEC §8.5 diperbarui (v1.0.2).
- **Android (#4, #5, #8, #10, #13):** keluar tertunda memakai offset jam server, ulang sekali saat `eb_stale`, dan menghapus state bila ditolak permanen; kode join berbeda dari sesi tersimpan tidak melanjutkan sesi siswa lain; pesan pengawas tampil sebagai banner kuning yang bertahan; tunggu status Lock Task hingga 1 detik; dependensi Gson eksplisit.
- Verifikasi: SIMS 1648 tes (2 gagal di luar modul ujian — Langganan, SekretarisAbsensi — sudah gagal sebelum perubahan); Exam Browser 27 tes OK; Node 37 tes OK; Console Pest 418 passed; Android core 9 tes, lintDebug 0 error, assembleDebug + bundleRelease sukses.
- Masih terbuka: SOP kunci `focus_lost` saat Console offline (#7), deploy SDK path repository (#9), distribusi Play/Managed Google Play (#12), pembersihan kode SEB/Kios survei yang mati (#14), uji tablet & staging.
