<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B'tive Exam Browser (docs/exam-browser, fitur 02 TASK-007): per ujian, admin/pengelola bisa
 * mewajibkan siswa mengerjakan dari aplikasi Exam Browser dengan level keamanan minimum.
 *
 *  - exam_browser_level      : NULL = tidak wajib (perilaku lama); 1–3 = level minimum (SPEC §9)
 *  - exam_browser_session_id : opsional, UUID sesi ujian di Exam Console — bila diisi, sesi klien
 *                              Exam Browser harus untuk sesi ujian itu (cegah kode join ujian lain).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ujians', function (Blueprint $table) {
            $table->unsignedTinyInteger('exam_browser_level')->nullable()->after('tampilkan_pembahasan');
            $table->uuid('exam_browser_session_id')->nullable()->after('exam_browser_level');
        });
    }

    public function down(): void
    {
        Schema::table('ujians', function (Blueprint $table) {
            $table->dropColumn(['exam_browser_level', 'exam_browser_session_id']);
        });
    }
};
