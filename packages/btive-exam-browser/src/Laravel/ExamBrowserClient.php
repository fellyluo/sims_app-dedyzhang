<?php

namespace Btive\ExamBrowser\Laravel;

use Btive\ExamBrowser\ConsoleUnavailable;
use Btive\ExamBrowser\ConsoleUrl;
use Btive\ExamBrowser\FinishToken;
use Btive\ExamBrowser\Introspector;
use Illuminate\Http\Client\Factory as Http;

/**
 * Aksi target ke Exam Console: menandai jawaban tersimpan (API-09) dan membuat finish token untuk
 * `ExamBrowser.finish(token)` di halaman (SPEC §12.2).
 */
final class ExamBrowserClient
{
    public function __construct(
        private readonly Http $http,
        private readonly Introspector $introspector,
        private readonly string $consoleUrl,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly float $timeout = 3.0,
    ) {}

    /**
     * API-09. Idempoten. Mengembalikan status sesi ('finished') atau null bila sesi sudah berakhir/tidak dikenal.
     *
     * @throws ConsoleUnavailable
     */
    public function finish(string $clientSessionId): ?string
    {
        ConsoleUrl::pastikanAman($this->consoleUrl);

        try {
            $response = $this->http->withToken($this->apiKey)->acceptJson()->timeout($this->timeout)
                ->post(rtrim($this->consoleUrl, '/').'/api/v1/integrations/sessions/'.rawurlencode($clientSessionId).'/finish');
        } catch (\Throwable $e) {
            throw new ConsoleUnavailable('Exam Console tidak terjangkau: '.$e->getMessage(), 0, $e);
        }

        if (in_array($response->status(), [404, 410], true)) {
            return null;
        }

        if (! $response->successful()) {
            throw new ConsoleUnavailable('Exam Console menjawab HTTP '.$response->status().'.');
        }

        return (string) $response->json('status');
    }

    /**
     * Token untuk dipanggil halaman: `ExamBrowser.finish(token)` → klien keluar seketika.
     *
     * @throws ConsoleUnavailable
     */
    public function finishToken(string $clientSessionId, ?int $now = null): ?string
    {
        $info = $this->introspector->fetch($clientSessionId);

        return $info === null ? null : FinishToken::create($info->sessionKey(), $info->sessionId, $now ?? time());
    }
}
