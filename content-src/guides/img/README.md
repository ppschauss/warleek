# Bilder für die Guides

Hier liegen die Bilddateien, die in Guides verwendet werden – sowohl Titelbilder
als auch Bilder im Fließtext.

## Regeln

- **Dateiname = Asset-Schlüssel.** `bild-hot-zone.webp` heißt im Markdown `bild-hot-zone`.
- Erlaubt sind `.webp`, `.png`, `.jpg`. WebP ist die erste Wahl; Screenshots dürfen PNG bleiben,
  wenn sie feine Kanten haben (UI-Text, dünne Linien).
- Breite **1024 px**, Seitenverhältnis frei. Screenshots aus dem Spiel gern im Original-Seitenverhältnis.
- Ein Schlüssel wird **einmal** vergeben und nicht wiederverwendet – die Mediathek erkennt
  Bilder daran wieder (`_warleek_asset_key`) und legt sie beim erneuten Einspielen nicht doppelt an.

## So bindest du ein Bild ein

Titelbild im Kopfblock der Markdown-Datei:

```
image: guide-kontrollzone
image_alt: Blick von oben über ein Industrietal mit vier Funktürmen
```

Bild im Fließtext – an die Stelle, wo es hingehört:

```
![Die Hot Zone ist kleiner als die Kontrollzone und wandert.](bild-hot-zone)
```

Mit sichtbarer Bildunterschrift (für Screenshots meist die bessere Wahl):

```
![Das Audio-Menü mit den drei Voice-Schaltern.](screenshot-voice "Escape → Audio → Voice Chat, Squad Voice und Proximity Voice einschalten.")
```

Der Text in den eckigen Klammern ist der **Alt-Text**. Er ist Pflicht: Er steht in der
Mediathek, wird von Screenreadern vorgelesen und von Suchmaschinen gelesen.
Beschreib, was zu sehen ist, nicht was du sagen willst. Der Text in Anführungszeichen
ist die **Bildunterschrift** und steht sichtbar unter dem Bild – dort gehört die
Erklärung hin. Beide sollen sich ergänzen, nicht wiederholen.

## Danach

```bash
php bin/guides-build.php --dry-run   # meckert, wenn ein Bild oder Alt-Text fehlt
php bin/guides-build.php             # schreibt guides.json und MANIFEST.json
./manage.sh seed                     # spielt alles in die Dev-Instanz
```

Beim Bauen werden nur die Bilder übernommen, die auch wirklich in einem Guide
vorkommen. Wer eine Datei hier ablegt und nirgends verlinkt, ändert nichts.

## Umwandeln

```bash
cwebp -q 82 -resize 1024 0 quelle.png -o bild-name.webp
```

## Schaubilder

Die Dateien `dia-*.svg` sind die **Quellen** der Schaubilder; ausgeliefert wird das
daneben liegende `.webp`. Ändern heißt: SVG bearbeiten, neu rendern, neu bauen.

```bash
docker cp dia-kurve.svg wh-web-check:/tmp/dia/dia-kurve.svg
docker exec wh-web-check chromium --headless=new --no-sandbox --disable-dev-shm-usage \
  --hide-scrollbars --screenshot=/tmp/dia/dia-kurve.png --window-size=1024,688 file:///tmp/dia/dia-kurve.svg
docker cp wh-web-check:/tmp/dia/dia-kurve.png . && cwebp -q 90 dia-kurve.png -o dia-kurve.webp
```

Farben aus `theme.json`: Hintergrund `#161c18`, Text `#eef5e6`, Raster `#1f2822`,
Beschriftung `#8a938c`, Akzent `#9be15d` (Lauchgrün), Warnung `#e0843a`, neutral `#c7b58f`.

## Was hier nicht hingehört

**Keine Bildschirmfotos aus fremden Videos oder von fremden Webseiten.** Auch nicht
aus YouTube-Guides, die wir verlinken – das wäre eine Veröffentlichung fremder Inhalte.
Eigene Aufnahmen aus dem Spiel sind in Ordnung, eigene Schaubilder sowieso.
