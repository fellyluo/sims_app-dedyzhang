# 05 Klien Windows

## Outcome

Lab komputer dan laptop siswa Windows 10/11 menjalankan ujian di desktop terkunci yang tidak bisa ditinggalkan, tidak bisa direkam, dan mendeteksi VM/remote desktop — tanpa merusak konfigurasi PC setelah ujian.

## Scope

### Included

- App .NET 8 WPF + WebView2 (`clients/windows/`), x64 + ARM64
- Desktop terpisah (`CreateDesktop`/`SwitchDesktop`), jendela fullscreen topmost, enforcement satu monitor
- Low-level keyboard hook (blok Win, Alt+Tab, Alt+Esc, Ctrl+Esc, Alt+F4, PrintScreen, shortcut Copilot/Win+H/Win+C)
- Policy HKCU sementara (DisableTaskMgr, DisableLockWorkstation, DisableChangePassword, NoLogoff, sembunyikan switch user) dengan **journal + restore terjamin**
- Blok capture `SetWindowDisplayAffinity(WDA_EXCLUDEFROMCAPTURE)`, kosongkan clipboard saat mulai/selesai
- Deteksi: VM (CPUID hypervisor bit, SMBIOS, driver/MAC vendor), RDP/remote session (`SM_REMOTESESSION`), proses terlarang (remote desktop, recorder, overlay, chatbot desktop) dari daftar yang dikelola console, debugger
- Handshake + device key (CNG, TPM Platform Crypto Provider bila tersedia) untuk L2; L3 untuk PC terdaftar lembaga
- Request signing via `WebResourceRequested` (tambah header) + JS API `ExamBrowser`
- Telemetri, keluar sah, resume

### Excluded

- Driver kernel / service level SYSTEM untuk blok Ctrl+Alt+Del (tidak dilakukan; SAS dimitigasi lewat policy + deteksi pergantian desktop)
- Windows 7/8.1

## Linked artifacts

- Requirements: REQ-003–005, REQ-007–009; NFR-010
- Flows/screens: FLOW-01–03; SCR-K01–K06
- Entities/APIs: API-01–06, ENT-06

## User story / scenario

- Sebagai admin lab, saya ingin memasang aplikasi lewat MSI/Intune, agar 40 PC siap tanpa instalasi manual.
- Sebagai pengawas, saya ingin tahu jika siswa menjalankan ujian di dalam VM atau lewat AnyDesk.
- Sebagai teknisi, saya ingin PC tetap normal setelah aplikasi crash di tengah ujian.

## Acceptance criteria

- Given lockdown aktif, when siswa menekan Alt+Tab/Win/Alt+F4, then tidak ada efek.
- Given Ctrl+Alt+Del ditekan, when layar keamanan Windows tampil, then Task Manager/Lock/Switch user dinonaktifkan dan kembali ke ujian tercatat sebagai event `secure_attention`.
- Given dua monitor tersambung, when pra-ujian, then mulai diblokir sampai monitor kedua dilepas (atau profil mengizinkan duplicate).
- Given OBS/Snipping Tool/Teams share screen, when merekam, then jendela ujian tampil hitam/kosong.
- Given berjalan di Hyper-V/VMware/VirtualBox atau sesi RDP, when handshake, then level ≤ L1 dan event berat; profil dapat memblokir penuh.
- Given proses terlarang berjalan, when pra-ujian, then siswa diminta menutupnya; muncul saat ujian → event + opsi auto-kill sesuai profil.
- Given app di-kill lewat cara apa pun, when PC di-restart atau app dibuka lagi, then semua policy registry dan desktop pulih seperti sebelum ujian.

## Edge cases and states

- Akun siswa non-admin (umum di lab): semua fitur harus jalan tanpa elevasi; fitur yang butuh admin dinonaktifkan dengan aman.
- WebView2 runtime tidak ada: installer menyertakan bootstrapper; mode lab offline memakai fixed-version runtime.
- Windows S mode / Smart App Control: distribusi MSIX bertanda tangan.
- Antivirus menandai keyboard hook: tanda tangan EV + submit false positive ke vendor.
- Layar sentuh & keyboard virtual (tablet Windows): tetap dapat mengetik di halaman ujian.

## Tasks

### TASK-001 - Scaffold app & core protokol

- Objective: solusi .NET 8: `ExamBrowser.Core` (JCS, Ed25519 via NSec/libsodium, HMAC, TOTP), `ExamBrowser.App` (WPF), test project; CI build x64/ARM64.
- Scope/files or area: `clients/windows/`
- Dependency: fitur 02 TASK-001
- Acceptance: Core lulus test vectors.
- Verification: `dotnet test` di CI.
- Risk/approval gate: —

### TASK-002 - Join flow & verifikasi config

- Objective: SCR-K01/K02, kode/QR (webcam opsional), protokol URI `btive-eb://`, verifikasi config, checklist pra-ujian (monitor, proses, baterai, jaringan).
- Scope/files or area: `App/Join/*`
- Dependency: TASK-001
- Acceptance: config tidak sah ditolak; checklist berjalan.
- Verification: unit + manual.
- Risk/approval gate: —

### TASK-003 - Kiosk desktop & jendela

- Objective: buat desktop terpisah, jalankan WebView2 di desktop itu, fullscreen topmost, enforcement satu monitor, pantau pergantian desktop/sesi (`WTSRegisterSessionNotification`).
- Scope/files or area: `App/Kiosk/*` (P/Invoke user32/wtsapi32)
- Dependency: TASK-002
- Acceptance: AC 1, 3.
- Verification: uji manual Windows 10 22H2 & 11 24H2+.
- Risk/approval gate: —

### TASK-004 - Keyboard hook & policy sistem dengan restore terjamin

- Objective: `WH_KEYBOARD_LL`, policy HKCU sementara dengan journal file (sebelum ubah → tulis journal; saat start → jika journal ada, restore dulu), restore di exit normal, `AppDomain.UnhandledException`, dan tool `ExamBrowser.Restore.exe`.
- Scope/files or area: `App/SystemLock/*`
- Dependency: TASK-003
- Acceptance: AC 2, 7 dengan fault injection (kill -9, BSOD simulasi via power-off VM uji).
- Verification: test otomatis restore + uji manual.
- Risk/approval gate: **GATE** perubahan sistem pada PC siswa (destructive bila gagal restore) — approval FL setelah demo fault injection.

### TASK-005 - Detektor integritas

- Objective: blok capture (`WDA_EXCLUDEFROMCAPTURE`), clipboard clear + blok, deteksi VM/RDP/debugger, daftar proses terlarang dari config (nama + signer), opsi auto-kill sesuai profil.
- Scope/files or area: `App/Integrity/*`
- Dependency: TASK-003
- Acceptance: AC 4, 5, 6.
- Verification: uji di Hyper-V, VMware, VirtualBox, RDP, AnyDesk, OBS.
- Risk/approval gate: —

### TASK-006 - Handshake, device key, request signing

- Objective: handshake API-03 dengan attestation `device_key` (kunci CNG non-exportable, TPM bila ada), registrasi perangkat lab (L3) via console fitur 07 TASK-007; header via `CoreWebView2.WebResourceRequested`; JS API `ExamBrowser` via `AddHostObjectToScript`/`postMessage` hanya untuk origin whitelist; matikan DevTools, context menu, download, autofill, password manager, translate.
- Scope/files or area: `App/Session/*`, `App/Web/*`
- Dependency: TASK-001, fitur 02 TASK-003
- Acceptance: SDK SIMS staging menerima header; level dihitung benar untuk PC terdaftar vs pribadi.
- Verification: E2E dengan contoh SDK.
- Risk/approval gate: **GATE** auth (registrasi perangkat).

### TASK-007 - Telemetri, keluar, resume

- Objective: queue SQLite + flush, heartbeat, perintah remote, jalur keluar sah, resume; auto-start opsional untuk resume setelah reboot (Run key HKCU, dihapus saat selesai).
- Scope/files or area: `App/Telemetry/*`, `App/Exit/*`
- Dependency: TASK-006, fitur 02 TASK-004, TASK-008
- Acceptance: tidak ada jalur keluar tidak sah; resume berfungsi.
- Verification: manual + unit.
- Risk/approval gate: **GATE** auth (jalur keluar).

### TASK-008 - Red-team Windows

- Objective: checklist bypass di Windows 10/11, akun admin vs standar, VM, RDP, multi-monitor, Win+Tab virtual desktop, Narrator, Sticky Keys, Copilot.
- Scope/files or area: `clients/windows/docs/redteam-<versi>.md`
- Dependency: TASK-003–007
- Acceptance: ≥ 95% skenario terdeteksi/terblokir.
- Verification: laporan.
- Risk/approval gate: —

## Definition of done

- Acceptance criteria pass; restore terbukti dengan fault injection.
- Build release bertanda tangan (input fitur 08), DevTools mati di release.
- Panduan teknisi "PC terkunci setelah ujian" tersedia.
