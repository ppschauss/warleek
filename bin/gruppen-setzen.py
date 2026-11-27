#!/usr/bin/env python3
"""Jedem Datenbank-Eintrag eine Gruppe geben.

    python3 bin/gruppen-setzen.py [--trocken]

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
import json, sys
from pathlib import Path
from collections import Counter

DATEI = Path(__file__).resolve().parent.parent / "plugin/warleek-core/content/_content/items.json"

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

    if not trocken and not ohne:
        DATEI.write_text(json.dumps(items, ensure_ascii=False, indent=4) + "\n",
                         encoding="utf-8")
        print("\ngeschrieben:", DATEI)
    return 0


if __name__ == "__main__":
    sys.exit(main())
