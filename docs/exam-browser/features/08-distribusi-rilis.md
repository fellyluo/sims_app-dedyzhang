# 08 Distribusi & Rilis

## Outcome

Semua klien sampai ke perangkat siswa dalam bentuk bertanda tangan resmi, versi usang dipaksa update dari console, crash terpantau, dan produk lolos pilot sekolah sebelum GA.

## Scope

### Included

- Pipeline build rilis bertanda tangan per platform
- Registry rilis di console + enforcement versi minimum (API-10)
- Android: Google Play (listing, Data safety, closed test) + APK langsung untuk perangkat terkelola
- Windows: MSIX (Store/App Installer) + MSI untuk Intune/GPO
- Apple: App Store (iOS/iPadOS, macOS) dan/atau Developer ID + notarization (macOS di luar Store)
- Crash reporting & observability
- Red-team checklist master, pilot sekolah, panduan admin/pengawas/siswa

### Excluded

- Billing/lisensi (OQ1)
- Distribusi Linux/ChromeOS

## Linked artifacts

- Requirements: REQ-015; NFR-008
- Flows/screens: SCR-C08; SCR-K01 (state versi usang)
- Entities/APIs: ENT-11; API-10

## User story / scenario

- Sebagai super admin, saya ingin menaikkan versi minimum setelah menemukan bypass, agar klien lama tidak bisa ikut ujian berikutnya.
- Sebagai admin IT sekolah, saya ingin memasang klien Windows ke seluruh lab lewat Intune/GPO.

## Acceptance criteria

- Given versi minimum dinaikkan di console, when klien lama join, then ditolak dengan tombol update yang membuka store/unduhan resmi.
- Given build rilis, when diperiksa, then tertandatangani (APK/AAB signature, Authenticode/MSIX, Apple notarization/App Store) dan checksum tercatat di `app_releases`.
- Given crash di klien, when terjadi, then laporan masuk ke crash reporting tanpa data pribadi siswa (tanpa URL query, tanpa isi halaman).
- Given red-team checklist, when dijalankan pada kandidat rilis, then ≥ 95% per platform, dan semua temuan kritis tertutup.
- Given pilot, when satu periode ujian selesai, then 0 kehilangan jawaban dan metrik di PRD §4 tercapai.

## Edge cases and states

- Store review lambat/ditolak saat ada perbaikan keamanan mendesak → jalur APK/MSI/Developer ID untuk perangkat terkelola + versi minimum dinaikkan setelah store menyetujui.
- Sekolah tanpa internet saat jam ujian (lab offline): dokumentasikan bahwa console harus terjangkau; mode console on-premise = Later.
- Rollback rilis bermasalah: turunkan versi minimum, tarik rilis dari registry.

## Tasks

### TASK-001 - Pipeline rilis bertanda tangan

- Objective: workflow CI per platform yang menghasilkan artefak rilis bertanda tangan dari tag `android-v*`, `windows-v*`, `apple-v*`; secret signing di secret store CI.
- Scope/files or area: `.github/workflows/release-*.yml`, dokumen manajemen kunci.
- Dependency: fitur 04/05/06 build stabil
- Acceptance: AC 2.
- Verification: rilis uji internal.
- Risk/approval gate: **GATE** external integration + production deploy (kunci signing, akun store).

### TASK-002 - Registry rilis & versi minimum

- Objective: SCR-C08, migration `app_releases`, API-10, enforcement `min_app_version` di config (fitur 01) dan handshake (fitur 02).
- Scope/files or area: `console/`
- Dependency: fitur 01 TASK-005, fitur 02 TASK-003
- Acceptance: AC 1.
- Verification: Pest.
- Risk/approval gate: **GATE** migration/schema.

### TASK-003 - Android: Play & APK

- Objective: listing Play (Bahasa Indonesia), Data safety, App access (akun demo + kode join demo), target API 36, closed test ≥ 12 tester ≥ 14 hari; APK langsung untuk perangkat terkelola; verifikasi developer Android (Indonesia) bila berlaku.
- Scope/files or area: Play Console, `clients/android/docs/release.md`
- Dependency: TASK-001
- Acceptance: production track disetujui.
- Verification: status Play Console.
- Risk/approval gate: **GATE** external integration + production deploy.

### TASK-004 - Windows: MSIX & MSI

- Objective: MSIX (App Installer auto-update) + MSI per-machine untuk Intune/GPO, sertifikat code signing OV/EV, submit false-positive ke vendor AV utama bila perlu.
- Scope/files or area: `clients/windows/installer/`
- Dependency: TASK-001
- Acceptance: instal senyap `msiexec /qn` di lab uji; SmartScreen tidak memblokir.
- Verification: uji instal di VM bersih + Intune test tenant.
- Risk/approval gate: **GATE** external integration + production deploy.

### TASK-005 - Apple: App Store & notarization

- Objective: App Store Connect (iOS/iPadOS + macOS), review notes yang menjelaskan assessment mode + akun demo, privacy manifest, notarization untuk distribusi macOS di luar Store.
- Scope/files or area: `clients/apple/docs/release.md`
- Dependency: TASK-001, fitur 06 TASK-006
- Acceptance: app disetujui review.
- Verification: status App Store Connect.
- Risk/approval gate: **GATE** external integration + production deploy.

### TASK-006 - Crash reporting & observability

- Objective: pilih & pasang crash reporting (mis. Sentry untuk semua klien atau Crashlytics Android) dengan scrubbing data; metrik console (ingest rate, latency join, error rate) + alert.
- Scope/files or area: semua klien, console.
- Dependency: TASK-001
- Acceptance: AC 3.
- Verification: crash uji tercatat tanpa PII.
- Risk/approval gate: **GATE** external integration (layanan pihak ketiga, data siswa).

### TASK-007 - Red-team checklist master

- Objective: `protocol/REDTEAM-CHECKLIST.md` — skenario bypass per platform (pindah app, overlay, split-screen, VM, RDP, recording, devtools, URL escape, replay header, config palsu, klien termodifikasi, jam diubah, offline abuse, keluar paksa) dengan hasil yang diharapkan; dipakai fitur 04–06 dan setiap kandidat rilis.
- Scope/files or area: `protocol/`
- Dependency: fitur 02 TASK-001
- Acceptance: dipakai minimal sekali per platform sebelum pilot.
- Verification: laporan red-team terlampir di rilis.
- Risk/approval gate: —

### TASK-008 - Pilot sekolah & GA

- Objective: pilot 1 sekolah (≥ 2 kelas × 2 platform, satu periode ujian), audit `laravel-security-audit` console + perubahan SIMS, panduan admin/pengawas/siswa, retro → GA.
- Scope/files or area: `docs/` panduan, laporan pilot.
- Dependency: TASK-002–007, fitur 07
- Acceptance: AC 5.
- Verification: laporan pilot + metrik.
- Risk/approval gate: **GATE** production deploy (penggunaan di ujian nyata).

## Definition of done

- Semua klien tersedia di kanal resmi; versi minimum dapat dinaikkan/diturunkan dari console.
- Pilot lulus metrik PRD §4; audit keamanan tanpa temuan kritis terbuka.
