#!/usr/bin/env python3
"""Die Datenbank gegen sich selbst und gegen das Faktenblatt prüfen.

    python3 bin/datenbank-pruefen.py [> docs/pruefliste-datenbank.md]

Prüft `plugin/warleek-core/content/_content/items.json` auf das, was sich ohne
das Spiel prüfen lässt:

* **Innere Widersprüche** – eine Zahl im Fließtext, die dem Datenblatt desselben
  Eintrags widerspricht. Das ist der einzige Fehler, bei dem feststeht, dass
  einer von beiden falsch ist.
* **Abgleich mit dem Faktenblatt** (`docs/research/wardogs-facts.md`,
  Freischaltstufen und -gebühren). Beide Seiten sind Community-Angaben; eine
  Abweichung sagt nicht, wer recht hat, sondern dass jemand nachsehen muss.
* **Vertauschungsverdacht** – wenn Eintrag A genau die Werte trägt, die das
  Faktenblatt für Eintrag B nennt, und umgekehrt. Dann ist es kein
  Patch-Unterschied, sondern ein Übertragungsfehler.
* **Lücken** – Pflichtfelder je Typ, fehlende Gruppe, fehlender Text.

* **Abgleich mit der lebenden Seite** – `--live` lädt `https://warleek.de/herkunft.json`
  und vergleicht Bestand und Werte mit dieser Datei. Was im Backend entstanden ist,
  steht in keinem Commit; ohne diesen Abgleich pflegt man an der Wirklichkeit vorbei.
  Mit `--live <datei>` wird eine bereits geladene Fassung gelesen.

Was das Werkzeug *nicht* kann: entscheiden, welche Angabe stimmt. Dafür
braucht es den Laden im Spiel. Die Ausgabe ist eine Prüfliste, keine
Korrekturanweisung.
"""
import json, re, sys, unicodedata
from pathlib import Path
from collections import defaultdict

WURZEL = Path(__file__).resolve().parent.parent
ITEMS  = WURZEL / "plugin/warleek-core/content/_content/items.json"
FAKTEN = WURZEL / "docs/research/wardogs-facts.md"

# Pflichtfelder je Typ. Bewusst knapp: Was hier steht, muss jeder Eintrag
# dieses Typs tragen, sonst fehlt der Tabelle ihre Aussage.
PFLICHT = {
    "waffe":       ["preis", "freischaltung", "rolle"],
    "fahrzeug":    ["preis", "rolle"],
    "munition":    ["rolle"],
    "ausruestung": ["rolle"],
    "bauwerk":     ["rolle"],
    "emplacement": ["rolle"],
    "wurfwaffe":   ["rolle"],
    "anbauteil":   ["rolle"],
}

# Fahrzeugpreise, die die Season-03-Patch-Notes vom 11.11.2026 ausdrücklich
# ändern. Das ist die einzige Preisquelle mit Beleg – alles andere stammt aus
# Community-Datenbanken.
S3_PREISE = {
    "Wolf-110 [M2 Browning]": 6800,
    "Kodiak [M249]":          6250,
    "AH-6M [Miniguns]":       10480,
    "Havoc":                  26000,
}


def norm(s: str) -> str:
    s = unicodedata.normalize("NFKD", str(s).lower())
    return re.sub(r"[^a-z0-9]", "", s)


def zahl(v):
    """Geldbetrag aus einem Feldwert. Gibt None zurück, wenn keine Zahl drinsteht."""
    ziffern = re.sub(r"[^\d]", "", str(v))
    return int(ziffern) if ziffern else None


def faktenblatt():
    """Freischaltstufen und -gebühren aus dem Faktenblatt lesen."""
    text = FAKTEN.read_text(encoding="utf-8")
    raus = {}
    for zeile in re.findall(r"Freischaltgebühren \w+: (.+?)\.\n", text, re.S):
        for teil in zeile.split("·"):
            m = re.match(
                r"\s*(.+?)\s+(Assault|Medic|Recon|Support|Driver|Pilot|Wardog|Career)\s+(\d+)\s+\(\$([\d.]+)",
                teil,
            )
            if m:
                raus[norm(m.group(1))] = {
                    "name":    m.group(1).strip(),
                    "stufe":   f"{m.group(2)} {m.group(3)}",
                    "gebuehr": int(m.group(4).replace(".", "")),
                }
    return raus


def innere_widersprueche(i):
    """Zahlen im Fließtext gegen das Datenblatt desselben Eintrags."""
    text   = i.get("text") or ""
    felder = i.get("felder") or {}
    funde  = []

    preis = zahl(felder.get("preis"))
    if preis:
        # Beträge im Text, die wie ein Stückpreis aussehen (nicht die Gebühr).
        betraege = {int(b.replace(".", "").replace(",", ""))
                    for b in re.findall(r"\$\s?([\d.,]+)", text)}
        gebuehr  = zahl(felder.get("freischaltkosten"))
        fremd    = [b for b in betraege if b not in (preis, gebuehr) and b >= 100]
        if fremd and preis not in betraege:
            funde.append(f"Text nennt {', '.join('$'+format(b,',d').replace(',','.') for b in sorted(fremd))}, "
                         f"das Datenblatt $" + format(preis, ",d").replace(",", "."))

    stufe = (felder.get("freischaltung") or "").strip()
    if stufe:
        m = re.match(r"(Assault|Medic|Recon|Support|Driver|Pilot|Wardog|Career)\s+(\d+)", stufe)
        if m:
            im_text = re.findall(rf"{m.group(1)}\s+(\d+)", text)
            abweich = {x for x in im_text if x != m.group(2)}
            if abweich:
                funde.append(f"Text nennt {m.group(1)} {', '.join(sorted(abweich))}, "
                             f"das Datenblatt {stufe}")
    return funde


def live_bestand(quelle):
    """Den veröffentlichten Bestand aus dem Herkunftsnachweis lesen.

    `/herkunft.json` entsteht bei jedem Abruf aus den Daten der Website und
    nennt zu jedem Eintrag Adresse, Datenblatt und Prüfstand. Das ist die
    einzige Stelle, an der sich von außen ablesen lässt, was tatsächlich
    online steht – `items.json` im Repository ist nur die Saat.
    """
    if quelle and Path(quelle).exists():
        daten = json.loads(Path(quelle).read_text(encoding="utf-8"))
    else:
        from urllib.request import Request, urlopen
        req = Request("https://www.warleek.de/herkunft.json",
                      headers={"User-Agent": "warleek-datenpruefung/1.0"})
        with urlopen(req, timeout=30) as r:
            daten = json.loads(r.read().decode("utf-8"))
    raus = {}
    for e in daten.get("hasPart", []):
        slug = re.sub(r".*/datenbank/", "", e.get("url", "")).strip("/")
        if slug:
            raus[slug] = e
    return raus


def dubletten(live, items):
    """Einträge, die es zweimal gibt.

    WordPress hängt eine `-2` an, wenn eine Adresse schon belegt ist. Ein
    Eintrag mit dieser Endung ist deshalb fast immer ein zweites Mal
    angelegtes Original – mit eigenem Text, eigenen Werten und häufig ohne
    SEO-Titel. Dazu der umgekehrte Fall: zwei Namen für dieselbe Sache, weil
    das Spiel etwas umbenannt hat (UH-1Y wurde zur Z20 Lakota).
    """
    verdacht = []
    for slug, e in sorted(live.items()):
        m = re.match(r"^(.*)-2$", slug)
        if m and m.group(1) in live:
            verdacht.append((slug, m.group(1), e.get("name", "")))
    return verdacht


def main():
    items  = json.loads(ITEMS.read_text(encoding="utf-8"))
    fakten = faktenblatt()
    nach_norm = {norm(i["title"]): i for i in items}

    widerspruch, abgleich, luecken, ohne_gruppe = [], [], [], []

    for i in items:
        felder = i.get("felder") or {}
        titel  = i["title"]

        for w in innere_widersprueche(i):
            widerspruch.append((titel, i["slug"], w))

        f = fakten.get(norm(titel))
        if f:
            punkte = []
            ist_stufe = (felder.get("freischaltung") or "").strip()
            ist_geb   = zahl(felder.get("freischaltkosten"))
            if ist_stufe and ist_stufe.lower() != f["stufe"].lower():
                punkte.append(("stufe", ist_stufe, f["stufe"]))
            if ist_geb is not None and ist_geb != f["gebuehr"]:
                punkte.append(("gebuehr", ist_geb, f["gebuehr"]))
            if punkte:
                abgleich.append((titel, i["slug"], punkte))

        fehlt = [p for p in PFLICHT.get(i.get("typ"), []) if not str(felder.get(p, "")).strip()]
        if fehlt or not (i.get("text") or "").strip():
            luecken.append((titel, i["slug"], fehlt, bool((i.get("text") or "").strip())))

        if not (i.get("gruppe") or "").strip():
            ohne_gruppe.append((titel, i["slug"], i.get("typ", "?")))

    # Vertauschungen: A trägt Bs Faktenwerte und B trägt As.
    tausch = []
    for titel, slug, punkte in abgleich:
        soll = dict((p[0], p[2]) for p in punkte)
        ist  = dict((p[0], p[1]) for p in punkte)
        for k2, f2 in fakten.items():
            partner = nach_norm.get(k2)
            if not partner or norm(partner["title"]) == norm(titel):
                continue
            pf = partner.get("felder") or {}
            a_traegt_b = (("stufe" not in ist  or ist["stufe"].lower()  == f2["stufe"].lower())
                          and ("gebuehr" not in ist or ist["gebuehr"] == f2["gebuehr"]))
            b_traegt_a = ((pf.get("freischaltung", "").strip().lower() == soll.get("stufe", "").lower()
                           if "stufe" in soll else True)
                          and (zahl(pf.get("freischaltkosten")) == soll.get("gebuehr")
                               if "gebuehr" in soll else True))
            if a_traegt_b and b_traegt_a:
                paar = tuple(sorted([titel, partner["title"]]))
                if paar not in [t[0] for t in tausch]:
                    tausch.append((paar, f2["name"]))

    # Fahrzeugpreise aus den Season-03-Notes.
    preis_s3 = []
    for name, soll in S3_PREISE.items():
        i = nach_norm.get(norm(name))
        if not i:
            preis_s3.append((name, None, soll, "kein Eintrag"))
            continue
        ist = zahl((i.get("felder") or {}).get("preis"))
        if ist != soll:
            preis_s3.append((i["title"], ist, soll, "weicht ab"))

    fehlend = []
    for k, f in fakten.items():
        if k in nach_norm:
            continue
        # Namen wandern: „Mosin“ heißt hier „Mosin Nagant“, „AH-6M“ steht als
        # „AH-6M [Miniguns]“. Als fehlend gilt nur, wofür sich kein Eintrag
        # findet, dessen Name den gesuchten enthält oder umgekehrt.
        aehnlich = [i["title"] for i in items
                    if k and (k in norm(i["title"]) or norm(i["title"]) in k)]
        if not aehnlich:
            fehlend.append((f["name"], []))
        elif len(aehnlich) > 1:
            fehlend.append((f["name"], aehnlich))

    live = {}
    if "--live" in sys.argv:
        pos = sys.argv.index("--live")
        datei = sys.argv[pos + 1] if len(sys.argv) > pos + 1 else None
        try:
            live = live_bestand(datei)
        except Exception as fehler:          # noqa: BLE001 – Bericht statt Absturz
            print(f"> Live-Abgleich nicht möglich: {fehler}\n", file=sys.stderr)

    # ---------------------------------------------------------------- Ausgabe
    e = print
    e("# Prüfliste Datenbank\n")
    e(f"Erzeugt von `bin/datenbank-pruefen.py` über {len(items)} Einträge.\n")
    e("Das Werkzeug entscheidet nichts. Es sagt nur, wo zwei Quellen sich widersprechen "
      "oder etwas fehlt. Was stimmt, steht im Laden im Spiel.\n")

    betroffen = sorted({t for t, _s, _p in abgleich})
    e("\n## 0. Was nur im Spiel zu klären ist\n")
    e(f"- **{len(betroffen)} Einträge** haben eine Freischaltstufe oder -gebühr, die dem "
      f"Faktenblatt widerspricht (Abschnitt 4). Einmal durch den Laden und die "
      f"Track-Übersicht, dann ist das erledigt: {', '.join(betroffen)}.")
    if tausch:
        wort = "Ein Paar sieht" if len(tausch) == 1 else f"**{len(tausch)} Paare** sehen"
        e(f"- {wort} vertauscht aus (Abschnitt 2) – dort zuerst hinsehen.")
    e("- Alles, was hier nicht steht, ist entweder unbestritten oder von außen nicht prüfbar.\n")

    e(f"\n## 1. Innere Widersprüche ({len(widerspruch)})\n")
    e("Fließtext gegen Datenblatt **desselben** Eintrags. Hier steht fest, dass eine der "
      "beiden Zahlen falsch ist.\n")
    for t, s, w in widerspruch:
        e(f"- **{t}** (`{s}`): {w}")
    if not widerspruch:
        e("- keine")

    e(f"\n## 2. Vertauschungsverdacht ({len(tausch)})\n")
    e("Eintrag A trägt genau die Werte, die das Faktenblatt für Eintrag B nennt – und "
      "umgekehrt. Ein Patch verschiebt Werte, er tauscht sie nicht paarweise.\n")
    for paar, _ in tausch:
        e(f"- **{paar[0]}** ↔ **{paar[1]}**")
    if not tausch:
        e("- keiner")

    e(f"\n## 3. Im Faktenblatt genannt, in der Datenbank nicht eindeutig ({len(fehlend)})\n")
    for n, aehnlich in sorted(fehlend):
        if aehnlich:
            e(f"- **{n}** – mehrdeutig, in Frage kommen: {', '.join(sorted(aehnlich))}")
        else:
            e(f"- **{n}** – kein Eintrag")
    if not fehlend:
        e("- keine")

    e(f"\n## 4. Abweichung vom Faktenblatt ({len(abgleich)})\n")
    e("Beide Seiten sind Community-Angaben, das Faktenblatt stammt vom 30.09.2026 und "
      "kennt Season 3 nicht. Eine Abweichung kann eine Änderung sein – oder ein Fehler.\n")
    e("| Eintrag | Feld | Datenbank | Faktenblatt |")
    e("|---|---|---|---|")
    for t, s, punkte in abgleich:
        for feld, ist, soll in punkte:
            if feld == "gebuehr":
                ist  = "$" + format(ist, ",d").replace(",", ".")
                soll = "$" + format(soll, ",d").replace(",", ".")
            e(f"| {t} | {'Gebühr' if feld=='gebuehr' else 'Stufe'} | {ist} | {soll} |")

    e(f"\n## 5. Preise aus den Season-03-Patch-Notes ({len(preis_s3)} Abweichungen)\n")
    e("Die einzigen belegten Preisänderungen – aus der Ankündigung vom 11.11.2026.\n")
    for t, ist, soll, was in preis_s3:
        e(f"- **{t}**: Datenbank {'$'+format(ist,',d').replace(',','.') if ist else '–'}, "
          f"Patch Notes ${format(soll,',d').replace(',','.')} ({was})")
    if not preis_s3:
        e("- alle vier stimmen")

    e(f"\n## 6. Lücken ({len(luecken)})\n")
    for t, s, fehlt, hat_text in luecken:
        teile = []
        if fehlt:      teile.append("Pflichtfelder fehlen: " + ", ".join(fehlt))
        if not hat_text: teile.append("kein Text")
        e(f"- **{t}** (`{s}`): {'; '.join(teile)}")
    if not luecken:
        e("- keine")

    if live:
        nur_live = sorted(set(live) - {i["slug"] for i in items})
        dub = dubletten(live, items)
        e(f"\n## 7. Abgleich mit der veröffentlichten Seite ({len(live)} Einträge online, "
          f"{len(items)} in dieser Datei)\n")
        e(f"**Dublettenverdacht ({len(dub)}).** Adresse endet auf `-2`, das Original gibt es auch.\n")
        for slug, orig, name in dub:
            e(f"- `{slug}` neben `{orig}` – „{name}“")
        if not dub:
            e("- keiner")
        rest = [s for s in nur_live if not re.match(r"^.*-2$", s)]
        if rest:
            e(f"\n**Nur online, nicht in dieser Datei ({len(rest)}).** Im Backend "
              f"angelegt – ohne Eintrag hier geht der Wert beim nächsten Einspielen "
              f"nicht verloren, aber er wird auch nie gepflegt.\n")
            for s in rest:
                e(f"- `{s}` – „{live[s].get('name','')}“")

    e(f"\n## 8. Ohne Gruppe ({len(ohne_gruppe)})\n")
    nach_typ = defaultdict(list)
    for t, s, typ in ohne_gruppe:
        nach_typ[typ].append(t)
    for typ in sorted(nach_typ):
        e(f"\n**{typ}** ({len(nach_typ[typ])}): " + ", ".join(sorted(nach_typ[typ])))


if __name__ == "__main__":
    sys.exit(main())
