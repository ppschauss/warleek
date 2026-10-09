const puppeteer = require('/app/node_modules/puppeteer');
(async () => {
  const url = process.argv[2];
  const browser = await puppeteer.launch({args:['--no-sandbox','--disable-dev-shm-usage']});
  const page = await browser.newPage();
  const treffer = [];
  page.on('request', r => {
    const u = r.url();
    if (/google|gtag|analytics|doubleclick|visibilitykit/i.test(u)) treffer.push(u.slice(0,90));
  });

  // 1) Ohne Einwilligung
  await page.goto(url, {waitUntil:'networkidle2'});
  await new Promise(r => setTimeout(r, 1500));
  console.log('OHNE Einwilligung:', treffer.length ? treffer : '(keine Anfrage an Google)');

  // 2) Einwilligung setzen und neu laden
  treffer.length = 0;
  await page.evaluate(() => localStorage.setItem('warleek_consent', JSON.stringify({media:false, statistik:true})));
  await page.goto(url, {waitUntil:'networkidle2'});
  await new Promise(r => setTimeout(r, 2500));
  console.log('MIT Einwilligung: ', treffer.length ? treffer : '(nichts geladen - FEHLER)');

  // 3) Banner sichtbar?
  await page.evaluate(() => localStorage.removeItem('warleek_consent'));
  await page.goto(url, {waitUntil:'networkidle2'});
  const banner = await page.evaluate(() => {
    const b = document.querySelector('.wl-consent, [class*="consent"]');
    return b ? b.innerText.replace(/\s+/g,' ').slice(0,260) : 'KEIN BANNER';
  });
  console.log('BANNER:', banner);
  await browser.close();
})();
