<?php

return [
    // URL Exam Console, WAJIB https:// (http hanya untuk localhost/127.0.0.1), mis. https://console.btive.id
    'console_url' => env('EXAM_BROWSER_CONSOLE_URL'),

    // API key integrasi (ebk_...) — buat di Console → Integrasi. Rahasia server; jangan commit.
    'api_key' => env('EXAM_BROWSER_API_KEY'),

    // enforce = tolak request yang gagal verifikasi; report = catat saja lalu teruskan (masa transisi).
    'mode' => env('EXAM_BROWSER_MODE', 'report'),

    // Cache hasil introspeksi (detik, maks 300 — SPEC §11.3). Disimpan terenkripsi dengan APP_KEY.
    // Pendek (default 30) supaya kunci/keluarkan dari pengawas di Console cepat berlaku di server.
    'cache_ttl' => (int) env('EXAM_BROWSER_CACHE_TTL', 30),

    // Fail-open terbatas saat Console tidak terjangkau (detik sejak verifikasi online terakhir yang lolos).
    // Bukti offline tetap mewajibkan X-EB-Request-Hash sah dari kunci sesi yang sama dan tidak melewati
    // expires_at sesi. Default 1800 (30 menit) supaya gangguan jaringan sekolah tidak menghentikan simpan jawaban.
    'offline_grace_seconds' => (int) env('EXAM_BROWSER_OFFLINE_GRACE', 1800),

    'timeout' => (float) env('EXAM_BROWSER_TIMEOUT', 3.0),

    // Tautan unduh/petunjuk yang ditampilkan saat siswa membuka dari browser biasa.
    'download_url' => env('EXAM_BROWSER_DOWNLOAD_URL'),
];
