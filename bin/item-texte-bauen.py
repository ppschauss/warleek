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

`##` ist der Slug eines Eintrags, `###` eine eigene Frage.

**Zahlen gehören nicht in den Text.** Preise, Stufen und Gebühren stehen als
Platzhalter da und werden beim Ausliefern aus der Datenbank gelesen:
`{preis}` für den eigenen Eintrag, `{preis:wardogs-field-backpack}` für einen
anderen, `{aufpreis:wardogs-field-backpack}` für die Differenz. Dieses Werkzeug
prüft jeden Platzhalter gegen items.json und bricht ab, wenn einer ins Leere
zeigt – ein `–` mitten im Satz fällt sonst erst auf der Seite auf. Der Text landet im
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


FELDER = ("preis", "freischaltung", "freischaltkosten", "kaliber", "sitze",
          "tempo", "baukosten", "rolle", "hinweis", "titel", "aufpreis")
PLATZHALTER = re.compile(r"\{(" + "|".join(FELDER) + r")(?::([a-z0-9][a-z0-9-]*))?\}")


VERDAECHTIG = re.compile(r"\{[^{}\n]{1,60}\}")


def platzhalter_pruefen(slug, text, items):
    """Zeigt jeder Platzhalter auf einen Wert, den es gibt?"""
    fehler = []
    # Geschweifte Klammern, die kein gueltiger Platzhalter sind, bleiben beim
    # Ausliefern woertlich stehen - ein `{preis:wardogs-havoc/}` mit
    # Schraegstrich faellt sonst erst auf der fertigen Seite auf.
    for treffer in VERDAECHTIG.findall(text):
        if not PLATZHALTER.fullmatch(treffer):
            fehler.append(f"{treffer} – kein gültiger Platzhalter")
    eigen  = items.get(slug, {})
    for feld, ziel in PLATZHALTER.findall(text):
        if ziel and ziel not in items:
            fehler.append(f"{{{feld}:{ziel}}} – diesen Eintrag gibt es nicht")
            continue
        quelle = items.get(ziel) if ziel else eigen
        if feld == "titel":
            continue
        if feld == "aufpreis":
            # Dollar gegen Dollar oder Build-Supplies gegen Build-Supplies –
            # eine Differenz aus beidem wäre eine Zahl ohne Bedeutung.
            if not ziel:
                fehler.append("{aufpreis} braucht einen Slug")
            else:
                a, b = (eigen.get("felder") or {}), (quelle.get("felder") or {})
                passt = any(a.get(art) and b.get(art) for art in ("preis", "baukosten"))
                if not passt:
                    fehler.append(f"{{aufpreis:{ziel}}} – kein gemeinsamer Wert "
                                  f"(beide brauchen Preis oder beide Baukosten)")
            continue
        if not str((quelle.get("felder") or {}).get(feld, "")).strip():
            wo = ziel or slug
            fehler.append(f"{{{feld}{':' + ziel if ziel else ''}}} – {wo} hat kein Feld „{feld}“")
    return fehler


DB_LINK = re.compile(r"\]\(/datenbank/([a-z0-9-]+)/\)")
GUIDE_LINK = re.compile(r"\]\(/guides/([a-z0-9-]+)/\)")


def links_pruefen(text, items, guides):
    """Zeigt jeder Querverweis auf etwas, das es gibt?

    Die Texte verlinken sich kreuz und quer – das ist der Sinn der Sache, aber
    ein Tippfehler im Slug faellt sonst erst auf der Seite auf, und zwar als
    404. Guides werden gegen guides.json geprueft, Eintraege gegen items.json.
    """
    fehler = []
    for ziel in DB_LINK.findall(text):
        if ziel not in items:
            fehler.append(f"/datenbank/{ziel}/ – diesen Eintrag gibt es nicht")
    for ziel in GUIDE_LINK.findall(text):
        if guides and ziel not in guides:
            fehler.append(f"/guides/{ziel}/ – diesen Guide gibt es nicht")
    return fehler


def guide_slugs():
    datei = WURZEL / "plugin/warleek-core/content/_content/guides.json"
    if not datei.exists():
        return set()
    return {g.get("slug") for g in json.loads(datei.read_text(encoding="utf-8"))}


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
    guides    = guide_slugs()
    unbekannt, geschrieben, kaputt = [], [], []

    for datei in dateien:
        for slug, d in lies(datei).items():
            if slug not in nach_slug:
                unbekannt.append((datei.name, slug))
                continue
            alles = "\n".join([d["text"]] + [f["antwort"] for f in d["fragen"]])
            for fehler in platzhalter_pruefen(slug, alles, nach_slug):
                kaputt.append((slug, fehler))
            for fehler in links_pruefen(alles, nach_slug, guides):
                kaputt.append((slug, fehler))
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

    if kaputt:
        print("FEHLER: Platzhalter oder Link zeigt ins Leere:", file=sys.stderr)
        for slug, fehler in kaputt:
            print(f"  {slug}: {fehler}", file=sys.stderr)
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
