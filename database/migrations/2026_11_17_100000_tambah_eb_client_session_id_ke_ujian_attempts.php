<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 2026-11-17 M1 (docs/exam-browser/audit/2026-11-17-task-007-sims.md): ikat attempt ke sesi
 * klien B'tive Exam Browser yang pertama lolos verifikasi. Sesi lain pada attempt yang sama →
 * attempt dikunci (enforce) sampai pengawas membuka kunci (yang sekaligus melepas ikatan ini).
 *
 *  - eb_client_session_id : session_id klien di Exam Console (SPEC §8; tetap sama saat resume §8.5).
 *                           NULL = belum terikat (ujian tanpa Exam Browser, atau baru dibuka kuncinya).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ujian_attempts', function (Blueprint $table) {
            $table->string('eb_client_session_id', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ujian_attempts', function (Blueprint $table) {
            $table->dropColumn('eb_client_session_id');
        });
    }
};
