// Visual/layout checks in headless Chrome (see tests/README.md).
//
//   TEST_EMAIL=... TEST_PASSWORD=... npm run ui
//   Optional: BASE_URL (default local site), CHROME_PATH, SHOTS_DIR (default ui/shots)
//
// Use a dedicated subscriber test account (never an administrator). Checks:
//   - login page: gap between "Zaloguj się" and "Nie pamiętasz hasła?"
//   - /panel/ tabs: tab list position and content width stay the same when switching tabs
//     (also with tabs on top, the layout used on staging)
//   - add-event / order forms: space above the submit button (.form-section-last)
// Exit code 1 when a check fails.
const puppeteer = require('puppeteer-core');
const fs = require('fs');
const path = require('path');

const BASE = (process.env.BASE_URL || 'http://maraton-pisania-listow.local').replace(/\/$/, '');
const CHROME = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const OUT = process.env.SHOTS_DIR || path.join(__dirname, 'shots');
const { TEST_EMAIL, TEST_PASSWORD } = process.env;

let failed = 0;
const check = (name, ok, detail) => { if (!ok) failed++; console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${detail !== undefined ? '  [' + detail + ']' : ''}`); };

(async () => {
  if (!TEST_EMAIL || !TEST_PASSWORD) throw new Error('Set TEST_EMAIL and TEST_PASSWORD (subscriber test account)');
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await puppeteer.launch({ executablePath: CHROME, headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 900 });

  // Bot protection must not block real visitors: the browser has to send the time spent on the page.
  // POSTs are intercepted and aborted (nothing is saved, no Salesforce sync).
  const capturePost = async (urlPart, submit) => {
    let body = null;
    const onRequest = (r) => {
      if (r.method() === 'POST' && r.url().includes(urlPart)) { body = r.postData() || ''; return r.abort(); }
      r.continue();
    };
    await page.setRequestInterception(true);
    page.on('request', onRequest);
    page.on('dialog', (d) => d.dismiss().catch(() => {}));
    await submit();
    await new Promise((r) => setTimeout(r, 1500));
    page.off('request', onRequest);
    await page.setRequestInterception(false);
    return body || '';
  };
  const fieldValue = (body, name) => {
    const multipart = new RegExp(`name="${name}"\\r\\n\\r\\n([^\\r]*)`).exec(body);
    return multipart ? multipart[1] : new URLSearchParams(body).get(name);
  };

  const petitionUrl = process.env.PETITION_URL;
  if (petitionUrl) {
    await page.goto(petitionUrl, { waitUntil: 'networkidle2' });
    const body = await capturePost('admin-ajax.php', async () => {
      const f = await page.$('.amnesty-petition-form');
      await f.$eval('[name="first_name"]', (e) => (e.value = 'UI'));
      await f.$eval('[name="last_name"]', (e) => (e.value = 'Test'));
      await f.$eval('[name="email"]', (e) => (e.value = 'ui-check@example.invalid'));
      await f.$eval('[name="rodo_consent"]', (e) => (e.checked = true));
      await new Promise((r) => setTimeout(r, 2500));
      await f.$eval('.amnesty-petition-submit-btn', (b) => b.click());
    });
    const elapsed = parseFloat(fieldValue(body, 'amnesty_elapsed'));
    check('petycja: przeglądarka wysyła czas na stronie ≥ 2 s (człowiek nie jest brany za bota)', elapsed >= 2, elapsed + ' s');
    check('petycja: pole-pułapka puste', fieldValue(body, 'amnesty_website') === '');
  } else {
    console.log('SKIP petycja: ustaw PETITION_URL, aby sprawdzić formularz podpisu');
  }

  await page.goto(BASE + '/zaloguj-sie/', { waitUntil: 'networkidle2' });
  if (await page.$('#reg_user_email')) {
    const body = await capturePost('/zaloguj-sie', async () => {
      await page.type('#reg_user_email', 'ui-check@example.invalid');
      await page.type('#reg_user_pass', 'haslo12345');
      await page.$eval('[name="consent_marathon"]', (e) => (e.checked = true));
      await new Promise((r) => setTimeout(r, 2500));
      await page.$eval('button[name="submit_registration"]', (b) => b.click());
    });
    const elapsed = parseFloat(fieldValue(body, 'maraton_elapsed'));
    check('rejestracja: przeglądarka wysyła czas na stronie ≥ 2 s', elapsed >= 2, elapsed + ' s');
  }

  // Login page
  await page.goto(BASE + '/zaloguj-sie/', { waitUntil: 'networkidle2' });
  const loginGap = await page.evaluate(() => {
    const btn = document.querySelector('#event-login-form button[name="submit_login"]');
    const link = document.querySelector('.login-lost-password a');
    return btn && link ? Math.round(link.getBoundingClientRect().top - btn.getBoundingClientRect().bottom) : null;
  });
  check('logowanie: odstęp „Nie pamiętasz hasła?” ≥ 15 px', loginGap !== null && loginGap >= 15, loginGap + ' px');
  const form = await page.$('#event-login-form');
  if (form) await form.screenshot({ path: path.join(OUT, 'login.png') });

  // Log in
  await page.type('#user_login', TEST_EMAIL);
  await page.type('#user_pass', TEST_PASSWORD);
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), page.click('#event-login-form button[name="submit_login"]')]);
  if (!page.url().includes('/panel')) await page.goto(BASE + '/panel/', { waitUntil: 'networkidle2' });
  check('logowanie przekierowuje do /panel/', page.url().includes('/panel'), page.url());

  // Panel tabs, as configured and with tabs on top
  const measureTabs = async (label) => {
    const titles = await page.$$('.e-n-tabs-heading > .e-n-tab-title');
    const seen = [];
    for (let i = 0; i < titles.length; i++) {
      await titles[i].click();
      await new Promise((r) => setTimeout(r, 300));
      seen.push(await page.evaluate(() => {
        const first = document.querySelector('.e-n-tabs-heading > .e-n-tab-title').getBoundingClientRect();
        const content = document.querySelector('.e-n-tabs-content').getBoundingClientRect();
        const active = document.querySelector('.e-n-tabs-content > [role="tabpanel"].e-active') || document.querySelector('.e-n-tabs-content > [role="tabpanel"]:not([hidden])');
        return { x: Math.round(first.left), w: Math.round(content.width), panel: active ? Math.round(active.getBoundingClientRect().width) : null };
      }));
      await page.screenshot({ path: path.join(OUT, `panel-${label}-tab${i + 1}.png`) });
    }
    const same = (k) => seen.every((s) => s[k] === seen[0][k]);
    check(`panel (${label}): zakładki bez przeskoku`, titles.length > 0 && same('x') && same('w'), JSON.stringify(seen));
    check(`panel (${label}): panele na pełną szerokość`, seen.every((s) => s.panel === null || s.panel === s.w), seen.map((s) => s.panel + '/' + s.w).join(' '));
  };
  if (await page.$('.elementor-widget-n-tabs')) {
    await measureTabs('układ z Elementora');
    await page.evaluate(() => {
      const w = document.querySelector('.elementor-widget-n-tabs');
      w.style.setProperty('--n-tabs-direction', 'column');
      w.style.setProperty('--n-tabs-heading-direction', 'row');
      w.style.setProperty('--n-tabs-heading-width', 'initial');
      w.style.setProperty('--n-tabs-heading-justify-content', 'center');
    });
    await measureTabs('zakładki u góry');
  } else {
    console.log('SKIP panel: brak widgetu zakładek na /panel/');
  }

  // Space above submit buttons (forms can be in hidden tabs/popups: computed style is enough)
  const gaps = await page.evaluate(() => [...document.querySelectorAll('.form-section-last')].map((el) => parseFloat(getComputedStyle(el).marginTop)));
  if (gaps.length) check('formularze: odstęp nad przyciskiem ≥ 24 px', gaps.every((g) => g >= 24), gaps.join(', ') + ' px');
  else console.log('SKIP formularze: brak .form-section-last na /panel/');

  await browser.close();
  console.log(`\nZrzuty ekranu: ${OUT}`);
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error(e.message || e); process.exit(1); });
