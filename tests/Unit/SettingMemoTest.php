<?php

namespace Tests\Unit;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Perbaikan performa nyata: dashboard admin sempat memuat ~67 query per render, salah satu
 * penyumbang terbesarnya adalah Setting::get() yang lama — memo-nya per-KEY, jadi tiap key
 * BEDA yang pertama kali dibaca = 1 query terpisah (aplikasi ini pakai ~50 key setting berbeda,
 * layout saja mengecek 22 modul). Fix: memo() sekarang memuat SEMUA baris settings sekaligus
 * (1 query) begitu get() pertama dipanggil, bukan lazy per-key. Test ini mengunci PERILAKU
 * (bukan cuma soal query count) supaya refactor lain di masa depan tak diam-diam merusaknya.
 */
class SettingMemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_mengembalikan_nilai_tersimpan(): void
    {
        Setting::set('nama_sekolah', 'SMP Contoh');

        $this->assertSame('SMP Contoh', Setting::get('nama_sekolah'));
    }

    public function test_get_mengembalikan_default_utk_key_yang_tak_ada(): void
    {
        $this->assertSame('fallback', Setting::get('key_yang_tak_pernah_diset', 'fallback'));
        $this->assertNull(Setting::get('key_yang_tak_pernah_diset'));
    }

    public function test_membaca_key_tersimpan_sekaligus_dan_mengingat_key_yang_tidak_ada(): void
    {
        DB::table('settings')->insert([
            ['key' => 'a', 'value' => '1'],
            ['key' => 'b', 'value' => '2'],
            ['key' => 'c', 'value' => '3'],
        ]);

        DB::enableQueryLog();
        Setting::get('a');
        Setting::get('b');
        Setting::get('c');
        $selectQueries = array_values(array_filter(DB::getQueryLog(), fn ($query) => str_contains($query['query'], 'settings')));
        $this->assertCount(1, $selectQueries, 'Key tersimpan harus dimuat sekaligus.');

        DB::flushQueryLog();
        $this->assertSame('default', Setting::get('d_tak_ada', 'default'));
        $this->assertNull(Setting::get('d_tak_ada'));
        $fallbackQueries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(1, $fallbackQueries, 'Key yang belum ada di memo diperiksa sekali.');
        $this->assertSame(['d_tak_ada'], $fallbackQueries[0]['bindings']);
    }

    /** Mengulang baca key YANG SAMA berkali-kali tetap harus 0 query tambahan (memo bekerja). */
    public function test_membaca_key_yang_sama_berulang_tidak_query_lagi(): void
    {
        Setting::set('x', 'nilai');
        Setting::get('x'); // pemanasan memo

        DB::enableQueryLog();
        for ($i = 0; $i < 5; $i++) {
            Setting::get('x');
        }
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $log);
    }

    public function test_set_memperbarui_memo_langsung_tanpa_perlu_baca_ulang_dari_db(): void
    {
        Setting::set('flag', 'lama');
        $this->assertSame('lama', Setting::get('flag'));

        Setting::set('flag', 'baru');
        $this->assertSame('baru', Setting::get('flag'));
    }

    public function test_hapus_setting_membuat_get_balik_ke_default(): void
    {
        $s = Setting::create(['key' => 'sementara', 'value' => 'ada']);
        $this->assertSame('ada', Setting::get('sementara'));

        $s->delete();

        $this->assertSame('default', Setting::get('sementara', 'default'));
    }
}
