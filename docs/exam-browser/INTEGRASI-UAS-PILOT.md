# Integrasi ujian SIMS dengan Android pilot

Status: implementasi lokal untuk pilot terawasi pada tablet sekolah Device Owner. Belum disetujui untuk UAS produksi. APK default tidak memiliki URL Console atau pin kunci publik sehingga join sengaja ditolak sampai build dikonfigurasi operator.

## Alur yang tersedia

Guru membuat ujian pada modul Ujian SIMS (jenis harian, PTS/PAS/UAS mengikuti hak dan alur existing). Console menyediakan profil dan sesi dengan start URL halaman gate ujian SIMS. Siswa memasukkan kode Console di Android, klien memverifikasi config, mengaktifkan Lock Task, lalu handshake. Login SIMS, token kelas, pengerjaan, penyimpanan dan penilaian tetap memakai modul SIMS.

Android mengirim header navigasi bertanda tangan dan menyediakan ExamBrowser.sign/finish/info hanya pada main frame origin target. SIMS memverifikasi header melalui SDK dan mencocokkan ID sesi Console. Attempt terikat ke SID perangkat; pasangan ujian-attempt berbeda ditolak. Setelah submit berhasil, token finish dapat melepaskan Lock Task. Kode pengawas tersedia sebagai jalur keluar terawasi.

## Menyiapkan staging

1. Sediakan dua origin HTTPS: SIMS staging dan Exam Console staging. Pakai database/data ujian uji, bukan jawaban siswa produksi.
2. Pasang dependency SIMS dari composer.lock. SDK btive/exam-browser bersumber dari ../btive-exam-browser/sdk/php; salinan vendor harus berupa junction/direktori valid, bukan file placeholder link dari arsip Mac.
3. Pastikan migration Exam Browser yang sudah ada tersedia pada database staging. Sesi ini tidak menjalankan migration atau deploy produksi.
4. Operator Console membuat integrasi server dengan scope sessions.read dan sessions.finish. Simpan API key melalui pengelolaan environment server. API key tidak masuk APK, URL, Git, atau log.
5. Atur EXAM_BROWSER_CONSOLE_URL pada server SIMS, EXAM_BROWSER_MODE=report, serta EXAM_BROWSER_DOWNLOAD_URL bila unduhan internal sudah tersedia. Konfigurasi dari SDK dimuat otomatis; tidak perlu menggandakan file config agar provider bekerja.
6. Buat profil Console untuk android, minimum L1, opsi kode pengawas aktif dan target_finish aktif. Allowlist hanya host/path SIMS yang dibutuhkan, termasuk login, gate, soal, jawaban dan aset. Jangan gunakan wildcard semua host.
7. Buat/buka sesi Console. Start URL harus gate ujian SIMS yang tepat, bukan halaman admin. Salin UUID sesi Console ke pengaturan Exam Browser ujian SIMS; UUID wajib ketika level diaktifkan.
8. Ambil kid dan kunci publik Ed25519 dari operator melalui saluran tepercaya. Build memakai tools/Build-SimsPilot.ps1 pada folder clients/android dengan ConsoleUrl dan PinnedKeys. Parameter tersebut publik; jangan memasukkan seed atau API key.
9. Provisioning Device Owner dilakukan hanya pada tablet uji khusus. APK debug beridentitas id.btive.exam.demo. Belum ada APK/AAB rilis produksi atau distribusi Play.
10. Jalankan report-only di staging minimal satu minggu sesuai DoD, lalu evaluasi enforce hanya setelah skenario berikut lulus. Mode report tidak memblokir browser biasa.

## Uji wajib di tablet fisik

- Login sebagai siswa uji, masuk dengan token kelas, kerjakan beberapa jenis soal, dan pastikan jawaban server sesuai.
- Saat Lock Task aktif, kirim pesan pengawas dari Console; pesan harus tampil di aplikasi tanpa membuka aplikasi lain atau memperlonggar navigasi.
- Uji kunci/buka kunci, putus-sambung jaringan, pengiriman ulang telemetri dan ack perintah tanpa pengulangan efek extend.
- Uji URL/redirect/subresource di luar allowlist, pop-up, file/content URI, SSL salah, screenshot, Home/Recents, split-screen, dan overlay.
- Uji submit sukses sampai token finish diterima. Pastikan soal dan cadangan lokal yang belum tersinkron tidak dibuang hanya karena perintah finish datang.
- Uji crash/kill dan resume, termasuk kehilangan respons resume, restart saat offline, serta kode pengawas setelah kegagalan pemulihan.
- Uji jawaban pada detik terakhir, jaringan lambat melewati deadline, cron auto-submit, dan rekonsiliasi. Deadline existing tetap keras; tidak ada tambahan waktu tersembunyi.
- Uji dua akun siswa bergantian pada tablet; verifikasi logout/cookie, cadangan jawaban lokal, dan privasi. Cadangan yang belum diakui server dipertahankan untuk rekonsiliasi, sehingga pembersihan setelah rekonsiliasi wajib dipastikan sebelum pemakaian bergantian.

## Batas dan pekerjaan tersisa

- L1 pilot hanya pada tablet Device Owner. L2/L3 memerlukan attestation/registrasi kunci perangkat; belum diimplementasikan dalam slice ini.
- API resume merotasi session_key. Sejak review 2026-11-21, Console menerima kunci lama (masa tenggang maks. 30 menit) sampai klien memakai kunci baru, sehingga respons resume yang hilang bisa dipulihkan. Klien tidak otomatis mengganti SID karena attempt SIMS sudah terikat perangkat.
- Deadline: server menerima sinkron antrean selama 45 detik setelah `batas_waktu_pada` (tanpa menambah waktu menjawab di UI). Auto-submit waktu habis selalu mengumpulkan; jawaban yang belum diakui server tetap tersimpan di perangkat untuk rekonsiliasi pengawas.
- finish_forced pada pilot mengunci tampilan dan meminta pengawas memeriksa pengumpulan; tidak memalsukan submit sukses atau langsung membuang jawaban. Hindari mengandalkannya sebagai pengumpulan otomatis sampai E2E selesai.
- Password darurat Argon2id, pemindaian QR, detektor lengkap, dan pipeline rilis bukan hasil slice ini. Profil pilot wajib mengaktifkan kode pengawas.
- Push FCM memiliki perubahan source tersendiri di workspace. Tidak ada bukti push pada perangkat nyata; jangan menyatakan push berfungsi berdasarkan build.
- Tidak ada perangkat adb terhubung dan URL staging belum diberikan. Belum ada verifikasi login sampai selesai pada server sebenarnya.

## SOP pengawas saat tablet terkunci

Tablet mengunci tampilan soal bila fokus aplikasi hilang lebih dari 1 detik, Lock Task terlepas, atau pengawas mengirim perintah kunci.

1. **Console terhubung:** buka kunci dari dashboard pantau Exam Console (perintah *unlock*). Siswa melanjutkan sesi dan attempt yang sama; tidak perlu tindakan di SIMS.
2. **Console tidak terhubung (offline):** kode pengawas 6 digit **mengakhiri** sesi Exam Browser, bukan sekadar membuka kunci. Lakukan hanya bila jaringan tidak bisa dipulihkan dalam beberapa menit:
   1. Masukkan kode pengawas di tablet; tablet keluar dari Lock Task.
   2. Siswa membuka lagi Exam Browser dengan kode ujian yang sama (sesi baru).
   3. SIMS (mode `enforce`) mengunci attempt dengan alasan `eb_perangkat_lain` karena sesi berganti. Pengawas membuka kunci attempt di SIMS (Hasil/Monitor ujian → buka kunci). Membuka kunci melepas ikatan sesi lama; request berikutnya mengikat sesi baru.
   4. Jawaban yang sudah tersimpan di server dan cadangan lokal tablet tidak hilang.
3. **Tablet bersama masih menyimpan sesi siswa lain:** aplikasi menolak kode ujian baru dan meminta kode pengawas untuk sesi lama. Masukkan kode pengawas sesi lama, lalu siswa baru memasukkan kode ujiannya.
4. **"Proses keluar ujian sebelumnya belum terkirim":** sambungkan jaringan lalu ketuk *Coba lagi*. Bila Console sudah menutup sesi itu, aplikasi menghapus data sesi lama secara otomatis.
5. Catat setiap pembukaan kunci (siapa, jam, alasan) di berita acara ujian.

## Bukti pemeriksaan lokal

- SIMS: 305 tests, 1408 assertions, OK untuk filter Ujian|ModeUjian; 23 tes integrasi Exam Browser termasuk regresi tambahan.
- Node: 3 regresi antrean jawaban lulus (ACK lama, submit saat sync gagal, serialisasi flush).
- Android final: 9 metode tes core (6 protokol + 3 SessionAccess), 0 failures/errors. :core-protocol:test :app:lintDebug :app:assembleDebug BUILD SUCCESSFUL pada kondisi workspace terbaru. Tanda tangan APK debug terverifikasi dengan apksigner. Warning lint tetap ada; uji perangkat dan push belum dijalankan.
- Konfigurasi PHP pemeriksaan memakai Sodium dan OPENSSL_CONF bawaan PHP; APP_KEY pengujian dibuat sementara di proses, tanpa membaca rahasia aplikasi.
