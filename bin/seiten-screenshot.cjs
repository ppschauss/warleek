const p=require("/app/node_modules/puppeteer");
(async()=>{
  const url=process.argv[2], out=process.argv[3];
  const b=await p.launch({args:["--no-sandbox","--disable-dev-shm-usage"]});
  const pg=await b.newPage();
  // Erst normal laden, Höhe messen
  await pg.setViewport({width:1280,height:1000});
  await pg.goto(url,{waitUntil:"networkidle2"});
  const h=await pg.evaluate(()=>document.documentElement.scrollHeight);
  // Viewport auf die ganze Seite: dann liegt alles „im Bild" und lädt
  await pg.setViewport({width:1280,height:Math.min(h+200,20000)});
  await pg.goto(url,{waitUntil:"networkidle2"});
  await pg.evaluate(()=>Promise.all([...document.images].filter(i=>!i.complete).map(i=>new Promise(r=>{i.onload=i.onerror=r;}))));
  await new Promise(r=>setTimeout(r,1200));
  const offen=await pg.evaluate(()=>[...document.images].filter(i=>!i.naturalWidth).map(i=>i.src.split("/").pop()));
  const leer=await pg.evaluate(()=>[...document.querySelectorAll(".wl-reveal")].filter(e=>getComputedStyle(e).opacity!=="1").length);
  console.log("  Seitenhöhe:", h, "| ohne Bilddaten:", offen.length?offen.join(", "):"keine", "| unsichtbare Reveals:", leer);
  await pg.screenshot({path:out});
  await b.close();
})();
