#!/usr/bin/env python3
"""Freigestellte Gegenstandsbilder ins Hausformat bringen.

    python3 bin/item-bild-einpassen.py <quelle.png> <ziel-slug> [...]

Die Eintragsbilder der Datenbank sind 1024×576 (16:9) ohne Transparenz –
so erwartet sie das Kartenraster. Freigestellte Symbole aus dem Spiel kommen
dagegen in beliebigen Formaten mit Alphakanal.

Dieses Skript legt sie mittig auf eine Fläche in der Flächenfarbe des Themes
(`--wp--preset--color--surface-2`, #1f2822) und speichert als WebP nach
`content-src/items/img/<slug>.webp`.

**Warum nicht transparent lassen.** WebP kann Alpha, und das Theme ist dunkel –
es würde also zunächst passen. Aber dieselbe Datei landet im Open-Graph-Bild,
in der Mediathek und in Vorschauen, die einen weißen Hintergrund annehmen.
Dort stünde ein schwarzer Helm auf Weiß und wäre kaum zu erkennen. Eine feste
Fläche sieht überall gleich aus.
"""
import sys
from pathlib import Path
from PIL import Image

WURZEL  = Path(__file__).resolve().parent.parent
ZIEL    = WURZEL / "content-src/items/img"
BREITE, HOEHE = 1024, 576
FLAECHE = (31, 40, 34)       # #1f2822 – surface-2 des Themes
RAND    = 0.86               # Anteil der Fläche, den das Motiv höchstens füllt


def einpassen(quelle: Path, slug: str) -> Path:
    bild = Image.open(quelle).convert("RGBA")

    # Auf den sichtbaren Inhalt zuschneiden: Die Symbole haben oft großzügige
    # transparente Ränder, und ohne Zuschnitt sitzt das Motiv winzig in der Mitte.
    kasten = bild.getbbox()
    if kasten:
        bild = bild.crop(kasten)

    faktor = min(BREITE * RAND / bild.width, HOEHE * RAND / bild.height)
    neu    = bild.resize((max(1, round(bild.width * faktor)),
                          max(1, round(bild.height * faktor))), Image.LANCZOS)

    flaeche = Image.new("RGBA", (BREITE, HOEHE), FLAECHE + (255,))
    flaeche.alpha_composite(neu, ((BREITE - neu.width) // 2, (HOEHE - neu.height) // 2))

    ziel = ZIEL / f"{slug}.webp"
    flaeche.convert("RGB").save(ziel, "WEBP", quality=88, method=6)
    return ziel


def main(argv):
    if len(argv) < 2 or len(argv) % 2:
        print(__doc__)
        return 1
    for quelle, slug in zip(argv[0::2], argv[1::2]):
        p = Path(quelle)
        if not p.exists():
            print(f"fehlt: {quelle}", file=sys.stderr)
            return 1
        ziel = einpassen(p, slug)
        print(f"  {slug:34} {ziel.stat().st_size:7} Bytes")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
