# 06 Klien macOS & iOS/iPadOS

## Outcome

MacBook, iPad, dan iPhone siswa menjalankan ujian dengan penguncian resmi Apple (Automatic Assessment Configuration), menggantikan prototipe `apps/sims-kios-macos` dengan produk umum yang memakai protokol yang sama.

## Scope

### Included

- Swift Package `ExamBrowserCore` (JCS, Ed25519 via CryptoKit `Curve25519.Signing`, HMAC, TOTP, telemetri queue, client API)
- App macOS (AppKit/SwiftUI, macOS 12+) dan app iOS/iPadOS (SwiftUI, iOS 16+) di `clients/apple/`
- `AEAssessmentSession` untuk mengunci (memblokir app lain, screenshot/recording, dikte, AutoFill, Spotlight, notifikasi sesuai OS)
- WKWebView hardening + request signing + `WKScriptMessageHandlerWithReply` untuk `ExamBrowser.sign/finish/info`
- Deteksi tambahan: `UIScreen.isCaptured`/scene capture state, AirPlay/display eksternal, jailbreak heuristik (iOS), Guided Access aktif (info)
- Attestation: App Attest (`DCAppAttestService`) di iOS/iPadOS; macOS memakai device key Secure Enclave terdaftar (mewarisi pola `DeviceKey.swift` prototipe)
- Pengajuan entitlement AAC ke Apple

### Excluded

- Profil MDM otomatis (Autonomous Single App Mode) — panduan saja (L3 Later)
- Mac Catalyst (dua target native lebih terkontrol)

## Linked artifacts

- Requirements: REQ-003–005, REQ-007–009
- Flows/screens: FLOW-01–03; SCR-K01–K06
- Entities/APIs: API-01–06, ENT-06
- Existing: `apps/sims-kios-macos/main.swift`, `DeviceKey.swift`, `Assessment.entitlements`

## User story / scenario

- Sebagai sekolah dengan iPad, saya ingin ujian berjalan dalam mode assessment resmi Apple, agar siswa tidak bisa membuka Safari atau ChatGPT.
- Sebagai pengguna prototipe SIMS Kios macOS, saya ingin pindah ke Exam Browser tanpa mengatur ulang perangkat dari nol.

## Acceptance criteria

- Given entitlement AAC tersedia, when siswa menekan "Mulai & kunci perangkat", then `AEAssessmentSession` aktif sebelum WebView memuat soal; gagal aktif → soal tidak dimuat.
- Given sesi assessment aktif, when siswa mencoba app switcher/Spotlight/screenshot, then diblokir OS.
- Given `AEAssessmentSession` terinterupsi (delegate `didInterrupt`/`failedToBegin`), when terjadi, then event berat + halaman ujian dikunci sampai sesi dipulihkan.
- Given iPhone/iPad jailbreak terdeteksi atau App Attest gagal, when handshake, then level ≤ L1.
- Given perekaman layar/AirPlay mirror aktif, when ujian berjalan, then event `screen_capture` dan konten disembunyikan.
- Given Mac dari prototipe dengan device key terdaftar, when migrasi, then kunci dapat diregistrasi ulang ke console tanpa menghapus Keychain manual (panduan migrasi).

## Edge cases and states

- Konfigurasi AAC per platform berbeda (`AEAssessmentConfiguration` di macOS mendukung `allowedApplications`); profil dapat mengizinkan Kalkulator/kamus bawaan bila diizinkan.
- iPad Stage Manager / Split View: dicegah oleh assessment mode; dicatat bila terdeteksi pada OS lama.
- Keyboard Bluetooth & Universal Control di iPad/Mac.
- Pembaruan OS mengubah perilaku AAC → masuk matriks uji per rilis OS mayor.

## Tasks

### TASK-001 - `ExamBrowserCore` Swift Package

- Objective: implementasi protokol + test vectors; client API (join, sessions, events, end); queue telemetri (SQLite/FileStore).
- Scope/files or area: `clients/apple/Packages/ExamBrowserCore`
- Dependency: fitur 02 TASK-001
- Acceptance: lulus test vectors di macOS & iOS Simulator.
- Verification: `swift test` + `xcodebuild test` di CI.
- Risk/approval gate: —

### TASK-002 - App macOS

- Objective: migrasi `apps/sims-kios-macos` menjadi target `ExamBrowser-macOS`: join flow, `AEAssessmentSession`, WKWebView, device key Secure Enclave, jalur keluar sah, resume.
- Scope/files or area: `clients/apple/macOS/`
- Dependency: TASK-001
- Acceptance: AC 1–3, 6.
- Verification: uji manual Intel + Apple Silicon, macOS 12/14/15/26.
- Risk/approval gate: —

### TASK-003 - App iOS/iPadOS

- Objective: target `ExamBrowser-iOS`: join (kode/QR/Universal Link), `AEAssessmentSession`, WKWebView, capture/AirPlay detection, jalur keluar, resume.
- Scope/files or area: `clients/apple/iOS/`
- Dependency: TASK-001
- Acceptance: AC 1–3, 5.
- Verification: uji di iPhone + iPad fisik (Simulator tidak mendukung AAC penuh).
- Risk/approval gate: —

### TASK-004 - WKWebView hardening & request signing

- Objective: navigasi main-frame via `URLRequest` dengan header X-EB; `decidePolicyFor` whitelist; `WKScriptMessageHandlerWithReply` untuk JS API, dibatasi origin whitelist; matikan link preview, data detector, inspectable (`isInspectable = false`), download, popup.
- Scope/files or area: modul web bersama macOS/iOS
- Dependency: TASK-002, TASK-003
- Acceptance: SDK SIMS staging menerima header; navigasi luar whitelist diblokir.
- Verification: E2E dengan contoh SDK.
- Risk/approval gate: —

### TASK-005 - App Attest & device key

> Catatan 2026-11-17: driver server `app_attest` di console saat ini **fail closed** (selalu tidak terverifikasi). Task ini wajib mengimplementasikan verifikasi server penuh (CBOR attestation object, rantai x5c ke Apple App Attestation Root CA, nonce extension `1.2.840.113635.100.8.2`, rpIdHash, counter, assertion) dengan fixture dari perangkat nyata — lihat `console/app/Protocol/Attestation/AppAttestVerifier.php`.

- Objective: App Attest (attest key + assertion per handshake) di iOS/iPadOS; Secure Enclave device key di macOS; registrasi perangkat terkelola ke console.
- Scope/files or area: `ExamBrowserCore/Attestation`, fitur 02 TASK-003 driver `app_attest`.
- Dependency: TASK-001, fitur 02 TASK-003
- Acceptance: AC 4.
- Verification: perangkat nyata, environment development & production App Attest.
- Risk/approval gate: **GATE** external integration (Apple App Attest) + auth.

### TASK-006 - Entitlement AAC & akun Apple

- Objective: ajukan `com.apple.developer.automatic-assessment-configuration` untuk bundle macOS & iOS produk umum; siapkan App ID, provisioning, review notes; dokumentasikan fallback (Guided Access / MDM Single App Mode) bila ditolak.
- Scope/files or area: Apple Developer account, `clients/apple/docs/entitlement.md`
- Dependency: — (mulai sejak Phase 1 karena lead time)
- Acceptance: entitlement disetujui, atau keputusan fallback tercatat (OQ2).
- Verification: profil provisioning berisi entitlement.
- Risk/approval gate: **GATE** external integration (pengajuan ke Apple atas nama B'tive).

### TASK-007 - Red-team Apple

- Objective: checklist bypass di macOS & iPadOS/iOS (Universal Control, Stage Manager, Siri, Control Center, Screen Mirroring, Shortcuts, external keyboard shortcuts).
- Scope/files or area: `clients/apple/docs/redteam-<versi>.md`
- Dependency: TASK-002–005
- Acceptance: ≥ 95% skenario terdeteksi/terblokir.
- Verification: laporan.
- Risk/approval gate: —

## Definition of done

- Acceptance criteria pass di perangkat fisik.
- `apps/sims-kios-macos` ditandai deprecated dengan panduan migrasi.
- Privacy manifest (`PrivacyInfo.xcprivacy`) lengkap (input fitur 08).
