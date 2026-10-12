#!/usr/bin/env python3
"""Warleek — Banner und Symbol für r/WardogsDeutsch.

Erzeugt drei Dateien in den Größen, die Reddit in der Community-Verwaltung
verlangt:

    reddit-banner-desktop.png   1920×576   (10:3, wie im Reddit-Dialog angegeben)
    reddit-banner-mobil.png     1280×384   (10:3, Anzeige in der App)
    reddit-symbol.png            512×512   (wird rund beschnitten)

Zwei Dinge, die das Layout bestimmen:

1. **Unten links liegt in der App das Community-Symbol über dem Banner.**
   Deshalb steht dort nichts – der Text sitzt oben links, die Marke unten
   rechts.
2. **Das Symbol wird rund beschnitten.** Der Dogtag bekommt deshalb reichlich
   Luft zum Rand, sonst schneidet Reddit die Kette ab.

Hintergrund ist offizielles Bildmaterial aus der Season-02-Ankündigung,
Schrift ist die Hausschrift (Barlow Condensed).

Aufruf: python3 bin/reddit-assets.py
"""
import os
import sys
from PIL import Image, ImageDraw, ImageFilter, ImageEnhance, ImageFont

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
LOGO = os.path.join(ROOT, "assets-src", "logo")
FONTS = os.path.join(ROOT, ".cache-fonts") + os.sep
OUT = os.path.join(ROOT, "dist", "reddit") + os.sep

BASE = (0x0d, 0x11, 0x0f)
LEEK = (0x9b, 0xe1, 0x5d)
TEXT = (0xe6, 0xea, 0xe4)
STEEL = (0x8a, 0x93, 0x8c)


def schrift(gewicht, groesse):
    """Hausschrift laden, bei Bedarf einmalig von WOFF2 nach TTF wandeln."""
    os.makedirs(FONTS, exist_ok=True)
    ttf = os.path.join(FONTS, "barlow-condensed-%s.ttf" % gewicht)
    if not os.path.exists(ttf):
        woff = os.path.join(ROOT, "theme", "warleek", "assets", "fonts",
                            "barlow-condensed-latin-%s.woff2" % gewicht)
        try:
            from fontTools.ttLib import TTFont
        except ImportError:
            sys.exit("fontTools fehlt: pip install fonttools brotli")
        try:
            f = TTFont(woff)
        except ImportError:
            sys.exit("Zum Lesen der WOFF2-Schrift fehlt brotli: pip install brotli")
        f.flavor = None
        f.save(ttf)
    return ImageFont.truetype(ttf, groesse)


def cover(im, w, h):
    r = max(w / im.width, h / im.height)
    im = im.resize((max(1, round(im.width * r)), max(1, round(im.height * r))), Image.LANCZOS)
    x = (im.width - w) // 2
    y = (im.height - h) // 2
    return im.crop((x, y, x + w, y + h))


def hintergrund(quelle, w, h):
    im = Image.open(quelle).convert("RGB")
    im = cover(im, w, h)
    im = im.filter(ImageFilter.GaussianBlur(max(2, w // 500)))
    im = ImageEnhance.Brightness(im).enhance(0.40)
    im = ImageEnhance.Color(im).enhance(0.60)
    leinwand = Image.new("RGB", (w, h), BASE)
    return Image.blend(leinwand, im, 0.9).convert("RGBA")


def banner(quelle, ziel, w, h, gross, klein, zeile):
    bild = hintergrund(quelle, w, h)

    # Verlauf von links: trägt die Schrift, ohne das Bild zuzudecken.
    verlauf = Image.new("L", (w, 1))
    for x in range(w):
        p = x / (w - 1)
        verlauf.putpixel((x, 0), int(225 * max(0.0, 1.0 - p / 0.72) ** 1.25))
    dunkel = Image.new("RGBA", (w, h), BASE + (255,))
    dunkel.putalpha(verlauf.resize((w, h)))
    bild.alpha_composite(dunkel)

    d = ImageDraw.Draw(bild)
    rand = int(w * 0.045)
    f_gross = schrift("800", gross)
    f_klein = schrift("700", klein)

    y = int(h * 0.20)
    d.text((rand, y), "WARDOGS", font=f_gross, fill=TEXT)
    hg = d.textbbox((0, 0), "WARDOGS", font=f_gross)[3]
    d.text((rand, y + hg + int(h * 0.02)), "AUF DEUTSCH", font=f_gross, fill=LEEK)

    yl = y + 2 * hg + int(h * 0.10)
    d.rectangle([rand, yl, rand + int(w * 0.055), yl + max(3, h // 150)], fill=LEEK)
    d.text((rand, yl + int(h * 0.055)), zeile, font=f_klein, fill=STEEL)

    # Marke unten rechts – unten links liegt in der App das Symbol darüber.
    # Das fertige Lockup ist **nicht** transparent (Hintergrund exakt BASE) und
    # zeigt auf dem Bild einen Kasten. Deshalb hier aus dem transparenten
    # Dogtag plus Hausschrift gesetzt – und bewusst klein: Ein Community-Banner
    # ist kein Werbeplatz.
    tag = Image.open(os.path.join(LOGO, "warleek-icon.png")).convert("RGBA")
    th = int(h * 0.17)
    tag = tag.resize((round(tag.width * th / tag.height), th), Image.LANCZOS)
    f_marke = schrift("800", int(h * 0.075))
    wort = "warleek.de"
    bw = d.textbbox((0, 0), wort, font=f_marke)[2]
    gesamt = tag.width + int(w * 0.008) + bw
    mx = w - rand - gesamt
    my = h - rand - th
    bild.alpha_composite(tag, (mx, my))
    d = ImageDraw.Draw(bild)
    d.text((mx + tag.width + int(w * 0.008), my + (th - int(h * 0.075)) // 2 - int(h * 0.012)),
           wort, font=f_marke, fill=STEEL)

    os.makedirs(os.path.dirname(ziel), exist_ok=True)
    bild.convert("RGB").save(ziel, "PNG", optimize=True)
    print("  %-34s %dx%d  %d B" % (os.path.basename(ziel), w, h, os.path.getsize(ziel)))


def symbol(ziel, groesse=512):
    bild = Image.new("RGBA", (groesse, groesse), BASE + (255,))
    d = ImageDraw.Draw(bild)
    # Sehr dezenter Ring: Bei 32 px in der Sidebar-Liste darf er die Marke nicht
    # überstrahlen – vorher war er lauter als der Dogtag selbst.
    d.ellipse([4, 4, groesse - 5, groesse - 5], outline=(0x2a, 0x33, 0x2c), width=max(2, groesse // 110))

    tag = Image.open(os.path.join(LOGO, "warleek-icon.png")).convert("RGBA")
    # Rund beschnitten, aber der Dogtag ist hoch und schmal – er darf deshalb
    # fast die volle Höhe nutzen, ohne dass die Kette abgeschnitten wird.
    innen = int(groesse * 0.80)
    r = min(innen / tag.width, innen / tag.height)
    tag = tag.resize((round(tag.width * r), round(tag.height * r)), Image.LANCZOS)
    bild.alpha_composite(tag, ((groesse - tag.width) // 2, (groesse - tag.height) // 2))

    os.makedirs(os.path.dirname(ziel), exist_ok=True)
    bild.convert("RGB").save(ziel, "PNG", optimize=True)
    print("  %-34s %dx%d  %d B" % (os.path.basename(ziel), groesse, groesse, os.path.getsize(ziel)))


if __name__ == "__main__":
    quelle = os.path.join(ROOT, "plugin", "warleek-core", "content", "img", "guide-s2-regen.webp")
    if not os.path.exists(quelle):
        sys.exit("Hintergrundbild fehlt: " + quelle)

    zeile = "Guides · Taktik · Patch Notes · Datenbank"
    banner(quelle, OUT + "reddit-banner-desktop.png", 1920, 576, 128, 34, zeile)
    banner(quelle, OUT + "reddit-banner-mobil.png", 1280, 384, 86, 24, zeile)
    symbol(OUT + "reddit-symbol.png")
    print("\nLiegt in dist/reddit/ – Reddit skaliert selbst, 10:3 ist das Anzeigeverhältnis.")
