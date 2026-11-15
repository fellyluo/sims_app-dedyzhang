<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Integrasi mesin InsightFace ke halaman scan kiosk: skrip yg dimuat, kolom descriptor yg
 * dikirim ke payload JS, dan faceSim() yg tidak salah pakai kalibrasi Human.js pada embedding
 * InsightFace — semua HARUS berganti mengikuti setting face_engine, tanpa mengganggu jalur
 * Human.js default sama sekali.
 */
class FaceEngineScanIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('kaih_wajib_sebelum_absen', '0');
        $this->admin = User::create(['username' => 'admin_if_scan', 'password' => Hash::make('x'), 'access' => 'superadmin']);
        $this->kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
    }

    public function test_scan_page_memuat_human_js_saat_engine_default(): void
    {
        $res = $this->actingAs($this->admin)->get(route('absensi.scan'))->assertOk();
        $res->assertSee('@vladmandic/human@3.3.6/dist/human.js', false);
        $res->assertDontSee('onnxruntime-web', false);
    }

    public function test_scan_page_memuat_insightface_saat_engine_diaktifkan(): void
    {
        Setting::set('face_engine', 'insightface');

        $res = $this->actingAs($this->admin)->get(route('absensi.scan'))->assertOk();
        $res->assertSee('onnxruntime-web@1.27.0', false);
        $res->assertSee('immich-app/buffalo_s', false);
        $res->assertSee('loadInsightFace', false);
        $res->assertDontSee('@vladmandic/human@3.3.6/dist/human.js', false);
    }

    public function test_model_insightface_dikunci_ke_commit_bukan_main_mengambang(): void
    {
        $source = file_get_contents(resource_path('views/absensi/_insightface_engine.blade.php'));
        $this->assertStringContainsString("resolve/\${IF_COMMIT}", $source);
        $this->assertStringNotContainsString('resolve/main/', $source);
    }

    public function test_payload_scan_pakai_kolom_descriptor_sesuai_engine_aktif(): void
    {
        $siswa = Siswa::create([
            'id_kelas' => $this->kelas->uuid, 'nama' => 'Siswa Dua Engine', 'nis' => '888001', 'jk' => 'L',
            'face_descriptor' => [[0.1, 0.2]],
            'face_descriptor_if' => [[0.9, 0.8, 0.7]],
        ]);

        // Privasi biometrik: descriptor TIDAK dikirim ke browser (pencocokan server-side),
        // tapi has_face tetap mengikuti kolom descriptor milik engine aktif.
        $res1 = $this->actingAs($this->admin)->get(route('absensi.scan'))->assertOk();
        $row1 = collect($res1->viewData('payload'))->firstWhere('uuid', $siswa->uuid);
        $this->assertSame([], $row1['desc']);
        $this->assertTrue($row1['has_face']);

        // Siswa yang hanya punya descriptor Human tidak dianggap terdaftar di engine insightface.
        $humanOnly = Siswa::create([
            'id_kelas' => $this->kelas->uuid, 'nama' => 'Siswa Human Saja', 'nis' => '888002', 'jk' => 'P',
            'face_descriptor' => [[0.1, 0.2]],
        ]);
        Setting::set('face_engine', 'insightface');
        $res2 = $this->actingAs($this->admin)->get(route('absensi.scan'))->assertOk();
        $payload2 = collect($res2->viewData('payload'));
        $row2 = $payload2->firstWhere('uuid', $siswa->uuid);
        $this->assertSame([], $row2['desc']);
        $this->assertTrue($row2['has_face']);
        $this->assertFalse($payload2->firstWhere('uuid', $humanOnly->uuid)['has_face']);
    }

    public function test_facesim_tidak_pakai_kalibrasi_human_saat_engine_insightface(): void
    {
        $source = file_get_contents(resource_path('views/absensi/scan.blade.php'));
        $this->assertStringContainsString("window.SIMS_FACE_ENGINE !== 'insightface'", $source);
        $this->assertStringContainsString("window.SIMS_FACE_ENGINE = this.faceEngine", $source);
    }
}
