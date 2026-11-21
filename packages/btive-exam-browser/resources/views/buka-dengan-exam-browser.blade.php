<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Buka dengan B'tive Exam Browser</title>
    <style>
        :root { color-scheme: light; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f1f5f9; color: #0f172a;
               font: 16px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; padding: 16px; box-sizing: border-box; }
        main { max-width: 28rem; width: 100%; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; }
        h1 { font-size: 1.25rem; margin: 0 0 8px; }
        p { margin: 8px 0; color: #475569; }
        a.tombol { display: inline-block; margin-top: 12px; background: #0f766e; color: #fff; text-decoration: none; padding: 10px 16px; border-radius: 8px; }
        code { font-size: .8rem; color: #64748b; }
    </style>
</head>
<body>
<main>
    <h1>Buka dengan B'tive Exam Browser</h1>
    <p>{{ $result->message() }}</p>
    @if ($result->code === 'eb_level_insufficient' && $result->session)
        <p>Level perangkat Anda L{{ $result->session->effectiveLevel }}, ujian ini membutuhkan minimal L{{ $result->requiredLevel }}.</p>
    @endif
    @if ($result->code === 'eb_missing')
        <p>Buka aplikasi B'tive Exam Browser, lalu masukkan kode ujian dari pengawas.</p>
        @if ($downloadUrl)
            <a class="tombol" href="{{ $downloadUrl }}">Unduh B'tive Exam Browser</a>
        @endif
    @endif
    <p><code>{{ $result->code }}</code></p>
</main>
</body>
</html>
