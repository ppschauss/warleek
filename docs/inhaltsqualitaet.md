# AdSense-Ablehnung vom 24.11.2026 — Befund und Plan

**Meldung im AdSense-Konto:** `warleek.de` · Freigabestatus „Maßnahme erforderlich“ ·
Statusdetails „Minderwertige Inhalte“ · ads.txt-Status „Nicht gefunden“ · Stand 24. Nov. 2026, 16:33 CET.

## Gemessen, nicht vermutet

Alle 299 Adressen aus den Sitemaps abgerufen und den sichtbaren Text im `<main>` gezählt
(Skripte, Navigation, Fußzeile abgezogen), 27.11.2026:

| Sitemap | Seiten | Median Wörter | Minimum | darunter < 300 W |
|---|---|---|---|---|
| `page` | 10 | 879 | 406 | 0 |
| `guide` | 62 | 1.066 | 746 | 0 |
| `patchnote` | 11 | 985 | 248 | 2 |
| `item` (1+2) | **210** | **288** | **155** | **121** |
| `guide-thema` | 6 | 341 | 132 | 3 |

Dazu aus `items.json`: Der eigene Text eines Eintrags hat im Median **44 Wörter**
(Minimum 18, Maximum 155). Der Rest jeder Eintragsseite ist Werte-Tabelle plus immer
derselbe Rahmen aus Quellenangabe, Prüfstand, Nachbarschaft und „Weiter in der Datenbank“.

Zwei Drittel aller indexierten Adressen sind damit Seiten, die aus derselben Vorlage
entstehen und je drei Sätze Eigenleistung tragen. Unter jedem Eintrag steht zudem als
Quelle „Öffentliche Community-Datenbanken und Wikis“ — die Seite erklärt also selbst,
dass die Zahlen von woanders stammen.

Zweiter Fund: Die Patch Notes waren vollständige deutsche Zweitfassungen fremder
Ankündigungen. `wardogs-season-03-patch-notes` kam auf 12.349 Wörter und 127
Überschriften, darunter im Aufklapper noch einmal das englische Original.

Dritter Fund: `/thema/gameplay/` (135 Wörter, automatisches Archiv) stand neben
`/wardogs/gameplay/` (879 Wörter, kuratiert). Sechsmal dieselbe Dopplung.

## Phase 1 — Version 2.39.0 (27.11.2026)

- `item`-Einzelseiten und `guide-thema`-Archive auf `noindex, follow`, raus aus der Sitemap.
  `/datenbank/` bleibt drin.
- `/datenbank/` bekommt 650 Wörter eigenen Text (Freischaltung vs. Gebühr vs. Preis,
  die drei Stufen des Prüfstands, was die Werte nicht sagen, Stand nach Season 3).
- Patch Notes ab 500 Wörtern nur noch als Kurzfassung: eigene Zusammenfassung,
  Abschnittsliste, unsere Guides aus den sechs Wochen danach, Quellenlink.
  Englischer Aufklapper entfernt.
- `/ads.txt` wird wieder ausgeliefert.
- Impressum: „nicht-kommerziell“ raus, Abschnitt zur Werbefinanzierung rein.

**Was Phase 1 nicht leistet.** Ein `noindex` räumt den Index auf. Ein Prüfer, der auf
„Alle Einträge“ klickt, sieht trotzdem 198 dünne Seiten. Wer die Wiederprüfung jetzt
beantragt, setzt darauf, dass die automatische Bewertung auf den Index schaut.

## Phase 2 — erledigt am 27.11.2026 (Version 2.40.0)

Statt die Einzelseiten abzuschaffen, haben sie Inhalt bekommen. Alle 198
Einträge tragen jetzt 108 bis 238 Wörter eigenen Text plus mindestens eine
eigene Frage; gerendert sind das rund 250 bis 400 Wörter je Seite statt 178.
Das `noindex` hängt seitdem an der Länge (220 Wörter), nicht am Beitragstyp –
die Einträge sind damit von selbst in den Index zurückgekommen.

Werte stehen nicht mehr im Text, sondern als Platzhalter, die beim Ausliefern
aus dem Datenblatt gelesen werden. Eine Preisänderung im Backend macht damit
jeden Satz auf jeder Seite gleichzeitig richtig – und setzt das Siegel
„Zuletzt korrigiert am …" unter den Stand-Hinweis.

## Phase 3 — Vergleichsseiten je Gruppe

198 Einträge verteilen sich auf acht Typen (`ausruestung` 59, `waffe` 39, `bauwerk` 31,
`fahrzeug` 24, `wurfwaffe` 16, `emplacement` 14, `munition` 11, `anbauteil` 4) und
24 feinere Gruppen (`medizin` 13, `munition` 11, `sturmgewehr` 10, `scharfschuetze` 9,
`rucksack` 9, `panzerung` 9, `sprengstoff` 7, `helikopter` 6 …; 65 Einträge ohne Gruppe).

Plan: je Gruppe eine Vergleichsseite mit Tabelle, Empfehlung und eigenem Text,
Einzelseiten per 301 auf den Anker in der Vergleichsseite. Aus 198 dünnen Seiten
werden rund 20 starke. Die 65 Einträge ohne Gruppe brauchen vorher eine.

## Noch offen

- Wiederprüfung im AdSense-Konto beantragen (erst nach Phase 2 empfohlen).
- GDPR-Nachricht in AdSense veröffentlichen (zertifizierte CMP), zwei Anzeigenblöcke
  anlegen, `ads_slot_text` / `ads_slot_faq` eintragen, dann `ads_enabled` einschalten.
