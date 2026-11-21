# 03 Mode Browser SIMS (L0)

## Outcome

Sekolah yang belum memasang aplikasi tetap mendapat penguncian dasar di browser biasa: fullscreen dipaksa, pindah tab/aplikasi tercatat sebagai pelanggaran, dan pengawas melihatnya di dashboard yang sama dengan klien native.

## Scope

### Included

- Komponen web lock untuk halaman ujian siswa SIMS (memperluas `ModeUjian` + `arena-focus-lock`)
- Deteksi: keluar fullscreen, `visibilitychange`, `blur`, `pagehide`, resize drastis (split-screen), devtools heuristik, copy/paste/cut, klik kanan, print, drag-drop
- Ambang pelanggaran → kunci halaman, butuh kode pengawas
- Forward pelanggaran ke console (API-08) sebagai sesi L0
- Bypass otomatis lapisan web bila halaman dibuka di Exam Browser native

### Excluded

- Mencegah screenshot, aplikasi lain, atau chatbot di perangkat lain (tidak mungkin di level web — dinyatakan di UI pengawas sebagai "L0: deterrent")

## Linked artifacts

- Requirements: REQ-012, REQ-013
- Flows/screens: FLOW-04, FLOW-05; SCR-C04 (label L0)
- Entities/APIs: API-08

## User story / scenario

- Sebagai guru di sekolah tanpa lab, saya ingin ujian tetap bisa dikerjakan di Chrome siswa dengan pencatatan pelanggaran, agar ada efek jera.
- Sebagai pengawas, saya ingin melihat siswa L0 dan L1+ di satu dashboard dengan label level.

## Acceptance criteria

- Given jadwal dengan `min_level = L0`, when siswa membuka soal tanpa fullscreen, then gate "Masuk mode ujian" tampil dan soal tidak dirender sebelum fullscreen aktif (kecuali iOS Safari yang tidak mendukung Fullscreen API → fallback CSS + catatan).
- Given siswa pindah tab, when kembali, then event `focus_lost` dengan durasi tercatat di SIMS dan diteruskan ke console.
- Given pelanggaran mencapai ambang profil (default 3), when terjadi lagi, then halaman terkunci dan hanya kode pengawas yang membuka.
- Given halaman dibuka di Exam Browser native (`window.ExamBrowser` ada + header terverifikasi), when dimuat, then lapisan L0 tidak aktif.
- Given jadwal `min_level ≥ L1`, when dibuka di browser biasa, then halaman FLOW-05 tampil (bukan mode L0).

## Edge cases and states

- Notifikasi OS / dialog izin browser memicu `blur` singkat → abaikan < 1 detik (konfigurabel).
- Koneksi putus: event di-queue di `localStorage` (try/catch) dan dikirim ulang.
- Jawaban otomatis tersimpan sebelum halaman dikunci.
- Android WebView SIMS existing: pakai bridge `AndroidFcm.enterArenaFocus()` bila tersedia.

## Tasks

### TASK-001 - Komponen `exam-web-lock`

- Objective: komponen Blade + modul JS (refactor dari `arena-focus-lock`) dengan gate fullscreen, detektor event, overlay pelanggaran.
- Scope/files or area: SIMS `resources/views/components/exam-web-lock.blade.php`, `resources/js/exam-web-lock.js`, view ujian siswa.
- Dependency: fitur 02 TASK-007 (setting level per jadwal)
- Acceptance: AC 1–3 di atas.
- Verification: test frontend (`tests/Frontend/*.test.cjs`) + uji manual Chrome/Edge/Firefox/Safari, Android Chrome.
- Risk/approval gate: —

### TASK-002 - Pelaporan pelanggaran SIMS → console

- Objective: endpoint SIMS penerima event (auth siswa, scope jadwal), simpan ringkas di SIMS untuk rekap guru, forward ke console API-08 lewat job antrean.
- Scope/files or area: SIMS controller ujian, job `ForwardExamViolation`, activity log.
- Dependency: TASK-001, fitur 02 TASK-005
- Acceptance: event tampil di console ≤ 10 detik; console down tidak memblokir siswa (retry).
- Verification: `php artisan test --filter=Ujian` + Http::fake untuk console.
- Risk/approval gate: **GATE** migration (tabel event di SIMS) + external integration.

### TASK-003 - Kunci halaman & kode pengawas

- Objective: ambang pelanggaran per profil; overlay terkunci; seed TOTP per siswa per jadwal dibuat SIMS (encrypted, tidak pernah dikirim ke browser siswa — verifikasi kode dilakukan server SIMS); pengawas SIMS melihat kode di monitor ruangan existing (`UjianRuanganMonitorController`).
- Scope/files or area: SIMS monitor ruangan, komponen lock.
- Dependency: TASK-001
- Acceptance: AC 3; melihat kode tercatat di activity log.
- Verification: Pest + uji manual.
- Risk/approval gate: **GATE** auth (kode unlock).

### TASK-004 - Deteksi Exam Browser native

- Objective: bila SDK memverifikasi header valid, set flag server-side sehingga komponen L0 tidak dirender; jangan percaya user agent saja.
- Scope/files or area: middleware SDK di SIMS, view ujian.
- Dependency: fitur 02 TASK-006
- Acceptance: AC 4.
- Verification: Pest dengan header dari test vectors.
- Risk/approval gate: —

### TASK-005 - Regresi & dokumentasi

- Objective: perbarui `tests/Feature/ModeUjianLockdownTest.php` dan panduan pengguna (bagian Ujian) dengan penjelasan level L0–L3.
- Scope/files or area: tests, `docs/PANDUAN_PENGGUNAAN_SIMS_APP.md`.
- Dependency: TASK-001–004
- Acceptance: suite Ujian hijau.
- Verification: `php artisan test --filter="Ujian|ModeUjian"`.
- Risk/approval gate: —

## Definition of done

- Acceptance criteria pass; suite test Ujian/ModeUjian hijau.
- Default perilaku jadwal lama tidak berubah.
- Pengawas melihat label "L0 – penguncian dasar" dengan penjelasan batasnya.
