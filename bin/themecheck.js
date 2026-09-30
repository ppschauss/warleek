// Misst Textkontraste in allen Farbwelten × Hell/Dunkel.
// Prüft gegen WCAG AA: 4.5:1 für normalen Text, 3:1 für großen Text (>=24px oder >=18.66px fett).
const puppeteer = require('/tmp/pp/node_modules/puppeteer-core');

const THEMES = ['warleek', 'monochrom', 'valkyra', 'lonestar', 'manticore', 'pastell'];
const MODI = ['dark', 'light'];

(async () => {
  const base = process.argv[2] || 'http://192.168.0.161:8088';
  const seiten = (process.argv[3] || '/,/guides/,/guides/wardogs-medic/,/patch-notes/').split(',');
  const browser = await puppeteer.launch({ executablePath: '/usr/bin/chromium', args: ['--no-sandbox', '--disable-dev-shm-usage'] });
  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 1000 });

  const messe = () => page.evaluate(() => {
    const lum = (c) => {
      const [r, g, b] = c.map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
      return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    };
    const parse = (s) => (s.match(/[\d.]+/g) || ['0', '0', '0']).slice(0, 3).map(Number);
    const alpha = (s) => { const m = s.match(/[\d.]+/g); return m && m.length > 3 ? Number(m[3]) : 1; };
    const grund = (el) => {
      let e = el;
      while (e) {
        const bg = getComputedStyle(e).backgroundColor;
        if (bg && alpha(bg) > 0.5) return parse(bg);
        // Im Held-Bereich liegt ein dunkles Foto plus Verlauf – ohne diese Annahme
        // liefe die Messung gegen die weiße Seite dahinter und meldete Blödsinn.
        if (e.classList && e.classList.contains('wl-hero')) return [13, 17, 15];
        e = e.parentElement;
      }
      return [255, 255, 255];
    };
    const wichtig = 'h1, h2, h3, p, li, a, .wl-card p, .wl-tag, .wl-eyebrow, .wl-stand, figcaption, th, td, .wl-btn, button, legend, .wl-appearance__opt';
    const ergebnis = [];
    const gesehen = new Set();
    document.querySelectorAll(wichtig).forEach((el) => {
      const txt = (el.textContent || '').trim();
      if (!txt || el.offsetParent === null) return;
      if (el.querySelector(wichtig)) return; // nur Blätter, sonst zählt Text doppelt
      const cs = getComputedStyle(el);
      const gr = Math.round(parseFloat(cs.fontSize));
      const fett = parseInt(cs.fontWeight, 10) >= 700;
      const gross = gr >= 24 || (gr >= 18.66 && fett);
      const schluessel = cs.color + '|' + grund(el).join(',') + '|' + gross;
      if (gesehen.has(schluessel)) return;
      gesehen.add(schluessel);
      const k = (Math.max(lum(parse(cs.color)), lum(grund(el))) + 0.05) / (Math.min(lum(parse(cs.color)), lum(grund(el))) + 0.05);
      ergebnis.push({ text: txt.slice(0, 26), farbe: cs.color, grund: 'rgb(' + grund(el).join(',') + ')', gross, k: Math.round(k * 100) / 100, soll: gross ? 3 : 4.5 });
    });
    return ergebnis;
  });

  let schlecht = 0;
  for (const thema of THEMES) {
    for (const modus of MODI) {
      let schlimmste = null;
      const treffer = [];
      for (const s of seiten) {
        await page.goto(base + s, { waitUntil: 'networkidle0' });
        await page.evaluate((t, m) => {
          // Übergänge abschalten: Sonst misst man mitten in der Farbüberblendung
          // und bekommt Zwischenwerte, die es nie zu sehen gibt.
          if (!document.getElementById('wl-mess-stop')) {
            const st = document.createElement('style');
            st.id = 'wl-mess-stop';
            st.textContent = '*,*::before,*::after{transition:none!important;animation:none!important}';
            document.head.appendChild(st);
          }
          document.documentElement.setAttribute('data-wl-theme', t);
          document.documentElement.setAttribute('data-wl-mode', m);
        }, thema, modus);
        await new Promise((r) => setTimeout(r, 250));
        const w = await messe();
        w.filter((x) => x.k < x.soll).forEach((x) => treffer.push({ ...x, seite: s }));
        w.forEach((x) => { if (!schlimmste || x.k < schlimmste.k) schlimmste = x; });
      }
      const status = treffer.length ? 'FEHLER ' + treffer.length : 'ok';
      console.log(`${thema.padEnd(10)} ${modus.padEnd(6)} ${status.padEnd(10)} schwächster Wert ${schlimmste ? schlimmste.k + ':1 (' + schlimmste.text + ')' : '–'}`);
      treffer.slice(0, 4).forEach((t) => console.log(`    ${t.seite} „${t.text}" ${t.farbe} auf ${t.grund} = ${t.k}:1, nötig ${t.soll}`));
      schlecht += treffer.length;
    }
  }
  console.log(schlecht ? `\n${schlecht} Stellen unter der Grenze` : '\nalle Kombinationen bestehen WCAG AA');
  await browser.close();
})();
