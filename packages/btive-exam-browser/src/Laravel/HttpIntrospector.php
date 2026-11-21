<?php

namespace Btive\ExamBrowser\Laravel;

use Btive\ExamBrowser\ConsoleUnavailable;
use Btive\ExamBrowser\ConsoleUrl;
use Btive\ExamBrowser\Introspector;
use Btive\ExamBrowser\SessionInfo;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Http\Client\Factory as Http;

/**
 * Introspeksi API-07 lewat Laravel Http, cache terenkripsi (berisi session_key). Hanya sesi berstatus
 * `active` yang di-cache; selain itu selalu diambil ulang (SPEC §11.3).
 */
final class HttpIntrospector implements Introspector
{
    public function __construct(
        private readonly Http $http,
        private readonly Cache $cache,
        private readonly Encrypter $crypt,
        private readonly string $consoleUrl,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly int $cacheTtl = 30,
        private readonly float $timeout = 3.0,
    ) {}

    public function fetch(string $sessionId, bool $fresh = false): ?SessionInfo
    {
        $key = 'exam-browser:sesi:'.$sessionId;

        if (! $fresh) {
            $cached = $this->cache->get($key);
            if (is_string($cached)) {
                try {
                    $data = $this->crypt->decrypt($cached);
                    $info = is_array($data) ? SessionInfo::fromArray($data, fromCache: true) : null;
                    if ($info !== null) {
                        return $info;
                    }
                } catch (\Throwable) {
                    // cache rusak / APP_KEY berganti → ambil ulang
                }
            }
        }

        ConsoleUrl::pastikanAman($this->consoleUrl);

        try {
            $response = $this->http->withToken($this->apiKey)->acceptJson()->timeout($this->timeout)
                ->get(rtrim($this->consoleUrl, '/').'/api/v1/integrations/sessions/'.rawurlencode($sessionId));
        } catch (\Throwable $e) {
            throw new ConsoleUnavailable('Exam Console tidak terjangkau: '.$e->getMessage(), 0, $e);
        }

        if ($response->status() === 404) {
            $this->cache->forget($key);

            return null;
        }

        if (! $response->successful()) {
            throw new ConsoleUnavailable('Exam Console menjawab HTTP '.$response->status().' ('.($response->json('error.code') ?? '-').').');
        }

        $data = $response->json();
        $info = is_array($data) ? SessionInfo::fromArray($data) : null;

        if ($info === null) {
            throw new ConsoleUnavailable('Respons introspeksi tidak valid.');
        }

        if ($info->status === 'active') {
            $this->cache->put($key, $this->crypt->encrypt($info->toArray()), min(300, max(1, $this->cacheTtl)));
        } else {
            $this->cache->forget($key);
        }

        return $info;
    }
}
