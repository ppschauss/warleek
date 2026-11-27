#!/usr/bin/env python3
"""Jedem Datenbank-Eintrag eine Gruppe geben und die Vergleichsseiten bauen.

    python3 bin/gruppen-setzen.py [--trocken]

Schreibt zwei Dateien: die Gruppe an jedem Eintrag in `items.json` und – aus
`content-src/items/gruppen/<slug>.md` – die Vergleichsseiten nach
`content/_content/gruppen.json`. Aus jeder Gruppe wird ein Begriff der
Taxonomie `item-gruppe` mit eigener Seite unter `/datenbank-gruppe/<slug>/`.

Aufbau einer Gruppendatei:

    titel: Wardogs Rucksäcke im Vergleich
    beschreibung: Neun Rucksäcke von … – welcher sich wofür lohnt.

    Fließtext in Markdown.

`titel` wird die Überschrift der Seite, `beschreibung` die Kurzfassung für
Suchergebnisse, der Rest der geschriebene Teil unter der Tabelle.

Die Gruppe ist die Achse, auf der später die Vergleichsseiten stehen: „Wardogs
Pistolen im Vergleich“ ist eine Seite, die fünf dünne Einzelseiten ersetzt.
Deshalb ist jede Gruppe so geschnitten, dass sich ihre Mitglieder tatsächlich
vergleichen lassen – gleiche Aufgabe, gleiche Entscheidung beim Kauf.

Bis hierher trug `gruppe` nur die Bildauswahl (`item-gruppe-<name>` im
Installer) und 65 Einträge hatten gar keine. Was hier zugewiesen wird, ist
bewusst feiner als `typ`: Unter `waffe` stehen Pistole und Scharfschützengewehr
nebeneinander, und wer eine Pistole kauft, vergleicht sie nicht mit einer AMR 50.

Umgezogen gegenüber dem alten Stand:
* PP-19 Bizon: `sturmgewehr` → `maschinenpistole` (es ist eine MP).
* MG Nest: `mg` → `stellung` (gebaut, nicht getragen).
* Molotov: `wurfwaffe` → bleibt, aber die Gruppe bekommt die Handgranaten dazu;
  `sprengstoff` behält, was gelegt und gezündet wird.
* Rauch- und Signalgranaten werden eine Gruppe – es ist dieselbe M18.
"""
import json, re, sys
from pathlib import Path
from collections import Counter

WURZEL   = Path(__file__).resolve().parent.parent
DATEI    = WURZEL / "plugin/warleek-core/content/_content/items.json"
GRUPPEN  = WURZEL / "plugin/warleek-core/content/_content/gruppen.json"
TEXTE    = WURZEL / "content-src/items/gruppen"

# Gruppe -> Titel. Was hier nicht steht, behält seine bisherige Gruppe.
ZUORDNUNG = {
    "sturmgewehr":      ["A-91", "AK-74", "Bushmaster M17S", "FAL", "Galil", "KH-2002",
                         "M4", "T-21", "TCX-SPR"],
    "maschinenpistole": ["AMP-9", "MP5", "PP-19 Bizon", "PP-19 Vityaz", "Super-45"],
    "schrotflinte":     ["M12G", "M500", "MP43"],
    "pistole":          ["Deagle", "GGX 17", "GGX 18", "Judge", "M1911"],
    "scharfschuetze":   ["AMR 50", "BMR-308", "Compound Bow", "M14", "MK22", "Mosin Nagant",
                         "SKS", "SV-98", "SVD", "Scout Rifle TD"],
    "mg":               ["Evo-3 Ultra", "M249 SAW", "PKM"],
    "panzerabwehr":     ["MAAWS", "MGL-40", "RPG-7"],
    "flugabwehr":       ["9K333 Verba", "Talon 9K-SAM", "Vanguard CIWS",
                         "Duel 35mm Oerlikon GDF Cannons"],
    "wurfwaffe":        ["Blendgranate", "Goldene Splittergranate", "HE-Granate",
                         "M67 Splittergranate", "Molotov"],
    "sprengstoff":      ["AT-Mine", "C4-Ladung", "Claymore", "Fernzünder",
                         "Unkonventionelle Sprengvorrichtung"],
    "signal":           ["M18 Rauchgranate: Schwarz", "M18 Rauchgranate: Weiß",
                         "M18 Signalgranate: Alarm", "M18 Signalgranate: Fahrzeug beschädigt",
                         "M18 Signalgranate: Feind", "M18 Signalgranate: Freund",
                         "M18 Signalgranate: Landezone", "M18 Signalgranate: Nachschub"],
    "fahrzeugwaffe":    ["2A42 Autocannon", "B 13 Rocket Pods", "L52 Cannon", "L55A1 Cannon",
                         "M134D Minigun", "M249 Machine Gun", "MG3A1 Coaxial Gun"],
    "stellung":         ["L81 Mörser", "Loudspeaker", "MG Nest", "Stingray"],
    "transporter":      ["Bobcat", "Dune Buggy", "Humvee", "Humvee [M249]", "Humvee [Minigun]",
                         "Kodiak", "Kodiak [M249]", "Kodiak [Pickup]", "M113 APC SV"],
    "bauwerkzeug":      ["Großer Hammer", "Kleiner Hammer", "Mittlerer Hammer"],
    "werkzeug":         ["Halligan Bar", "Kanister", "Leichter Bohrer", "Schraubenschlüssel",
                         "Schwerer Bohrer", "Wrench"],
    "fob-anlage":       ["Bauradio", "Drill Rig", "Forward Operating Base", "Munitionskiste",
                         "Mörser-Unterstand", "Recon-Turm", "Recon-Zelt", "Reparaturstation",
                         "Tankstation"],
    "befestigung":      ["Bremer Wall", "HBlock", "HESCO-Block", "HESCO-Block (groß)",
                         "HESCO-Wand", "Hedgehog", "Sandsäcke", "Stacheldraht"],
    "bunker":           ["Bunker", "Bunkerboden", "Bunkerdach", "Large Shelter", "Tor",
                         "Tür", "Wooden Step"],
}

# Nur zur Anzeige und für die späteren Vergleichsseiten.
NAMEN = {
    "anbauteil": "Anbauteile", "befestigung": "Befestigungen und Sperren",
    "bauwerkzeug": "Bauhämmer", "bunker": "Bunker und Unterstände",
    "fahrzeugwaffe": "Fahrzeug- und Helikopterwaffen", "fallschirm": "Fallschirme",
    "flugabwehr": "Flugabwehr", "fob-anlage": "FOB-Anlagen", "helikopter": "Helikopter",
    "kiste": "Kisten", "lkw": "LKW und Logistikfahrzeuge", "maschinenpistole": "Maschinenpistolen",
    "medizin": "Medizin", "mg": "Maschinengewehre", "munition": "Munitionssorten",
    "optik": "Optiken", "palette": "Paletten", "panzer": "Panzer und schwere Fahrzeuge",
    "panzerabwehr": "Panzerabwehr", "panzerung": "Panzerung und Helme", "pistole": "Pistolen",
    "rucksack": "Rucksäcke", "scharfschuetze": "Präzisionswaffen", "schrotflinte": "Schrotflinten",
    "signal": "Rauch- und Signalgranaten", "sprengstoff": "Sprengstoff und Minen",
    "stellung": "Gebaute Stellungen", "sturmgewehr": "Sturmgewehre", "tarnung": "Tarnung",
    "transporter": "Transportfahrzeuge", "werkzeug": "Werkzeuge", "weste": "Westen und Taschen",
    "wurfwaffe": "Handgranaten und Wurfwaffen",
}


def gruppentexte(zaehler):
    """Die Vergleichsseiten aus content-src/items/gruppen/ einlesen."""
    raus, fehlend = [], []
    for gruppe in sorted(zaehler):
        datei = TEXTE / f"{gruppe}.md"
        if not datei.exists():
            fehlend.append(gruppe)
            continue
        roh = datei.read_text(encoding="utf-8")
        kopf, _, rumpf = roh.partition("\n\n")
        felder = {}
        for zeile in kopf.splitlines():
            if ":" in zeile:
                k, _, v = zeile.partition(":")
                felder[k.strip()] = v.strip()
        raus.append({
            "slug":         gruppe,
            "name":         NAMEN.get(gruppe, gruppe),
            "titel":        felder.get("titel", f"Wardogs {NAMEN.get(gruppe, gruppe)}"),
            "beschreibung": felder.get("beschreibung", ""),
            "text":         rumpf.strip(),
        })
    return raus, fehlend


FELDER = ("preis", "freischaltung", "freischaltkosten", "kaliber", "sitze",
          "tempo", "baukosten", "rolle", "hinweis", "titel", "aufpreis")
PLATZHALTER = re.compile(r"\{(" + "|".join(FELDER) + r")(?::([a-z0-9][a-z0-9-]*))?\}")
VERDAECHTIG = re.compile(r"\{[^{}\n]{1,60}\}")
DB_LINK     = re.compile(r"\]\(/datenbank/([a-z0-9-]+)/\)")
GRUPPE_LINK = re.compile(r"\]\(/datenbank-gruppe/([a-z0-9-]+)/\)")
GUIDE_LINK  = re.compile(r"\]\(/guides/([a-z0-9-]+)/\)")


def seiten_pruefen(seiten, items, gruppen, guides):
    """Platzhalter und Querverweise der Vergleichsseiten prüfen.

    Ein Platzhalter ohne Slug hätte hier keinen Bezug – eine Vergleichsseite
    gehört zu keinem einzelnen Eintrag. Deshalb ist er auf diesen Seiten immer
    ein Fehler, anders als im Text eines Eintrags.
    """
    fehler = []
    for s in seiten:
        text = s["titel"] + "\n" + s["beschreibung"] + "\n" + s["text"]
        for treffer in VERDAECHTIG.findall(text):
            if not PLATZHALTER.fullmatch(treffer):
                fehler.append((s["slug"], f"{treffer} – kein gültiger Platzhalter"))
        for feld, ziel in PLATZHALTER.findall(text):
            if not ziel:
                fehler.append((s["slug"], f"{{{feld}}} ohne Slug – hier gibt es keinen eigenen Eintrag"))
            elif ziel not in items:
                fehler.append((s["slug"], f"{{{feld}:{ziel}}} – diesen Eintrag gibt es nicht"))
            elif feld not in ("titel", "aufpreis") and not str((items[ziel].get("felder") or {}).get(feld, "")).strip():
                fehler.append((s["slug"], f"{{{feld}:{ziel}}} – {ziel} hat kein Feld „{feld}“"))
        for ziel in DB_LINK.findall(text):
            if ziel not in items:
                fehler.append((s["slug"], f"/datenbank/{ziel}/ – diesen Eintrag gibt es nicht"))
        for ziel in GRUPPE_LINK.findall(text):
            if ziel not in gruppen:
                fehler.append((s["slug"], f"/datenbank-gruppe/{ziel}/ – diese Gruppe gibt es nicht"))
        for ziel in GUIDE_LINK.findall(text):
            if guides and ziel not in guides:
                fehler.append((s["slug"], f"/guides/{ziel}/ – diesen Guide gibt es nicht"))
    return fehler


def guide_slugs():
    datei = WURZEL / "plugin/warleek-core/content/_content/guides.json"
    if not datei.exists():
        return set()
    return {g.get("slug") for g in json.loads(datei.read_text(encoding="utf-8"))}


def main():
    trocken = "--trocken" in sys.argv
    roh   = DATEI.read_text(encoding="utf-8")
    items = json.loads(roh)

    nach_titel = {}
    for gruppe, titel in ZUORDNUNG.items():
        for t in titel:
            if t in nach_titel:
                print(f"FEHLER: „{t}“ steht in zwei Gruppen "
                      f"({nach_titel[t]} und {gruppe})", file=sys.stderr)
                return 1
            nach_titel[t] = gruppe

    bekannt = {i["title"] for i in items}
    unbekannt = sorted(set(nach_titel) - bekannt)
    if unbekannt:
        print("FEHLER: Titel aus der Zuordnung gibt es nicht:", file=sys.stderr)
        for t in unbekannt:
            print(f"  - {t}", file=sys.stderr)
        return 1

    geaendert = []
    for i in items:
        neu = nach_titel.get(i["title"])
        if neu and i.get("gruppe") != neu:
            geaendert.append((i["title"], i.get("gruppe") or "–", neu))
            i["gruppe"] = neu

    ohne = [i["title"] for i in items if not (i.get("gruppe") or "").strip()]

    print(f"{len(geaendert)} Einträge umgruppiert, {len(ohne)} ohne Gruppe\n")
    for t, alt, neu in geaendert:
        print(f"  {t:34} {alt:16} -> {neu}")
    if ohne:
        print("\nOHNE GRUPPE:", ", ".join(ohne))

    zaehler = Counter(i.get("gruppe") or "–" for i in items)
    print(f"\n{len(zaehler)} Gruppen:")
    for g, n in sorted(zaehler.items()):
        print(f"  {n:3}  {g:18} {NAMEN.get(g, '(ohne Namen)')}")

    seiten, fehlend = gruppentexte(zaehler)
    print(f"\n{len(seiten)} Vergleichsseiten mit Text"
          + (f", {len(fehlend)} ohne: {', '.join(fehlend)}" if fehlend else ""))
    for s in seiten:
        w = len(re.sub(r"\s+", " ", s["text"]).split())
        print(f"  {s['slug']:18} {w:4} Wörter  {s['titel']}")

    kaputt = seiten_pruefen(seiten, {i["slug"]: i for i in items},
                            set(zaehler), guide_slugs())
    if kaputt:
        print("\nFEHLER: Platzhalter oder Link zeigt ins Leere:", file=sys.stderr)
        for slug, f in kaputt:
            print(f"  {slug}: {f}", file=sys.stderr)
        return 1

    if not trocken and not ohne:
        DATEI.write_text(json.dumps(items, ensure_ascii=False, indent=4) + "\n",
                         encoding="utf-8")
        GRUPPEN.write_text(json.dumps(seiten, ensure_ascii=False, indent=1) + "\n",
                           encoding="utf-8")
        print("\ngeschrieben:", DATEI)
        print("geschrieben:", GRUPPEN)
    return 0


if __name__ == "__main__":
    sys.exit(main())
