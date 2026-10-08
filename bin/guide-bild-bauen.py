#!/usr/bin/env python3
"""Warleek — Guide-Titelbild aus vorhandenen Assets zusammensetzen.

Kein erzeugtes Bild: Der Hintergrund und die Kacheln stammen aus
`content-src/items/img/` – also aus den offiziellen Pressekits und eigenen
Spielaufnahmen, die ohnehin an den Datenbank-Einträgen hängen.

Aufbau: Hintergrund weichzeichnen und abdunkeln, darüber mehrere Asset-Kacheln
überlappend, Vignette, Verlauf nach unten, Titel in der Hausschrift.

Aufruf:   python3 bin/guide-bild-bauen.py <rezept>
Rezepte:  siehe REZEPTE unten. Ergebnis landet direkt in
          plugin/warleek-core/content/img/<name>.webp
Danach:   php bin/guides-build.php && ./manage.sh seed

Schrift: Die Theme-Schriften liegen als WOFF2 vor und werden hier nach TTF
gewandelt. Dafür braucht fontTools das Modul `brotli` (pip install brotli).
Fehlt es, bricht das Skript mit einem klaren Hinweis ab, statt eine
Ersatzschrift zu nehmen – ein fremder Schriftschnitt fiele im Bild auf.

Fallstrick, der hier schon zweimal zugeschlagen hat:
- Die Vignette muss **innen durchsichtig** sein (`(255 - v) * f`). Die
  naheliegende Form `255 - v * f` dunkelt auch die Bildmitte ab.
- `wardogs-munitionskiste` ist ein unbeleuchtetes, grellweißes Asset und als
  Kachel unbrauchbar.
"""
import os, sys
from PIL import Image, ImageFilter, ImageEnhance, ImageDraw, ImageFont

ROOT  = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC   = os.path.join(ROOT, "content-src", "items", "img") + os.sep
ZIEL  = os.path.join(ROOT, "plugin", "warleek-core", "content", "img") + os.sep
FONTS = os.path.join(ROOT, ".cache-fonts") + os.sep
W, H  = 1024, 576

BASE     = (0x0d, 0x11, 0x0f)
LEEK     = (0x9b, 0xe1, 0x5d)
TEXT     = (0xe6, 0xea, 0xe4)


def schrift(gewicht, groesse):
    """Theme-Schrift laden, bei Bedarf einmalig von WOFF2 nach TTF wandeln."""
    os.makedirs(FONTS, exist_ok=True)
    ttf = os.path.join(FONTS, f"barlow-condensed-{gewicht}.ttf")
    if not os.path.exists(ttf):
        woff = os.path.join(ROOT, "theme", "warleek", "assets", "fonts",
                            f"barlow-condensed-latin-{gewicht}.woff2")
        try:
            from fontTools.ttLib import TTFont
        except ImportError:
            sys.exit("fontTools fehlt: pip install fonttools brotli")
        try:
            f = TTFont(woff)
        except ImportError:
            sys.exit("Zum Lesen der WOFF2-Schrift fehlt das Modul brotli: pip install brotli")
        f.flavor = None
        f.save(ttf)
    return ImageFont.truetype(ttf, groesse)


def cover(im, w, h):
    """Bild formatfüllend auf w×h bringen, ohne zu verzerren."""
    r = max(w / im.width, h / im.height)
    im = im.resize((max(1, round(im.width * r)), max(1, round(im.height * r))), Image.LANCZOS)
    x = (im.width - w) // 2
    y = (im.height - h) // 2
    return im.crop((x, y, x + w, y + h))


def tile(name, w, h, dark=0.78):
    """Eine Asset-Kachel: zuschneiden, leicht abdunkeln, dünner Rand."""
    im = Image.open(SRC + name).convert("RGB")
    im = cover(im, w, h)
    im = ImageEnhance.Brightness(im).enhance(dark)
    im = ImageEnhance.Color(im).enhance(0.82)
    d = ImageDraw.Draw(im)
    d.rectangle([0, 0, w - 1, h - 1], outline=(0x2a, 0x33, 0x2c), width=2)
    return im


def shadow(size, blur=18, alpha=150):
    """Weicher Schlagschatten als eigene Ebene."""
    w, h = size
    pad = blur * 3
    lay = Image.new("RGBA", (w + pad * 2, h + pad * 2), (0, 0, 0, 0))
    ImageDraw.Draw(lay).rectangle([pad, pad, pad + w, pad + h], fill=(0, 0, 0, alpha))
    return lay.filter(ImageFilter.GaussianBlur(blur)), pad


def build(hintergrund, kacheln, titel, unterzeile, ziel):
    # --- Hintergrund: weit weg, dunkel, unscharf -------------------------
    bg = Image.open(SRC + hintergrund).convert("RGB")
    bg = cover(bg, W, H)
    bg = bg.filter(ImageFilter.GaussianBlur(7))
    bg = ImageEnhance.Brightness(bg).enhance(0.34)
    bg = ImageEnhance.Color(bg).enhance(0.55)

    leinwand = Image.new("RGB", (W, H), BASE)
    leinwand = Image.blend(leinwand, bg, 0.92).convert("RGBA")

    # --- Kacheln überlappend legen ---------------------------------------
    for name, (x, y), (tw, th), rot, dark in kacheln:
        t = tile(name, tw, th, dark)
        sh, pad = shadow((tw, th))
        if rot:
            t = t.rotate(rot, expand=True, resample=Image.BICUBIC, fillcolor=(0, 0, 0))
            # Nach dem Drehen sind die Ecken schwarz – als Maske mitdrehen.
            m = Image.new("L", (tw, th), 255).rotate(rot, expand=True, resample=Image.BICUBIC, fillcolor=0)
            sh = sh.rotate(rot, expand=True, resample=Image.BICUBIC)
        else:
            m = Image.new("L", (tw, th), 255)
        leinwand.alpha_composite(sh, (x - pad, y - pad))
        leinwand.paste(t, (x, y), m)

    # --- Verlauf nach unten, damit die Schrift trägt ---------------------
    verlauf = Image.new("L", (1, H))
    for y in range(H):
        p = y / (H - 1)
        v = 0 if p < 0.38 else int(238 * ((p - 0.38) / 0.62) ** 1.5)
        verlauf.putpixel((0, y), v)
    verlauf = verlauf.resize((W, H))
    dunkel = Image.new("RGBA", (W, H), BASE + (255,))
    dunkel.putalpha(verlauf)
    leinwand.alpha_composite(dunkel)

    # --- Vignette: zieht den Blick in die Mitte --------------------------
    vig = Image.new("L", (W, H), 0)
    dv  = ImageDraw.Draw(vig)
    dv.ellipse([-W * 0.25, -H * 0.35, W * 1.25, H * 1.35], fill=255)
    # Innen durchsichtig, außen dunkel. Vorher stand hier 255 - v*f, was auch
    # die Bildmitte um ein Drittel abgedunkelt hat.
    vig = vig.filter(ImageFilter.GaussianBlur(120)).point(lambda v: int((255 - v) * 0.62))
    schatten = Image.new("RGBA", (W, H), (0, 0, 0, 255))
    schatten.putalpha(vig)
    leinwand.alpha_composite(schatten)

    # --- Titel ------------------------------------------------------------
    d = ImageDraw.Draw(leinwand)
    f_titel = schrift("800", 92)
    f_unter = schrift("700", 30)

    # Der Block wird von der Unterkante her aufgebaut: Titel, Akzentstrich,
    # Unterzeile. Vorher hing die Unterzeile unter dem Bildrand.
    x0     = 64
    rand   = 54
    hu     = d.textbbox((0, 0), unterzeile, font=f_unter)[3] if unterzeile else 0
    y_unter = H - rand - hu
    y_strich = y_unter - 26
    tb_h   = d.textbbox((0, 0), titel, font=f_titel)[3]
    y0     = y_strich - 22 - tb_h

    d.text((x0, y0), titel, font=f_titel, fill=TEXT)
    # Akzentstrich – das Markenelement, sparsam eingesetzt.
    d.rectangle([x0, y_strich, x0 + 96, y_strich + 5], fill=LEEK)
    if unterzeile:
        d.text((x0, y_unter), unterzeile, font=f_unter, fill=(0x8a, 0x93, 0x8c))

    leinwand.convert("RGB").save(ziel, "WEBP", quality=88, method=6)
    print(f"  geschrieben: {ziel} ({os.path.getsize(ziel)} B)")


REZEPTE = {
    "munition": dict(
        hintergrund="gruppe-mg.webp",
        kacheln=[
            # (Datei, Position, Größe, Drehung, Helligkeit)
            ("wardogs-ak-74.webp",        (36, 58), (352, 198), -4, 0.88),
            ("wardogs-m249-saw.webp",     (596, 44), (352, 198), 4, 0.92),
            ("wardogs-palette-ammo.webp", (316, 128), (392, 220), -2, 1.08),
        ],
        titel="Munition",
        unterzeile="FMJ · Flesh Damage · Armor Piercing",
        name="guide-munition",
    ),
}

if __name__ == "__main__":
    if len(sys.argv) < 2 or sys.argv[1] not in REZEPTE:
        sys.exit("Aufruf: guide-bild-bauen.py <rezept>\nVorhanden: " + ", ".join(REZEPTE))
    r = dict(REZEPTE[sys.argv[1]])
    name = r.pop("name")
    build(ziel=ZIEL + name + ".webp", **r)
