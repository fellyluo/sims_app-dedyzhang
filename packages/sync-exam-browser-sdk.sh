#!/bin/sh
# Salin SDK PHP Exam Browser ke repo SIMS (tanpa vendor/tes). Jalankan dari folder induk kedua repo.
set -eu
SRC="btive-exam-browser/sdk/php"
DST="sims_app-main/packages/btive-exam-browser"
test -f "$SRC/composer.json" || { echo "SDK tidak ditemukan di $SRC" >&2; exit 1; }
rm -rf "$DST/src" "$DST/config" "$DST/resources"
cp -r "$SRC/src" "$SRC/config" "$SRC/resources" "$SRC/composer.json" "$SRC/README.md" "$DST/"
echo "SDK disalin ke $DST"
