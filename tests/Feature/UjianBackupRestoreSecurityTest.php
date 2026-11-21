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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Audit 2026-11-17 C1: restore backup ujian sebelumnya bisa dipanggil siapa pun yang login
 * (Model::unguard + tanpa authorize) → siswa bisa mengubah nilainya sendiri.
 */
class UjianBackupRestoreSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Ujian $ujian;

    private UjianAttempt $attempt;

    private User $siswa;

    private User $guru;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75]);
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $this->guru = User::create(['username' => 'guru_rst', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $g = Guru::create(['id_login' => $this->guru->uuid, 'nama' => 'Guru R', 'nik' => '8888888888', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        Ngajar::create(['id_guru' => $g->uuid, 'id_pelajaran' => $pelajaran->uuid, 'id_kelas' => $kelas->uuid]);
        $this->admin = User::create(['username' => 'admin_rst', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);

        $this->ujian = Ujian::create([
            'id_pelajaran' => $pelajaran->uuid, 'created_by' => $this->guru->uuid, 'judul' => 'PTS Asli', 'jenis' => 'pts',
            'target_nilai' => 'pts', 'durasi_menit' => 60, 'status' => 'published', 'exam_browser_level' => 2,
        ]);
        $uk = UjianKelas::create(['id_ujian' => $this->ujian->uuid, 'id_kelas' => $kelas->uuid, 'token_masuk' => 'TOKRST']);
        $this->siswa = User::create(['username' => 'siswa_rst', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $this->siswa->uuid, 'id_kelas' => $kelas->uuid, 'nama' => 'Siswa R', 'nis' => '8001', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        $this->attempt = UjianAttempt::create([
            'id_ujian_kelas' => $uk->uuid, 'id_siswa' => $this->siswa->uuid, 'urutan_soal' => [],
            'mulai_pada' => now(), 'batas_waktu_pada' => now()->addHour(), 'status' => UjianAttempt::STATUS_IN_PROGRESS,
        ]);
    }

    private function fileJahat(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('backup.json', json_encode([
            'uuid' => $this->ujian->uuid, 'judul' => 'DIRETAS', 'exam_browser_level' => null,
            'kelas' => [['uuid' => $this->attempt->id_ujian_kelas, 'id_ujian' => $this->ujian->uuid, 'attempts' => [[
                'uuid' => $this->attempt->uuid, 'skor_objektif' => 100, 'status' => 'dinilai',
            ]]]],
        ]));
    }

    private function utuh(): void
    {
        $this->assertSame('PTS Asli', $this->ujian->fresh()->judul);
        $this->assertSame(2, $this->ujian->fresh()->exam_browser_level);
        $this->assertSame(UjianAttempt::STATUS_IN_PROGRESS, $this->attempt->fresh()->status);
        $this->assertNull($this->attempt->fresh()->skor_objektif);
    }

    public function test_siswa_tidak_bisa_restore(): void
    {
        $this->actingAs($this->siswa)->post(route('ujian.restore'), ['password' => 'rahasia123', 'backup_file' => $this->fileJahat()])->assertForbidden();
        $this->utuh();
    }

    public function test_guru_tidak_bisa_restore_bahkan_ujiannya_sendiri(): void
    {
        $this->actingAs($this->guru)->post(route('ujian.restore'), ['password' => 'rahasia123', 'backup_file' => $this->fileJahat()])->assertForbidden();
        $this->utuh();
    }

    public function test_tombol_restore_tidak_tampil_untuk_guru(): void
    {
        $this->actingAs($this->guru)->get(route('ujian.index'))->assertOk()->assertDontSee('Restore Backup');
    }

    public function test_admin_tetap_bisa_restore(): void
    {
        $this->actingAs($this->admin)->post(route('ujian.restore'), ['password' => 'rahasia123', 'backup_file' => $this->fileJahat()])
            ->assertRedirect(route('ujian.index'));
        $this->assertSame('DIRETAS', $this->ujian->fresh()->judul);
    }

    public function test_file_bukan_json_ditolak(): void
    {
        $gambar = UploadedFile::fake()->image('backup.png');
        $this->actingAs($this->admin)->post(route('ujian.restore'), ['password' => 'rahasia123', 'backup_file' => $gambar])
            ->assertSessionHasErrors('backup_file');
        $this->utuh();
    }
}
