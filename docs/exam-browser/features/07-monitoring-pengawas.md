# 07 Monitoring Pengawas

## Outcome

Pengawas memantau seluruh peserta secara live dari satu layar, bisa menolong siswa yang terkunci dalam hitungan detik, dan setiap tindakan sensitif tercatat untuk akuntabilitas.

## Scope

### Included

- Dashboard live per sesi ujian (Laravel Reverb + Livewire)
- Detail peserta: timeline event, perangkat, level, versi app
- Kode unlock TOTP per peserta (tercatat setiap kali dilihat)
- Perintah remote: kunci, buka kunci, keluarkan (kick), tambah waktu, kirim pesan
- Rekap & ekspor pelanggaran (xlsx)
- Notifikasi pelanggaran berat
- Manajemen perangkat terkelola (registrasi device key, status L3)

### Excluded

- Live screen view / webcam peserta (Later, non-goal MVP)

## Linked artifacts

- Requirements: REQ-010, REQ-011, REQ-014, REQ-016; NFR-002, NFR-007
- Flows/screens: FLOW-02; SCR-C04, SCR-C05, SCR-C07
- Entities/APIs: ENT-05, ENT-06, ENT-07, ENT-08; API-05

## User story / scenario

- Sebagai pengawas, saya ingin melihat siapa yang offline atau melanggar dengan warna, agar bisa langsung mendatangi siswa.
- Sebagai pengawas, saya ingin memberi kode unlock ke satu siswa yang perangkatnya bermasalah, tanpa membagikan password global.
- Sebagai kepala sekolah/admin, saya ingin melihat siapa yang membuka kode unlock dan kapan.

## Acceptance criteria

- Given 1.000 peserta aktif, when ada event baru, then dashboard memperbarui kartu peserta ≤ 5 detik.
- Given peserta tidak mengirim heartbeat > 2× interval, when dashboard dibuka, then status "offline" (abu).
- Given pengawas yang tidak ditugaskan ke sesi, when membuka dashboard sesi itu, then 403 (Policy).
- Given pengawas menekan "Lihat kode unlock", when kode tampil, then activity log mencatat pengawas, peserta, sesi, waktu.
- Given perintah "kunci" dikirim, when heartbeat berikutnya, then klien menampilkan SCR-K04 dan mengirim ack; dashboard menampilkan status terkunci.
- Given ekspor pelanggaran, when diunduh, then hanya data lembaga & sesi yang berwenang, dan aksi tercatat.

## Edge cases and states

- Peserta join dua kali dari perangkat berbeda → tampil peringatan "multi-perangkat", pengawas memilih sesi yang sah (kick yang lain).
- Perintah ke klien offline → status "tertunda" sampai ack; kedaluwarsa setelah sesi ditutup.
- Reverb down → fallback polling 10 detik.
- Sesi ditutup saat peserta masih aktif → perintah `finish_forced` (klien mengarahkan ke halaman submit target dulu bila profil mengizinkan).

## Tasks

### TASK-001 - Dashboard live

- Objective: SCR-C04 grid peserta (status warna, level, versi, pelanggaran terakhir), filter & pencarian, broadcasting Reverb channel privat per exam_session, fallback polling.
- Scope/files or area: `console/app/Livewire/Monitor/*`, `routes/channels.php`, Reverb config.
- Dependency: fitur 02 TASK-004
- Acceptance: AC 1–3.
- Verification: Pest (channel authorization) + load test 1.000 peserta simulasi.
- Risk/approval gate: **GATE** auth (otorisasi channel broadcast).

> Catatan 2026-11-21 (keputusan FL): diimplementasikan dengan **polling 5 detik** (`/sesi-ujian/{id}/pantau/data?since=`), Reverb ditunda. Otorisasi = policy `pantau` + tabel penugasan `exam_session_pengawas` (bukan channel broadcast). Saat Reverb ditambahkan, channel privat per sesi memakai policy yang sama.

### TASK-002 - Detail peserta

- Objective: SCR-C05 timeline event (severity, durasi), info perangkat, riwayat level, riwayat perintah.
- Scope/files or area: `Livewire/Monitor/PesertaDetail`
- Dependency: TASK-001
- Acceptance: timeline urut `occurred_at`, menampilkan event offline yang terkirim belakangan.
- Verification: Pest + review visual.
- Risk/approval gate: —

### TASK-003 - Kode unlock per peserta

- Objective: tombol "Lihat kode" → hitung TOTP dari `unlock_seed`, tampil dengan countdown, log setiap tampilan; permission `ujian.unlock.view`.
- Scope/files or area: `UnlockCodeController`, Policy, activity log.
- Dependency: TASK-002, fitur 02 TASK-002
- Acceptance: AC 4; kode cocok dengan test vectors TOTP.
- Verification: Pest.
- Risk/approval gate: **GATE** auth.

### TASK-004 - Perintah remote

- Objective: kirim lock/unlock/kick/extend/message/finish_forced → `remote_commands` dalam `DB::transaction()`; dikirim via response API-05; ack dari klien; UI status perintah.
- Scope/files or area: `RemoteCommandController`, update SPEC event & command types.
- Dependency: TASK-001, fitur 02 TASK-004
- Acceptance: AC 5; semua perintah tercatat di activity log.
- Verification: Pest + E2E dengan satu klien native.
- Risk/approval gate: **GATE** auth.

### TASK-005 - Rekap & ekspor pelanggaran

- Objective: rekap per sesi (jumlah per jenis, per peserta), ekspor xlsx (Laravel Excel), Policy + log.
- Scope/files or area: `Exports/ViolationExport`, halaman rekap.
- Dependency: TASK-002
- Acceptance: AC 6.
- Verification: Pest.
- Risk/approval gate: —

### TASK-006 - Notifikasi pelanggaran berat

- Objective: toast + suara di dashboard untuk severity `high`; opsi notifikasi email/WA ke pengawas (Later bila butuh integrasi eksternal).
- Scope/files or area: dashboard JS.
- Dependency: TASK-001
- Acceptance: event `high` memunculkan notifikasi ≤ 5 detik.
- Verification: manual.
- Risk/approval gate: —

### TASK-007 - Perangkat terkelola

- Objective: SCR-C07 — daftar perangkat, registrasi device key (kode registrasi sekali pakai), cabut perangkat, tandai `managed` (L3).
- Scope/files or area: `DeviceController`, Livewire UI.
- Dependency: fitur 02 TASK-002
- Acceptance: perangkat dicabut → handshake berikutnya turun level; hanya admin lembaga.
- Verification: Pest.
- Risk/approval gate: **GATE** auth.

## Definition of done

- Acceptance criteria pass; load test 1.000 peserta lulus NFR-007.
- Semua aksi sensitif tercatat dan dapat difilter admin lembaga.
- Panduan pengawas (1 halaman) tersedia.
