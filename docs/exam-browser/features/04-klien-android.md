# 04 Klien Android

## Outcome

Siswa dengan HP/tablet Android dapat mengikuti ujian di aplikasi yang mengunci layar ke halaman ujian (L1), dengan attestation Play Integrity untuk L2 dan mode Device Owner untuk perangkat sekolah (L3, dokumentasi di MVP).

## Scope

### Included

- App Kotlin (`clients/android/`), minSdk 26, targetSdk 36, Compose untuk shell UI, Android WebView untuk konten
- Join: ketik kode / scan QR (CameraX + ML Kit) / deep link `btive-eb://join/{code}` dan App Links https
- Verifikasi config Ed25519 (public key ter-pin di app + `GET /api/v1/keys`)
- Lockdown: `startLockTask()` (screen pinning; tanpa konfirmasi bila Device Owner), immersive sticky, `FLAG_SECURE`, `setHideOverlayWindows(true)` (API 31+), blok multi-window/PiP, back/recents, keep screen on
- Deteksi: kehilangan fokus jendela, multi-window, accessibility service aktif di luar whitelist, root/Magisk heuristik, emulator, perekaman layar (`addScreenRecordingCallback`, API 35+), layar eksternal/cast, mode developer + USB debugging
- Handshake + Play Integrity (Standard request, nonce dari protokol)
- Request signing: header untuk navigasi main-frame, JS bridge `ExamBrowser.sign/finish/info`
- Telemetri: Room queue + WorkManager/foreground flush, heartbeat
- Keluar: finish token, kode unlock TOTP, quit password darurat; resume setelah crash/kill

### Excluded

- AccessibilityService (risiko penolakan Play)
- Provisioning Device Owner otomatis (Later; MVP: panduan QR provisioning manual)

## Linked artifacts

- Requirements: REQ-003–005, REQ-007–009
- Flows/screens: FLOW-01–03; SCR-K01–K06
- Entities/APIs: API-01–06

## User story / scenario

- Sebagai siswa, saya ingin scan QR dan langsung masuk ujian, agar tidak perlu mengetik URL.
- Sebagai pengawas, saya ingin tahu jika siswa membuka split-screen atau aplikasi chatbot mengambang di atas ujian.
- Sebagai siswa, saya ingin aplikasi yang tertutup karena baterai/crash melanjutkan ujian saya tanpa login ulang.

## Acceptance criteria

- Given config dengan tanda tangan salah, when join, then app menolak dengan pesan "Konfigurasi ujian tidak sah" dan tidak membuka WebView.
- Given lockdown aktif, when siswa menekan Home/Recents, then tetap di app (screen pinning) dan event `lock_task_escape_attempt` tercatat bila pinning dilepas.
- Given app chatbot dengan overlay "bubble", when lockdown aktif di API 31+, then overlay disembunyikan; di API < 31 `filterTouchesWhenObscured` + event `obscured_touch`.
- Given URL di luar whitelist, when diklik/redirect, then navigasi diblokir dan event `blocked_navigation`.
- Given screenshot/screen recording, when dicoba, then hasil hitam (`FLAG_SECURE`); di API 35+ perekaman terdeteksi → event `screen_recording`.
- Given app di-kill paksa saat ujian, when dibuka lagi, then langsung masuk SCR-K03 di URL terakhir setelah resume sukses.
- Given perangkat root / emulator, when handshake, then level efektif ≤ L1 dan profil L2 menolak dengan alasan.
- Given offline 10 menit, when online lagi, then semua event terkirim tanpa duplikat.

## Edge cases and states

- Panggilan telepon masuk: lock task tetap; event `incoming_call` (severity info) bila jendela kehilangan fokus.
- Vendor dengan Lock Task bermasalah (beberapa skin OEM): daftar perangkat diuji di matriks; fallback deteksi fokus + pelanggaran.
- WebView usang: cek versi `WebView` minimum, minta update via Play.
- Keyboard pihak ketiga dengan fitur AI/clipboard: profil dapat mewajibkan keyboard bawaan (deteksi IME aktif → peringatan).
- Baterai < 15% di pra-ujian → peringatan.
- Rotasi layar & tablet besar: layout responsif.

## Tasks

### TASK-001 - Scaffold app & CI

- Objective: proyek Gradle Kotlin DSL, modul `core-protocol` (pure Kotlin: JCS, Ed25519 via libsodium/Tink, HMAC, TOTP), `app`; CI build + unit test + lint.
- Scope/files or area: `clients/android/`
- Dependency: fitur 02 TASK-001
- Acceptance: `core-protocol` lulus seluruh test vectors.
- Verification: `./gradlew test lint` di CI.
- Risk/approval gate: —

### TASK-002 - Join flow & verifikasi config

- Objective: SCR-K01/K02, scan QR, deep link + App Links, fetch API-01, verifikasi signature/kadaluarsa/versi minimum/platform.
- Scope/files or area: `app/join/*`
- Dependency: TASK-001, fitur 01 TASK-006
- Acceptance: AC 1.
- Verification: instrumented test + uji manual dengan console staging.
- Risk/approval gate: —

### TASK-003 - Lockdown core

- Objective: `LockdownController`: lock task, immersive, FLAG_SECURE, hide overlay, blok multi-window/PiP, back handling, keep screen on, kunci orientasi opsional.
- Scope/files or area: `app/lockdown/*`, `MainActivity`, manifest (`android:resizeableActivity="false"`, `lockTaskMode`).
- Dependency: TASK-002
- Acceptance: AC 2, 3, 5.
- Verification: uji manual matriks perangkat (min. Samsung, Xiaomi, Oppo/Realme, Vivo, Pixel; Android 8, 11, 13, 15, 16).
- Risk/approval gate: —

### TASK-004 - Detektor & telemetri

- Objective: detektor (fokus, multi-window, accessibility, root, emulator, recording, display eksternal, dev options/ADB, IME), Room queue, WorkManager flush, heartbeat sesuai `heartbeat_interval`, eksekusi perintah remote dari response.
- Scope/files or area: `app/integrity/*`, `app/telemetry/*`
- Dependency: TASK-003, fitur 02 TASK-004
- Acceptance: AC 8; setiap detektor punya event type terdaftar di SPEC.
- Verification: unit test detektor + uji manual.
- Risk/approval gate: —

### TASK-005 - Handshake & Play Integrity

- Objective: `POST /sessions` dengan Play Integrity Standard API token (nonce sesuai SPEC), simpan sesi di EncryptedSharedPreferences/Keystore, resume.
- Scope/files or area: `app/session/*`, Google Cloud project + Play Integrity API.
- Dependency: TASK-002, fitur 02 TASK-003
- Acceptance: AC 6, 7.
- Verification: uji di perangkat nyata (lolos & tidak lolos integrity).
- Risk/approval gate: **GATE** external integration (Google Play Integrity, akun Cloud).

### TASK-006 - WebView hardening & request signing

- Objective: whitelist di `shouldOverrideUrlLoading` + `shouldInterceptRequest` (blokir subresource di luar aturan), `loadUrl(url, headers)` untuk main-frame, JS interface `ExamBrowser` (sign, finish, info, calculator bila diizinkan) hanya diekspos ke origin whitelist, matikan file access/content access/debugging/download/geolocation/kamera kecuali diizinkan profil, blok `window.open` ke luar whitelist.
- Scope/files or area: `app/web/*`
- Dependency: TASK-005
- Acceptance: AC 4; SDK target memverifikasi header dari app (end-to-end dengan SIMS staging).
- Verification: instrumented test + E2E dengan contoh SDK.
- Risk/approval gate: —

### TASK-007 - Keluar, unlock, resume

- Objective: SCR-K04–K06; jalur keluar sah (finish token/target finish, TOTP, quit password Argon2id), `stopLockTask()`, API-06; resume setelah crash.
- Scope/files or area: `app/exit/*`
- Dependency: TASK-005, fitur 02 TASK-008
- Acceptance: tidak ada cara keluar selain jalur sah (diuji red-team); AC 6.
- Verification: uji manual + test unit TOTP.
- Risk/approval gate: **GATE** auth (jalur keluar).

### TASK-008 - Panduan L3 Device Owner

- Objective: dokumentasi provisioning QR Device Owner untuk tablet sekolah, allowlist lock task, kebijakan `DevicePolicyManager` yang dipakai; deteksi Device Owner → klaim L3 (diverifikasi via device key terdaftar).
- Scope/files or area: `clients/android/docs/L3-device-owner.md`, kode deteksi kecil.
- Dependency: TASK-003
- Acceptance: satu tablet uji berhasil masuk lock task tanpa konfirmasi siswa.
- Verification: uji manual.
- Risk/approval gate: —

### TASK-009 - Red-team Android

- Objective: jalankan checklist bypass (fitur 08 TASK-007) di matriks perangkat; perbaiki temuan kritis.
- Scope/files or area: laporan `clients/android/docs/redteam-<versi>.md`
- Dependency: TASK-003–007
- Acceptance: ≥ 95% skenario terdeteksi/terblokir.
- Verification: laporan red-team.
- Risk/approval gate: —

## Definition of done

- Acceptance criteria pass di matriks perangkat.
- Test vectors lulus di CI; tidak ada `WebView.setWebContentsDebuggingEnabled(true)` di release.
- Data safety declaration draf selesai (input fitur 08).
