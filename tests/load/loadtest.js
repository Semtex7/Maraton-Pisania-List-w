// Load test for the Maraton site (see tests/README.md before running).
//
//   BASE_URL=http://maraton-pisania-listow.local PETITION_URL=<url of a petition> npm run load:pages -- 20 15
//   npm run load:sign -- 10 20     (LOCAL ONLY, needs load/loadtest-helper.php active)
//
// pages: GET home, petition, events list and the map AJAX with <connections> for <seconds> each.
// sign:  POSTs signatures with "sign all" (emails loadtest-*@example.invalid). It writes to the database
//        and triggers Salesforce sync, so it must only run locally with the helper plugin that simulates
//        Salesforce. Remove the rows afterwards (README.md).
const autocannon = require('autocannon');

const [mode = 'pages', conn = '20', secs = '15'] = process.argv.slice(2);
const BASE = (process.env.BASE_URL || 'http://maraton-pisania-listow.local').replace(/\/$/, '');
const PETITION_URL = process.env.PETITION_URL;

function report(name, r) {
  const codes = Object.entries(r.statusCodeStats || {}).map(([c, s]) => c + ':' + s.count).join(' ');
  console.log(`${name.padEnd(28)} req=${r.requests.total} rps=${r.requests.average} p50=${r.latency.p50}ms p90=${r.latency.p90}ms p99=${r.latency.p99}ms max=${r.latency.max}ms errors=${r.errors} timeouts=${r.timeouts} non2xx=${r.non2xx} [${codes}]`);
}

(async () => {
  if (!PETITION_URL) throw new Error('Set PETITION_URL to the address of a published petition');

  if (mode === 'pages') {
    for (const [name, url] of [
      ['strona główna', BASE + '/'],
      ['petycja', PETITION_URL],
      ['lista wydarzeń', BASE + '/wydarzenie/'],
      ['AJAX mapa', BASE + '/wp-admin/admin-ajax.php?action=get_events_map_data'],
    ]) {
      report(name, await autocannon({ url, connections: +conn, duration: +secs, timeout: 30 }));
    }
    return;
  }

  if (mode !== 'sign') throw new Error('mode: pages | sign');
  if (!/\.local\b|localhost|127\.0\.0\.1/.test(BASE)) throw new Error('sign mode is local only (writes signatures, triggers Salesforce sync)');

  // Guest nonce and petition id from the petition page
  const html = await (await fetch(PETITION_URL)).text();
  const nonce = (html.match(/"nonce":"([a-f0-9]+)"/) || [])[1];
  const pid = (html.match(/name="petition_id" value="(\d+)"/) || [])[1];
  if (!nonce || !pid) throw new Error('no nonce / petition id on the petition page');

  let n = 0;
  const r = await autocannon({
    url: BASE + '/wp-admin/admin-ajax.php', method: 'POST', connections: +conn, duration: +secs, timeout: 60,
    headers: { 'content-type': 'application/x-www-form-urlencoded' },
    requests: [{
      setupRequest: (req) => ({
        ...req,
        body: new URLSearchParams({
          action: 'submit_amnesty_petition', amnesty_sign_nonce: nonce, petition_id: pid,
          first_name: 'Load', last_name: 'Test', email: `loadtest-${Date.now()}-${n++}@example.invalid`,
          sign_all: '1', letter_type: 'appeal', custom_message: 'test', rodo_consent: '1', amnesty_elapsed: '5',
        }).toString(),
      }),
    }],
  });
  report('podpis + Podpisz wszystkie', r);
  console.log('wysłanych formularzy:', n);
})().catch((e) => { console.error(e.message || e); process.exit(1); });
