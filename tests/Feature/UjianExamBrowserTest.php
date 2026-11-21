<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianKelas;
use App\Models\UjianSoal;
use App\Models\UjianSoalOpsi;
use App\Models\User;
use Btive\ExamBrowser\Base64Url;
use Btive\ExamBrowser\FinishToken;
use Btive\ExamBrowser\RequestHash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * B'tive Exam Browser per ujian (docs/exam-browser fitur 02 TASK-007) — SDK btive/exam-browser.
 */
class UjianExamBrowserTest extends TestCase
{
    use RefreshDatabase;

    private const SID = '6f1f2c1a-1111-4aaa-8bbb-0123456789ab';

    private const SID_LAIN = '9c9c9c9c-3333-4eee-afff-0123456789ab';

    private const CONSOLE_EXAM = '7a2b3c4d-2222-4ccc-9ddd-abcdefabcdef';

    private Ujian $ujian;

    private UjianAttempt $attempt;

    private User $siswaUser;

    private User $admin;

    private string $key;

    /** @var array<string, mixed> */
    private array $console = [];

    private int $consoleStatus = 200;

    private int $finishStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'exam-browser.console_url' => 'https://console.test',
            'exam-browser.api_key' => 'ebk_abcd1234_'.str_repeat('X', 32),
            'exam-browser.mode' => 'enforce',
            'cache.default' => 'array',
        ]);
        $this->key = random_bytes(32);
        $this->sesiConsole();
        // Satu fake untuk seluruh test (Http::fake menumpuk stub); status diatur lewat properti.
        Http::fake([
            'console.test/api/v1/integrations/sessions/*/finish' => fn () => Http::response(['v' => 1, 'status' => 'finished', 'command_id' => 'c'], $this->finishStatus),
            'console.test/api/v1/integrations/sessions/*' => fn () => Http::response($this->console, $this->consoleStatus),
        ]);

        $pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75]);
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $guruUser = User::create(['username' => 'guru_eb', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $guru = Guru::create(['id_login' => $guruUser->uuid, 'nama' => 'Guru EB', 'nik' => '7777777777', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $pelajaran->uuid, 'id_kelas' => $kelas->uuid]);
        $this->admin = User::create(['username' => 'admin_eb', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);

        $this->ujian = Ujian::create([
            'id_pelajaran' => $pelajaran->uuid, 'created_by' => $guruUser->uuid,
            'judul' => 'PTS EB', 'jenis' => 'pts', 'target_nilai' => 'pts', 'durasi_menit' => 60, 'status' => 'published',
        ]);
        $soal = UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'mcq', 'teks_soal' => '2+2=?', 'poin' => 10, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => '4', 'is_benar' => true, 'urutan' => 1]);
        $ujianKelas = UjianKelas::create(['id_ujian' => $this->ujian->uuid, 'id_kelas' => $kelas->uuid, 'token_masuk' => 'TOKENEB']);

        $this->siswaUser = User::create(['username' => 'siswa_eb', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $this->siswaUser->uuid, 'id_kelas' => $kelas->uuid, 'nama' => 'Siswa EB', 'nis' => '7001', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        // Mulai attempt SEBELUM ujian diwajibkan Exam Browser (POST mulai memang tidak dijaga).
        $this->actingAs($this->siswaUser)->post(route('ujian.siswa.start', $this->ujian), ['token' => 'TOKENEB'])->assertRedirect();
        $this->attempt = UjianAttempt::where('id_siswa', $this->siswaUser->uuid)->firstOrFail();
    }

    private function sesiConsole(array $o = []): void
    {
        $this->console = array_replace([
            'session_id' => self::SID, 'exam_session_id' => self::CONSOLE_EXAM, 'lembaga_id' => 'l', 'status' => 'active',
            'effective_level' => 2, 'session_key' => Base64Url::encode($this->key), 'peserta_ref' => 'siswa_eb',
            'platform' => 'android', 'app_version' => '1.0', 'started_at' => now()->timestamp, 'expires_at' => now()->addHour()->timestamp,
        ], $o);
        Cache::flush();
    }

    private function wajib(?int $level, ?string $sesiConsole = null): void
    {
        $this->ujian->forceFill(['exam_browser_level' => $level, 'exam_browser_session_id' => $sesiConsole])->save();
    }

    /** Header X-EB-* untuk URL lengkap (SPEC §11.1). */
    private function eb(string $url, string $sid = self::SID): array
    {
        $ts = (string) time();

        return ['X-EB-Session' => $sid, 'X-EB-Ts' => $ts, 'X-EB-Request-Hash' => RequestHash::compute($this->key, $ts, $url)];
    }

    public function test_ujian_tanpa_syarat_tidak_berubah_dan_tidak_memanggil_console(): void
    {
        $this->actingAs($this->siswaUser)->get(route('ujian.siswa.kerjakan', [$this->ujian, $this->attempt]))->assertOk();
        $this->actingAs($this->siswaUser)->postJson(route('ujian.siswa.submit', [$this->ujian, $this->attempt]))
            ->assertOk()->assertJsonMissingPath('exam_browser_finish_token');

        Http::assertNothingSent();
    }

    public function test_browser_biasa_ditolak_saat_wajib_dan_enforce(): void
    {
        $this->wajib(1);

        $this->actingAs($this->siswaUser)->get(route('ujian.siswa.kerjakan', [$this->ujian, $this->attempt]))
            ->assertForbidden()->assertSee("Buka dengan B'tive Exam Browser", false);
        $this->actingAs($this->siswaUser)->postJson(route('ujian.siswa.sinkronBatch', [$this->ujian, $this->attempt]), ['jawaban' => []])
            ->assertForbidden()->assertJsonPath('code', 'eb_missing');
        $this->actingAs($this->siswaUser)->postJson(route('ujian.siswa.submit', [$this->ujian, $this->attempt]))->assertForbidden();

        $this->assertSame(UjianAttempt::STATUS_IN_PROGRESS, $this->attempt->fresh()->status);
    }

    public function test_mode_report_tetap_meneruskan_siswa(): void
    {
        config(['exam-browser.mode' => 'report']);
        $this->wajib(2);

        $this->actingAs($this->siswaUser)->get(route('ujian.siswa.kerjakan', [$this->ujian, $this->attempt]))->assertOk();
    }

    public function test_exam_browser_sah_bisa_mengerjakan_dan_submit_memanggil_finish(): void
    {
        $this->wajib(2, self::CONSOLE_EXAM);

        $kerjakan = route('ujian.siswa.kerjakan', [$this->ujian, $this->attempt]);
        $this->actingAs($this->siswaUser)->get($kerjakan, $this->eb($kerjakan))->assertOk();

        $kumpul = route('ujian.siswa.submit', [$this->ujian, $this->attempt]);
        $res = $this->actingAs($this->siswaUser)->postJson($kumpul, [], $this->eb($kumpul))->assertOk();

        // Dikumpulkan (langsung dinilai bila Mode Ujian nonaktif) — yang penting tidak lagi in_progress.
        $this->assertNotSame(UjianAttempt::STATUS_IN_PROGRESS, $this->attempt->fresh()->status);
        $this->assertSame('ok', FinishToken::verify($this->key, self::SID, (string) $res->json('exam_browser_finish_token'), time()));
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/api/v1/integrations/sessions/'.self::SID.'/finish'));
    }

    public function test_level_kurang_atau_sesi_console_lain_ditolak(): void
    {
        $kerjakan = route('ujian.siswa.kerjakan', [$this->ujian, $this->attempt]);

        $this->wajib(3);
        $this->actingAs($this->siswaUser)->getJson($kerjakan, $this->eb($kerjakan))->assertForbidden()->assertJsonPath('code', 'eb_level_insufficient');

        $this->wajib(2, '00000000-0000-4000-8000-000000000000');
        $this->actingAs($this->siswaUser)->getJson($kerjakan, $this->eb($kerjakan))->assertForbidden()->assertJsonPath('code', 'eb_wrong_exam');
    }

    public function test_finish_gagal_tidak_menggagalkan_pengumpulan(): void
    {
        $this->finishStatus = 500;
        $this->wajib(1);

        $kumpul = route('ujian.siswa.submit', [$this->ujian, $this->attempt]);
        $this->actingAs($this->siswaUser)->postJson($kumpul, [], $this->eb($kumpul))->assertOk();

        // Dikumpulkan (langsung dinilai bila Mode Ujian nonaktif) — yang penting tidak lagi in_progress.
        $this->assertNotSame(UjianAttempt::STATUS_IN_PROGRESS, $this->attempt->fresh()->status);
    }

    public function test_console_tidak_terjangkau_enforce_503(): void
    {
        $this->consoleStatus = 502;
        $this->wajib(1);
        $kerjakan = route('ujian.siswa.kerjakan', [$this->ujian, $this->attempt]);

        $this->actingAs($this->siswaUser)->getJson($kerjakan, $this->eb($kerjakan))->assertStatus(503)->assertJsonPath('code', 'eb_console_unavailable');
    }

    public function test_admin_mengatur_syarat_di_pengaturan_ujian(): void
    {
        $data = [
            'judul' => 'PTS EB', 'jenis' => 'pts', 'durasi_menit' => 60, 'mode_skor' => 'rata_rata',
            'exam_browser_level' => 2, 'exam_browser_session_id' => self::CONSOLE_EXAM,
        ];

        $this->actingAs($this->admin)->post(route('ujian.update', $this->ujian), $data)->assertRedirect();
        $this->assertSame(2, $this->ujian->fresh()->exam_browser_level);
        $this->assertSame(self::CONSOLE_EXAM, $this->ujian->fresh()->exam_browser_session_id);

        $this->actingAs($this->admin)->post(route('ujian.update', $this->ujian), ['exam_browser_level' => ''] + $data)->assertRedirect();
        $this->assertNull($this->ujian->fresh()->exam_browser_level);
        $this->assertNull($this->ujian->fresh()->exam_browser_session_id);

        $this->actingAs($this->admin)->post(route('ujian.update', $this->ujian), ['exam_browser_level' => 9] + $data)->assertSessionHasErrors('exam_browser_level');
    }

    public function test_guru_biasa_tidak_bisa_mengubah_syarat(): void
    {
        $guru = User::where('username', 'guru_eb')->firstOrFail();
        $this->actingAs($guru)->post(route('ujian.update', $this->ujian), [
            'judul' => 'PTS EB', 'jenis' => 'harian', 'durasi_menit' => 60, 'mode_skor' => 'rata_rata', 'exam_browser_level' => 1,
        ]);

        $this->assertNull($this->ujian->fresh()->exam_browser_level);
    }

    public function test_console_mati_penyimpanan_attempt_yang_sudah_terverifikasi_tetap_jalan(): void
    {
        $this->wajib(1);
        $kerjakan = route('ujian.siswa.kerjakan', [$this->ujian, $this->attempt]);
        $sinkron = route('ujian.siswa.sinkronBatch', [$this->ujian, $this->attempt]);

        // Belum pernah lolos di sesi ini → tetap ditolak 503.
        $this->consoleStatus = 502;
        $this->actingAs($this->siswaUser)->postJson($sinkron, ['jawaban' => []], $this->eb($sinkron))->assertStatus(503);

        // Lolos dulu saat Console hidup, lalu Console mati.
        $this->consoleStatus = 200;
        $this->actingAs($this->siswaUser)->get($kerjakan, $this->eb($kerjakan))->assertOk();
        $this->consoleStatus = 502;
        Cache::flush();

        $soal = UjianSoal::where('id_ujian', $this->ujian->uuid)->firstOrFail();
        $opsi = UjianSoalOpsi::where('id_soal', $soal->uuid)->firstOrFail();
        $jawaban = ['jawaban' => [['id_soal' => $soal->uuid, 'id_opsi_dipilih' => $opsi->uuid]]];

        // Tanda tangan untuk URL lain tetap ditolak ketika Console tidak tersedia.
        $this->actingAs($this->siswaUser)->postJson($sinkron, $jawaban, $this->eb($kerjakan))->assertStatus(503);
        $this->actingAs($this->siswaUser)->postJson($sinkron, $jawaban, $this->eb($sinkron))->assertOk();
        $this->assertDatabaseHas('ujian_jawaban', ['id_attempt' => $this->attempt->uuid, 'id_soal' => $soal->uuid, 'id_opsi_dipilih' => $opsi->uuid]);
        // Review 2026-11-21 #6: halaman kerjakan dari perangkat terikat ikut fail-open (muat ulang saat Console putus).
        $this->actingAs($this->siswaUser)->get($kerjakan, $this->eb($kerjakan))->assertOk();
        // Tanpa header, bukti offline dibuang. Pengumpulan menunggu verifikasi ulang.
        $this->actingAs($this->siswaUser)->postJson($sinkron, ['jawaban' => []])->assertForbidden();
        $kumpul = route('ujian.siswa.submit', [$this->ujian, $this->attempt]);
        $this->actingAs($this->siswaUser)->postJson($kumpul, [], $this->eb($kumpul))->assertStatus(503);

        $this->consoleStatus = 200;
        $this->actingAs($this->siswaUser)->get($kerjakan, $this->eb($kerjakan))->assertOk();
        $this->consoleStatus = 502;
        Cache::flush();

        // Review 2026-11-21 #6: finish token tetap diberikan dari bukti offline supaya tablet terbuka seketika.
        $res = $this->actingAs($this->siswaUser)->postJson($kumpul, [], $this->eb($kumpul))->assertOk();
        $this->assertSame('ok', FinishToken::verify($this->key, self::SID, (string) $res->json('exam_browser_finish_token'), now()->timestamp));
        $this->assertNotSame(UjianAttempt::STATUS_IN_PROGRESS, $this->attempt->fresh()->status);
    }

    public function test_post_mulai_saat_enforce_butuh_gate_terverifikasi(): void
    {
        $this->attempt->delete();
        $this->wajib(2);
        $mulai = route('ujian.siswa.start', $this->ujian);

        $this->actingAs($this->siswaUser)->post($mulai, ['token' => 'TOKENEB'])->assertRedirect(route('ujian.siswa.gate', $this->ujian));
        $this->assertSame(0, UjianAttempt::where('id_siswa', $this->siswaUser->uuid)->count());

        $gate = route('ujian.siswa.gate', $this->ujian);
        $this->actingAs($this->siswaUser)->get($gate, $this->eb($gate))->assertOk();
        $this->actingAs($this->siswaUser)->post($mulai, ['token' => 'TOKENEB'])->assertRedirect();
        $this->assertSame(1, UjianAttempt::where('id_siswa', $this->siswaUser->uuid)->count());
    }

    public function test_post_mulai_penanda_gate_kedaluwarsa_15_menit(): void
    {
        $this->attempt->delete();
        $this->wajib(1);
        $gate = route('ujian.siswa.gate', $this->ujian);
        $this->actingAs($this->siswaUser)->get($gate, $this->eb($gate))->assertOk();

        $this->travel(16)->minutes();
        $this->actingAs($this->siswaUser)->post(route('ujian.siswa.start', $this->ujian), ['token' => 'TOKENEB'])
            ->assertRedirect(route('ujian.siswa.gate', $this->ujian));
    }

    public function test_post_mulai_mode_report_tidak_dibatasi(): void
    {
        config(['exam-browser.mode' => 'report']);
        $this->attempt->delete();
        $this->wajib(2);

        $this->actingAs($this->siswaUser)->post(route('ujian.siswa.start', $this->ujian), ['token' => 'TOKENEB'])
            ->assertRedirect(route('ujian.siswa.kerjakan', [$this->ujian, UjianAttempt::where('id_siswa', $this->siswaUser->uuid)->firstOrFail()]));
    }

    public function test_m1_attempt_terikat_ke_sesi_pertama_perangkat_lain_dikunci_sampai_pengawas_membuka(): void
    {
        $this->wajib(1);
        $kerjakan = route('ujian.siswa.kerjakan', [$this->ujian, $this->attempt]);
        $sinkron = route('ujian.siswa.sinkronBatch', [$this->ujian, $this->attempt]);

        $this->actingAs($this->siswaUser)->get($kerjakan, $this->eb($kerjakan))->assertOk();
        $this->assertSame(self::SID, $this->attempt->fresh()->eb_client_session_id);

        // Sesi yang sama (mis. setelah resume, SPEC §8.5) tetap lolos.
        Cache::flush();
        $this->actingAs($this->siswaUser)->get($kerjakan, $this->eb($kerjakan))->assertOk();

        // Perangkat/sesi Exam Browser lain yang sah → ditolak + attempt dikunci + tercatat.
        $this->sesiConsole(['session_id' => self::SID_LAIN]);
        $this->actingAs($this->siswaUser)->postJson($sinkron, ['jawaban' => []], $this->eb($sinkron, self::SID_LAIN))
            ->assertForbidden()->assertJsonPath('code', 'eb_wrong_session')->assertJsonPath('dikunci', true);
        $this->actingAs($this->siswaUser)->get($kerjakan, $this->eb($kerjakan, self::SID_LAIN))
            ->assertForbidden()->assertSee('perangkat Exam Browser lain');
        $this->assertTrue($this->attempt->fresh()->isLocked());
        $this->assertSame(self::SID, $this->attempt->fresh()->eb_client_session_id);
        $this->assertDatabaseCount('ujian_pelanggaran', 1);
        $this->assertDatabaseHas('ujian_pelanggaran', ['id_attempt' => $this->attempt->uuid, 'tipe' => 'eb_perangkat_lain']);

        // Pengawas membuka kunci → ikatan lepas → perangkat baru mengikat ulang.
        $this->actingAs($this->admin)->post(route('ujian.monitor.unlock', [$this->ujian, $this->attempt]))->assertRedirect();
        $this->assertNull($this->attempt->fresh()->eb_client_session_id);
        $this->actingAs($this->siswaUser)->get($kerjakan, $this->eb($kerjakan, self::SID_LAIN))->assertOk();
        $this->assertSame(self::SID_LAIN, $this->attempt->fresh()->eb_client_session_id);
        $this->assertFalse($this->attempt->fresh()->isLocked());
    }

    public function test_m1_mode_report_sesi_lain_hanya_dicatat(): void
    {
        config(['exam-browser.mode' => 'report']);
        $this->wajib(1);
        $kerjakan = route('ujian.siswa.kerjakan', [$this->ujian, $this->attempt]);
        $this->actingAs($this->siswaUser)->get($kerjakan, $this->eb($kerjakan))->assertOk();

        $this->sesiConsole(['session_id' => self::SID_LAIN]);
        $this->actingAs($this->siswaUser)->get($kerjakan, $this->eb($kerjakan, self::SID_LAIN))->assertOk();
        $this->assertFalse($this->attempt->fresh()->isLocked());
        $this->assertSame(self::SID, $this->attempt->fresh()->eb_client_session_id);
    }

    public function test_m1_siswa_lain_tidak_bisa_mengikat_atau_mengunci_attempt_orang_lain(): void
    {
        $this->wajib(1);
        $lain = User::create(['username' => 'siswa_lain_eb', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        $kerjakan = route('ujian.siswa.kerjakan', [$this->ujian, $this->attempt]);

        // Sebelum terikat: request siswa lain tidak boleh mengikat attempt ini ke sesinya.
        $this->sesiConsole(['session_id' => self::SID_LAIN]);
        $this->actingAs($lain)->get($kerjakan, $this->eb($kerjakan, self::SID_LAIN))->assertForbidden();
        $this->assertNull($this->attempt->fresh()->eb_client_session_id);

        // Setelah terikat ke pemilik: request siswa lain tidak boleh mengunci attempt pemilik.
        $this->sesiConsole();
        $this->actingAs($this->siswaUser)->get($kerjakan, $this->eb($kerjakan))->assertOk();
        $this->sesiConsole(['session_id' => self::SID_LAIN]);
        $this->actingAs($lain)->get($kerjakan, $this->eb($kerjakan, self::SID_LAIN))->assertForbidden();
        $this->assertFalse($this->attempt->fresh()->isLocked());
        $this->assertSame(self::SID, $this->attempt->fresh()->eb_client_session_id);
    }

    private function hitungFinish(): int
    {
        return count(Http::recorded(fn ($req) => str_ends_with($req->url(), '/finish')));
    }

    public function test_task008_waktu_habis_attempt_terikat_memanggil_finish_console(): void
    {
        $this->wajib(1);
        $kerjakan = route('ujian.siswa.kerjakan', [$this->ujian, $this->attempt]);
        $this->actingAs($this->siswaUser)->get($kerjakan, $this->eb($kerjakan))->assertOk();

        $this->travelTo($this->attempt->fresh()->batas_waktu_pada->copy()->addMinute());
        $this->artisan('ujian:auto-submit')->assertSuccessful();

        $this->assertNotSame(UjianAttempt::STATUS_IN_PROGRESS, $this->attempt->fresh()->status);
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/api/v1/integrations/sessions/'.self::SID.'/finish'));
    }

    public function test_task008_attempt_tanpa_ikatan_tidak_memanggil_console(): void
    {
        $this->travelTo($this->attempt->fresh()->batas_waktu_pada->copy()->addMinute());
        $this->artisan('ujian:auto-submit')->assertSuccessful();

        $this->assertNotSame(UjianAttempt::STATUS_IN_PROGRESS, $this->attempt->fresh()->status);
        $this->assertSame(0, $this->hitungFinish());
    }

    public function test_task008_console_mati_penilaian_tetap_jalan_dan_tidak_diulang_per_attempt(): void
    {
        $this->attempt->forceFill(['eb_client_session_id' => self::SID])->save();
        $lain = User::create(['username' => 'siswa_eb2', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        $kedua = UjianAttempt::create([
            'id_ujian_kelas' => $this->attempt->id_ujian_kelas, 'id_siswa' => $lain->uuid, 'urutan_soal' => [],
            'mulai_pada' => now(), 'batas_waktu_pada' => $this->attempt->batas_waktu_pada, 'status' => UjianAttempt::STATUS_IN_PROGRESS,
        ]);
        $kedua->forceFill(['eb_client_session_id' => self::SID_LAIN])->save();
        $this->finishStatus = 502;

        $this->travelTo($this->attempt->fresh()->batas_waktu_pada->copy()->addMinute());
        $this->artisan('ujian:auto-submit')->assertSuccessful();

        $this->assertNotSame(UjianAttempt::STATUS_IN_PROGRESS, $this->attempt->fresh()->status);
        $this->assertNotSame(UjianAttempt::STATUS_IN_PROGRESS, $kedua->fresh()->status);
        $this->assertSame(1, $this->hitungFinish());
    }

    public function test_task008_guru_menyelesaikan_paksa_juga_memanggil_finish(): void
    {
        $this->attempt->forceFill(['eb_client_session_id' => self::SID])->save();

        $this->actingAs($this->admin)->post(route('ujian.hasil.paksaSelesai', [$this->ujian, $this->attempt]))->assertRedirect();

        $this->assertNotSame(UjianAttempt::STATUS_IN_PROGRESS, $this->attempt->fresh()->status);
        $this->assertSame(1, $this->hitungFinish());
    }
    public function test_attempt_tidak_bisa_dipasangkan_dengan_ujian_lain_tanpa_exam_browser(): void
    {
        $this->wajib(1);
        $lain = $this->ujian->replicate();
        $lain->exam_browser_level = null;
        $lain->exam_browser_session_id = null;
        $lain->save();
        $url = route('ujian.siswa.submit', [$lain, $this->attempt]);
        $this->actingAs($this->siswaUser)->postJson($url)->assertNotFound();
        $this->assertSame(UjianAttempt::STATUS_IN_PROGRESS, $this->attempt->fresh()->status);
    }

    public function test_console_offline_tetap_menolak_hash_palsu_dan_sid_perangkat_lain(): void
    {
        $this->wajib(1);
        $page = route('ujian.siswa.kerjakan', [$this->ujian, $this->attempt]);
        $this->actingAs($this->siswaUser)->get($page, $this->eb($page))->assertOk();
        $this->consoleStatus = 503;
        Cache::flush();
        $url = route('ujian.siswa.sinkronBatch', [$this->ujian, $this->attempt]);
        $headers = $this->eb($url);
        $headers['X-EB-Request-Hash'] = str_repeat('0', 64);
        $this->postJson($url, ['jawaban' => []], $headers)->assertStatus(503);
        $this->postJson($url, ['jawaban' => []], $this->eb($url, self::SID_LAIN))->assertStatus(503);
    }

    /** Header X-EB-* memakai jam Carbon (dipakai jalur offline setelah travel()). */
    private function ebSekarang(string $url): array
    {
        $ts = (string) now()->timestamp;

        return ['X-EB-Session' => self::SID, 'X-EB-Ts' => $ts, 'X-EB-Request-Hash' => RequestHash::compute($this->key, $ts, $url)];
    }

    private function consoleMatiSetelahKerjakan(): string
    {
        $this->wajib(1);
        $page = route('ujian.siswa.kerjakan', [$this->ujian, $this->attempt]);
        $this->actingAs($this->siswaUser)->get($page, $this->eb($page))->assertOk();
        $this->consoleStatus = 503;
        Cache::flush();

        return $page;
    }

    public function test_bukti_offline_masih_sah_setelah_lima_menit_dalam_jendela_konfigurasi(): void
    {
        $this->consoleMatiSetelahKerjakan();
        $this->travel(10)->minutes();
        $url = route('ujian.siswa.sinkronBatch', [$this->ujian, $this->attempt]);
        $soal = UjianSoal::where('id_ujian', $this->ujian->uuid)->firstOrFail();
        $this->postJson($url, ['jawaban' => [['id_soal' => $soal->uuid]]], $this->ebSekarang($url))->assertOk();
    }

    public function test_bukti_offline_kedaluwarsa_setelah_jendela_konfigurasi(): void
    {
        config(['exam-browser.offline_grace_seconds' => 600]);
        $this->consoleMatiSetelahKerjakan();
        $this->travel(601)->seconds();
        $url = route('ujian.siswa.sinkronBatch', [$this->ujian, $this->attempt]);
        $this->postJson($url, ['jawaban' => []], $this->ebSekarang($url))->assertStatus(503);
    }

    public function test_console_mati_halaman_kerjakan_tetap_bisa_dimuat_ulang_dari_perangkat_terikat(): void
    {
        $page = $this->consoleMatiSetelahKerjakan();
        $this->get($page, $this->ebSekarang($page))->assertOk()->assertSee('nativeVerified: true', false);
        // Perangkat lain / hash palsu tetap ditolak.
        $palsu = $this->ebSekarang($page);
        $palsu['X-EB-Request-Hash'] = str_repeat('0', 64);
        $this->get($page, $palsu)->assertStatus(503);
    }

    public function test_console_mati_submit_tetap_memberi_finish_token_dari_bukti_offline(): void
    {
        $this->consoleMatiSetelahKerjakan();
        $url = route('ujian.siswa.submit', [$this->ujian, $this->attempt]);
        $res = $this->postJson($url, [], $this->ebSekarang($url))->assertOk();

        $this->assertNotSame(UjianAttempt::STATUS_IN_PROGRESS, $this->attempt->fresh()->status);
        $this->assertSame('ok', FinishToken::verify($this->key, self::SID, (string) $res->json('exam_browser_finish_token'), now()->timestamp));
    }

    public function test_jawaban_dalam_masa_tenggang_deadline_diterima_lalu_ditolak_setelahnya(): void
    {
        $url = route('ujian.siswa.sinkronBatch', [$this->ujian, $this->attempt]);
        $soal = UjianSoal::where('id_ujian', $this->ujian->uuid)->firstOrFail();
        $opsi = UjianSoalOpsi::where('id_soal', $soal->uuid)->firstOrFail();
        $batas = $this->attempt->fresh()->batas_waktu_pada;

        $this->travelTo($batas->copy()->addSeconds(20));
        $this->actingAs($this->siswaUser)->postJson($url, ['jawaban' => [['id_soal' => $soal->uuid, 'id_opsi_dipilih' => $opsi->uuid]]])
            ->assertOk()->assertJsonPath('count', 1);

        // Cron tidak memfinalisasi selama masa tenggang.
        $this->artisan('ujian:auto-submit')->assertSuccessful();
        $this->assertSame(UjianAttempt::STATUS_IN_PROGRESS, $this->attempt->fresh()->status);

        $this->travelTo($batas->copy()->addSeconds(UjianAttempt::MASA_TENGGANG_SINKRON_DETIK + 1));
        $this->actingAs($this->siswaUser)->postJson($url, ['jawaban' => []])->assertStatus(422);
        $this->artisan('ujian:auto-submit')->assertSuccessful();
        $this->assertNotSame(UjianAttempt::STATUS_IN_PROGRESS, $this->attempt->fresh()->status);
    }
}
