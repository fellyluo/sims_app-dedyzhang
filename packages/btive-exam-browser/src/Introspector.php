<?php

namespace Btive\ExamBrowser;

/**
 * Sumber data sesi (API-07). Implementasi wajib melempar ConsoleUnavailable untuk error jaringan/5xx
 * dan mengembalikan null hanya untuk 404 (sesi tidak dikenal).
 */
interface Introspector
{
    /**
     * @throws ConsoleUnavailable
     */
    public function fetch(string $sessionId, bool $fresh = false): ?SessionInfo;
}
