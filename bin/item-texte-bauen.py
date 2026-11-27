#!/usr/bin/env python3
"""Geschriebene Eintragstexte aus `content-src/items/texte/` in items.json legen.

    python3 bin/item-texte-bauen.py [gruppe …] [--trocken]

Je Gruppe eine Markdown-Datei, damit sich Texte lesen und überarbeiten lassen,
ohne in einer 200.000 Zeichen langen JSON-Datei zu suchen. Ohne Argument werden
alle Dateien eingelesen.

Aufbau einer Datei:

    ## wardogs-scout-rucksack
    Fließtext, Absätze durch Leerzeilen getrennt.

    ### Lohnt sich der Aufpreis zum Field-Rucksack?
    Antwort in einem Absatz.

`##` ist der Slug eines Eintrags, `###` eine eigene Frage. Der Text landet im
Feld `text`, die Fragen in `fragen` – von dort hängt sie der Installer hinten
an die drei Fragen, die ohnehin aus den Feldern entstehen.

**Überschrieben wird nur, was hier steht.** Einträge ohne Datei behalten ihren
alten Text; das Werkzeug läuft über Wochen immer wieder.
"""
import json, re, sys
from pathlib import Path

WURZEL = Path(__file__).resolve().parent.parent
ITEMS  = WURZEL / "plugin/warleek-core/content/_content/items.json"
TEXTE  = WURZEL / "content-src/items/texte"


def lies(datei):
    """Eine Gruppendatei in {slug: {"text":…, "fragen":[…]}} zerlegen."""
    roh = datei.read_text(encoding="utf-8")
    raus, slug, teil, frage = {}, None, [], None

    def schliesse():
        if slug is None:
            return
        if frage is not None:
            raus[slug]["fragen"].append({"frage": frage,
                                         "antwort": "\n".join(teil).strip()})
        else:
            raus[slug]["text"] = "\n".join(teil).strip()

    for zeile in roh.splitlines():
        if zeile.startswith("## "):
            schliesse()
            slug, teil, frage = zeile[3:].strip(), [], None
            raus[slug] = {"text": "", "fragen": []}
        elif zeile.startswith("### "):
            schliesse()
            frage, teil = zeile[4:].strip(), []
        else:
            teil.append(zeile)
    schliesse()
    return {s: d for s, d in raus.items() if d["text"] or d["fragen"]}


def woerter(t):
    t = re.sub(r"\s+", " ", t).strip()
    return len(t.split()) if t else 0


def main():
    args    = [a for a in sys.argv[1:] if not a.startswith("--")]
    trocken = "--trocken" in sys.argv
    dateien = [TEXTE / f"{a}.md" for a in args] if args else sorted(TEXTE.glob("*.md"))

    fehlt = [d for d in dateien if not d.exists()]
    if fehlt:
        print("Keine Datei: " + ", ".join(str(d) for d in fehlt), file=sys.stderr)
        return 1

    items     = json.loads(ITEMS.read_text(encoding="utf-8"))
    nach_slug = {i["slug"]: i for i in items}
    unbekannt, geschrieben = [], []

    for datei in dateien:
        for slug, d in lies(datei).items():
            if slug not in nach_slug:
                unbekannt.append((datei.name, slug))
                continue
            i = nach_slug[slug]
            if d["text"]:
                i["text"] = d["text"]
            if d["fragen"]:
                i["fragen"] = d["fragen"]
            # Gezählt wird, was auf der Seite steht: Fließtext und Antworten.
            # Die Fragen selbst nicht – sie sind Überschriften.
            umfang = woerter(d["text"]) + sum(woerter(f["antwort"]) for f in d["fragen"])
            geschrieben.append((datei.stem, slug, umfang, len(d["fragen"])))

    if unbekannt:
        print("FEHLER: Slug gibt es nicht in items.json:", file=sys.stderr)
        for datei, slug in unbekannt:
            print(f"  {datei}: {slug}", file=sys.stderr)
        return 1

    print(f"{len(geschrieben)} Einträge aus {len(dateien)} Datei(en)\n")
    gruppe = None
    for g, slug, w, f in geschrieben:
        if g != gruppe:
            print(f"  [{g}]")
            gruppe = g
        warnung = "  ← kurz" if w < 150 else ("  ← lang" if w > 320 else "")
        print(f"    {slug:38} {w:4} Wörter, {f} eigene Frage(n){warnung}")

    if not trocken:
        ITEMS.write_text(json.dumps(items, ensure_ascii=False, indent=4) + "\n",
                         encoding="utf-8")
        print("\ngeschrieben:", ITEMS)
    return 0


if __name__ == "__main__":
    sys.exit(main())
