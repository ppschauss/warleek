const puppeteer = require('/app/node_modules/puppeteer');
const URL = 'http://192.168.0.161:8088/';
(async () => {
  const b = await puppeteer.launch({args:['--no-sandbox','--disable-dev-shm-usage']});
  const p = await b.newPage();
  const net = [];
  p.on('request', r => { if (/google|gtag|analytics/i.test(r.url())) net.push(r.url().slice(0,80)); });

  const stand = async () => p.evaluate(() => {
    const d = (window.dataLayer||[]).filter(a => a[0] === 'consent');
    return d.map(a => a[1] + ': ' + JSON.stringify(a[2]));
  });

  await p.goto(URL, {waitUntil:'networkidle2'});
  await new Promise(r=>setTimeout(r,1200));
  console.log('1) OHNE Einwilligung');
  console.log('   Netzwerk an Google:', net.length ? net : '(nichts)');
  console.log('   dataLayer consent: ', (await stand()).join(' | '));

  net.length = 0;
  await p.evaluate(() => document.querySelector('[data-wl-consent="allow"]')?.click());
  await new Promise(r=>setTimeout(r,2500));
  console.log('2) NACH Zustimmung');
  console.log('   Netzwerk an Google:', net.length ? net : '(nichts - FEHLER)');
  console.log('   dataLayer consent: ', (await stand()).join(' | '));

  net.length = 0;
  await p.evaluate(() => window.warleekConsent.set({media:false, statistik:false}));
  await new Promise(r=>setTimeout(r,800));
  console.log('3) NACH Widerruf');
  console.log('   dataLayer consent: ', (await stand()).join(' | '));
  await b.close();
})();
