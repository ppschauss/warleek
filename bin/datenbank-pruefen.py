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
HUB    = WURZEL / "docs/research/wardogshub-2026-11-27.md"

# wardogshub.uk nennt zwei Tracks anders als wir und als unser Faktenblatt.
HUB_TRACKS = {"Infantry": "Assault", "Career": "Wardog"}

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


# Verschiebungen, die wir **selbst belegt** haben – aus unseren Guides, die
# ihrerseits aus den Patch Notes stammen. Faktenblatt (30.09.2026) und
# wardogshub.uk (Beta, 21.08.2026) sind beide **älter als Season 2** vom
# 14.10.2026. Wo ein Patch den Wert seither verschoben hat, ist nicht unsere
# Zahl auffällig, sondern die der älteren Quellen.
#
# Ohne diese Liste meldete das Werkzeug 22 Einträge zur Prüfung, von denen 13
# längst geklärt waren – und schickte jemanden für nichts durch den Laden.
BELEGTE_AENDERUNGEN = {
    "wardogs-ak-74":              "Season 2: Startausrüstung, keine Gebühr mehr (season-2-progression)",
    "wardogs-t-21":               "Season 2: erstmals Freischaltung Assault 3, $10.000 (season-2-progression)",
    "wardogs-m4":                 "Season 2: Level 20 → 28, Gebühr $100.000 → $150.000 (season-2-progression)",
    "wardogs-fal":                "Season 2: Gebühr $200.000 → $250.000 (season-2-progression)",
    "wardogs-mp5":                "Season 2: Level 15 → 4, Gebühr $75.000 → $25.000 (season-2-progression)",
    "wardogs-medizintasche":      "Season 2: Level 24 → 14, Gebühr $75.000 → $50.000 (season-2-progression)",
    "wardogs-defibrillator-ausr": "Season 2: Level 11 → 22, Gebühr → $110.000 (season-2-progression)",
    "wardogs-pkm":                "Season 2: Level 30 → 15, Gebühr $150.000 → $100.000 (season-2-progression)",
    "wardogs-m249-saw":           "Season 2: Level 15 → 20, Gebühr $100.000 → $150.000 (season-2-progression)",
    "wardogs-maaws":              "Season 2: zwei Stufen früher, $25.000 weniger (season-2-progression)",
    "wardogs-sph-2":              "Season 2: WARDOG 90 → Driver 31, $500.000 → $350.000 (season-2-progression)",
    "wardogs-mk22":               "Season 2: Level 25 → 30 (season-2-progression)",
    "wardogs-bmr-308":            "Season 2: Level 30 → 20 (season-2-progression)",
    "wardogs-ghillie-anzug-tot":  "Season 2: Level 27 → 41 (season-2-progression)",
    "wardogs-ah-6m-miniguns":     "Season 2: Pilot 4 → 8, Gebühr $50.000 → $125.000 (season-2-progression)",
    "wardogs-havoc":              "Season 2: Gebühr → $650.000 (season-2-progression)",
    "wardogs-l2a6":               "Season 3: Driver 35 → 50 (season-3-nerfs-buffs)",
    "wardogs-t-72":               "Season 3: Driver 20 → 35 (season-3-nerfs-buffs)",
    "wardogs-amr-50":             "Season 3: Recon 35 → 41 (Eintragshinweis)",
    "wardogs-grosser-hammer":     "Season 3: Support 8 → 14 (season-3-nerfs-buffs)",
    "wardogs-9k333-verba":        "Season 3: Support 14 → 8 (season-3-nerfs-buffs)",
}


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


def wardogshub():
    """Die dritte Quelle lesen: Freischaltstufen und -gebühren von wardogshub.uk.

    Gelesen am 27.11.2026, überwiegend Beta-Stand (21.08.2026). Damit ist sie
    **älter** als unser Faktenblatt und erst recht älter als unsere Datenbank –
    sie taugt nicht, um einen Wert zu bestätigen, aber sehr wohl, um einen
    Verdacht zu erhärten: Wo zwei voneinander unabhängige Quellen dasselbe
    sagen und wir etwas anderes, ist unsere Zahl die auffällige.
    """
    if not HUB.exists():
        return {}
    raus = {}
    for zeile in HUB.read_text(encoding="utf-8").splitlines():
        m = re.match(r"\|\s*(.+?)\s*\|\s*(\w+)\s+(\d+)\s*\|\s*(\d+)\s*\|", zeile)
        if not m:
            continue
        track = HUB_TRACKS.get(m.group(2), m.group(2))
        raus[norm(m.group(1))] = {
            "name":    m.group(1),
            "stufe":   f"{track} {m.group(3)}",
            "gebuehr": int(m.group(4)),
        }
    return raus


def innere_widersprueche(i):
    """Zahlen im Fließtext gegen das Datenblatt desselben Eintrags.

    **Platzhalter heben die Prüfung auf.** Steht `{preis}` im Text, kommt der
    aktuelle Preis beim Ausliefern aus dem Datenblatt – ein ausgeschriebener
    Betrag daneben ist dann keine konkurrierende Angabe, sondern eine
    historische („in Season 1 waren es $75") oder eine, die gar nicht im
    Datenblatt steht (Abschussprämien, Summen über mehrere Einträge). Ohne
    diese Ausnahme meldete das Werkzeug 29 Widersprüche, von denen keiner
    einer war.
    """
    text   = i.get("text") or ""
    felder = i.get("felder") or {}
    funde  = []

    preis = zahl(felder.get("preis"))
    if preis and "{preis}" not in text:
        # Beträge im Text, die wie ein Stückpreis aussehen (nicht die Gebühr).
        betraege = {int(b.replace(".", "").replace(",", ""))
                    for b in re.findall(r"\$\s?([\d.,]+)", text)}
        gebuehr  = zahl(felder.get("freischaltkosten"))
        fremd    = [b for b in betraege if b not in (preis, gebuehr) and b >= 100]
        if fremd and preis not in betraege:
            funde.append(f"Text nennt {', '.join('$'+format(b,',d').replace(',','.') for b in sorted(fremd))}, "
                         f"das Datenblatt $" + format(preis, ",d").replace(",", "."))

    stufe = (felder.get("freischaltung") or "").strip()
    if stufe and "{freischaltung}" not in text:
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
    hub    = wardogshub()
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
                # Sagt die dritte Quelle dasselbe wie das Faktenblatt? Dann
                # steht unsere Zahl allein gegen zwei – das ist der Fall, bei
                # dem sich der Gang zum Laden am meisten lohnt.
                h = hub.get(norm(titel))
                einig = []
                for feld, _ist, soll in punkte:
                    if not h:
                        continue
                    if feld == "stufe" and h["stufe"].lower() == str(soll).lower():
                        einig.append("stufe")
                    if feld == "gebuehr" and h["gebuehr"] == soll:
                        einig.append("gebuehr")
                abgleich.append((titel, i["slug"], punkte, einig, h))

        # Startausrüstung ohne Preis ist kostenlos, keine Lücke. wardogshub.uk
        # führt Bushmaster M17S, A-91 und KH-2002 mit $0 – das deckt sich mit
        # unserem „Startwaffe, keine Gebühr" und ist damit vollständig.
        pflicht = list(PFLICHT.get(i.get("typ"), []))
        if i.get("start") and "preis" in pflicht:
            pflicht.remove("preis")
        fehlt = [p for p in pflicht if not str(felder.get(p, "")).strip()]
        if fehlt or not (i.get("text") or "").strip():
            luecken.append((titel, i["slug"], fehlt, bool((i.get("text") or "").strip())))

        if not (i.get("gruppe") or "").strip():
            ohne_gruppe.append((titel, i["slug"], i.get("typ", "?")))

    # Vertauschungen: A trägt Bs Faktenwerte und B trägt As.
    tausch = []
    for titel, slug, punkte, _einig, _h in abgleich:
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

    offen  = [(t, sl, p, e) for t, sl, p, e, _h in abgleich if sl not in BELEGTE_AENDERUNGEN]
    belegt = sorted({t for t, sl, _p, _e, _h in abgleich if sl in BELEGTE_AENDERUNGEN})
    betroffen = sorted({t for t, _sl, _p, _e in offen})
    zwei      = sorted({t for t, _sl, _p, e in offen if e})
    e("\n## 0. Was nur im Spiel zu klären ist\n")
    e(f"- **{len(betroffen)} Einträge** haben eine Freischaltstufe oder -gebühr, für die es "
      f"keine Erklärung gibt: {', '.join(betroffen)}.")
    if belegt:
        e(f"- **{len(belegt)} weitere Abweichungen sind erklärt** – ein Patch hat den Wert "
          f"verschoben, und wir haben die Verschiebung belegt. Dort ist nichts zu prüfen: "
          f"{', '.join(belegt)}.")
    if tausch:
        wort = "Ein Paar sieht" if len(tausch) == 1 else f"**{len(tausch)} Paare** sehen"
        e(f"- {wort} vertauscht aus (Abschnitt 2) – dort zuerst hinsehen.")
    if zwei:
        e(f"- Von den offenen stehen **{len(zwei)}** gegen **zwei** voneinander unabhängige Quellen "
          f"(Faktenblatt und wardogshub.uk, gelesen am 27.11.2026): "
          f"{', '.join(zwei)}. Dort lohnt der Blick zuerst.")
    e("- **Die Kaufpreise sind bestätigt.** wardogshub.uk nennt für jeden abgeglichenen "
      "Gegenstand denselben Preis wie wir – die Preise stehen nicht zur Debatte, nur die "
      "Freischaltungen.")
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
    e("Beide externen Quellen sind **älter als Season 2** (Faktenblatt 30.09.2026, "
      "wardogshub.uk überwiegend Beta vom 21.08.2026). Eine Abweichung ist deshalb erst "
      "dann verdächtig, wenn kein Patch sie erklärt – Zeilen mit \u201ebelegt\u201c sind geklärt.\n")
    e("| Eintrag | Feld | Datenbank | Faktenblatt | wardogshub.uk | |")
    e("|---|---|---|---|---|---|")
    for t, _slug, punkte, einig, h in abgleich:
        for feld, ist, soll in punkte:
            dritt = "–"
            if h:
                dritt = h["stufe"] if feld == "stufe" else "$" + format(h["gebuehr"], ",d").replace(",", ".")
            if feld == "gebuehr":
                ist  = "$" + format(ist, ",d").replace(",", ".")
                soll = "$" + format(soll, ",d").replace(",", ".")
            # Belegte Verschiebung schlägt den Zwei-Quellen-Verdacht: Wenn ein
            # Patch den Wert bewegt hat, sind die älteren Quellen im Rückstand.
            belegt_hier = BELEGTE_AENDERUNGEN.get(_slug, "")
            marke = ("belegt: " + belegt_hier) if belegt_hier else (
                "**zwei gegen uns**" if feld in einig else "")
            e(f"| {t} | {'Gebühr' if feld=='gebuehr' else 'Stufe'} | {ist} | {soll} | {dritt} | {marke} |")

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
