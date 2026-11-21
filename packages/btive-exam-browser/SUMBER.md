# Salinan SDK `btive/exam-browser`

Folder ini adalah salinan SDK PHP dari repo **btive-exam-browser** (`sdk/php`). Salinan disimpan di dalam
repo SIMS supaya `composer install` di server produksi tidak bergantung pada folder `../btive-exam-browser`
(review 2026-11-21 #9).

- Sumber kebenaran: `btive-exam-browser/sdk/php`. Jangan mengubah kode di sini secara langsung.
- Setelah SDK diperbarui, jalankan dari folder induk kedua repo:

```bash
sh sims_app-main/packages/sync-exam-browser-sdk.sh
cd sims_app-main && composer update btive/exam-browser
```

Lalu jalankan tes `tests/Feature/UjianExamBrowserTest.php` sebelum commit.
