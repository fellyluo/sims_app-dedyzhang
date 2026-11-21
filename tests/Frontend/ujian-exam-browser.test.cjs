const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { test } = require('node:test');
const vm = require('node:vm');

const view = readFileSync('resources/views/ujian/siswa/kerjakan.blade.php', 'utf8');
const source = view.match(/<script>([\s\S]*?)<\/script>/)[1];

function bukaUjian({ nativeVerified = false, requestFullscreen, webkitRequestFullscreen } = {}) {
    const events = new Map();
    const document = {
        documentElement: { requestFullscreen, webkitRequestFullscreen },
        querySelector: () => null,
        addEventListener: (name, callback) => events.set(name, callback),
    };
    const storage = new Map();
    const window = {
        lucide: { createIcons() {} },
        addEventListener() {},
        localStorage: {
            getItem: (key) => storage.get(key),
            setItem: (key, value) => storage.set(key, value),
            removeItem: (key) => storage.delete(key),
        },
    };
    const ujian = vm.runInNewContext(`${source}\nujianKerjakan(config)`, {
        document, window, config: { nativeVerified }, setInterval: () => 0,
    });
    ujian.$nextTick = (callback) => callback();
    return { ujian, document, events };
}

test('browser yang mendukung layar penuh tetap di gate ketika permintaan ditolak', async () => {
    const { ujian } = bukaUjian({ requestFullscreen: () => Promise.reject(new Error('ditolak')) });

    await ujian.masukLayarPenuh();
    ujian.lanjutTanpaLayarPenuh();

    assert.equal(ujian.fsActive, false);
    assert.equal(ujian.layarPenuhGagal, true);
});

test('layar penuh WebKit membuka soal dan keluar dari layar penuh dicatat sekali', async () => {
    const { ujian, document, events } = bukaUjian({
        webkitRequestFullscreen() { document.webkitFullscreenElement = this; },
    });
    const laporan = [];
    ujian.laporKeluar = (tipe) => laporan.push(tipe);
    ujian.init();

    await ujian.masukLayarPenuh();
    assert.equal(ujian.fsActive, true);

    document.webkitFullscreenElement = null;
    events.get('webkitfullscreenchange')();
    events.get('fullscreenchange')();

    assert.equal(ujian.fsActive, false);
    assert.deepEqual(laporan, ['keluar_fullscreen']);
});

test('browser tanpa API layar penuh boleh memakai mode web dasar', () => {
    const { ujian } = bukaUjian();
    assert.equal(ujian.fsActive, false);
    ujian.lanjutTanpaLayarPenuh();
    assert.equal(ujian.fsActive, true);
});

test('Exam Browser yang diverifikasi membuka soal tanpa listener layar penuh web', () => {
    const { ujian, events } = bukaUjian({ nativeVerified: true, requestFullscreen() {} });
    ujian.init();

    assert.equal(ujian.fsActive, true);
    assert.equal(events.has('fullscreenchange'), false);
    assert.equal(events.has('visibilitychange'), true);
});
