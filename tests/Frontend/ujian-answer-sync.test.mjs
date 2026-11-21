import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
const blade = readFileSync(new URL('../../resources/views/ujian/siswa/kerjakan.blade.php', import.meta.url), 'utf8');
const script = blade.slice(blade.indexOf('function getSafeStorage()'), blade.lastIndexOf('</script>'));
function component(fetcher) {
  const context = vm.createContext({ console, setTimeout, clearTimeout, setInterval, clearInterval, AbortController,
    fetch: fetcher, document: { querySelector: () => null, documentElement: {} }, window: { location: {} }, navigator: {},
    $: { alert: () => {} } });
  vm.runInContext(script, context);
  const value = context.ujianKerjakan({ soal: [{ uuid: 'a', tipe: 'essay' }], jawabanAwal: {}, urlSinkronBatch: '/sync', urlSubmit: '/submit', urlTerkunci: '/gate' });
  value._ebHeaders = async () => ({});
  value._storage = { removed: 0, removeItem() { this.removed++; }, setItem() {}, getItem() { return null; } };
  value._window = context.window;
  return value;
}
const headers = { get: () => 'application/json' };
function perUrl(map) {
  const calls = [];
  const fetcher = async (url) => { calls.push(url); return map[url](calls.filter(u => u === url).length); };
  return { calls, fetcher };
}
test('ACK lama tidak menghapus jawaban yang berubah ketika request berjalan', async () => {
  let resolve;
  const c = component(() => new Promise(r => { resolve = r; }));
  c._dirtyQueue.a = { id_soal: 'a', jawaban_esai: 'lama' };
  const flushing = c.flushPendingAnswers();
  await new Promise(setImmediate);
  c._dirtyQueue.a = { id_soal: 'a', jawaban_esai: 'baru' };
  resolve({ ok: true });
  assert.equal(await flushing, false);
  assert.equal(c._dirtyQueue.a.jawaban_esai, 'baru');
});
test('kumpul manual ditunda bila sinkronisasi gagal karena jaringan (sementara)', async () => {
  let requests = 0;
  const c = component(async () => { requests++; return { ok: false, status: 503, headers }; });
  c._dirtyQueue.a = { id_soal: 'a', jawaban_esai: 'penting' };
  await c.kumpulkan(false);
  assert.equal(requests, 3);
  assert.equal(c.simpanError, true);
  assert.equal(c._dirtyQueue.a.jawaban_esai, 'penting');
  assert.equal(c.mengumpulkan, false);
});
test('error permanen 422 saat sinkron tidak memblokir pengumpulan; cadangan lokal dipertahankan (review #2)', async () => {
  const { calls, fetcher } = perUrl({
    '/sync': () => ({ ok: false, status: 422, headers }),
    '/submit': () => ({ ok: true, status: 200, headers, json: async () => ({ redirect: '/hasil' }) }),
  });
  const c = component(fetcher);
  c._dirtyQueue.a = { id_soal: 'a', jawaban_esai: 'x' };
  await c.kumpulkan(false);
  assert.equal(calls.filter(u => u === '/sync').length, 1);
  assert.equal(calls.filter(u => u === '/submit').length, 1);
  assert.equal(c._storage.removed, 0);
  assert.equal(c._window.location.href, '/hasil');
});
test('auto-submit waktu habis tetap mengumpulkan walau sinkron gagal (review #1)', async () => {
  const { calls, fetcher } = perUrl({
    '/sync': () => { throw new Error('offline'); },
    '/submit': () => ({ ok: true, status: 200, headers, json: async () => ({ redirect: '/hasil' }) }),
  });
  const c = component(fetcher);
  c._dirtyQueue.a = { id_soal: 'a', jawaban_esai: 'x' };
  await c.kumpulkan(true);
  assert.equal(calls.filter(u => u === '/submit').length, 1);
  assert.equal(c._storage.removed, 0);
  assert.equal(c._window.location.href, '/hasil');
});
test('auto-submit yang gagal diulang dengan jeda, bukan tiap detik (review #1)', async () => {
  const { fetcher } = perUrl({ '/submit': () => { throw new Error('offline'); } });
  const c = component(fetcher);
  c.batasWaktu = Date.now() - 1000;
  await c.kumpulkan(true);
  clearInterval(c._timerHandle);
  assert.equal(c.mengumpulkan, false);
  assert.ok(c._autoRetryAt > Date.now() + 5000);
  c.tickCountdown();
  assert.equal(c.mengumpulkan, false);
});
test('submit 422 (sudah dikumpulkan server) mengarahkan ke halaman gate/hasil', async () => {
  const { calls, fetcher } = perUrl({ '/submit': () => ({ ok: false, status: 422, headers, redirected: false }) });
  const c = component(fetcher);
  await c.kumpulkan(false);
  assert.equal(calls.length, 1);
  assert.equal(c._window.location.href, '/gate');
});
test('flush final menunggu request terdahulu tanpa request bersamaan', async () => {
  let calls = 0; let resolve;
  const c = component(() => { calls++; return new Promise(r => { resolve = r; }); });
  c._dirtyQueue.a = { id_soal: 'a', jawaban_esai: 'lama' };
  const first = c.flushPendingAnswers();
  await new Promise(setImmediate);
  const final = c.flushPendingAnswers(true);
  assert.equal(calls, 1);
  resolve({ ok: true });
  assert.equal(await first, true);
  assert.equal(await final, true);
  assert.equal(calls, 1);
});
