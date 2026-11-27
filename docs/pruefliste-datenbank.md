# Prüfliste Datenbank

Erzeugt von `bin/datenbank-pruefen.py` über 200 Einträge.

Das Werkzeug entscheidet nichts. Es sagt nur, wo zwei Quellen sich widersprechen oder etwas fehlt. Was stimmt, steht im Laden im Spiel.


## 0. Was nur im Spiel zu klären ist

- **10 Einträge** haben eine Freischaltstufe oder -gebühr, für die es keine Erklärung gibt: Compound Bow, Deagle, GGX 17, GGX 18, Humvee, Judge, MGL-40, SV-98, Super-45, Z20 Lakota [Miniguns].
- **12 weitere Abweichungen sind erklärt** – ein Patch hat den Wert verschoben, und wir haben die Verschiebung belegt. Dort ist nichts zu prüfen: AK-74, AMR 50, BMR-308, FAL, Havoc, L2A6, M4, MAAWS, MK22, MP5, PKM, SPH-2.
- Ein Paar sieht vertauscht aus (Abschnitt 2) – dort zuerst hinsehen.
- Von den offenen stehen **10** gegen **zwei** voneinander unabhängige Quellen (Faktenblatt und wardogshub.uk, gelesen am 27.11.2026): Compound Bow, Deagle, GGX 17, GGX 18, Humvee, Judge, MGL-40, SV-98, Super-45, Z20 Lakota [Miniguns]. Dort lohnt der Blick zuerst.
- **Die Kaufpreise sind bestätigt.** wardogshub.uk nennt für jeden abgeglichenen Gegenstand denselben Preis wie wir – die Preise stehen nicht zur Debatte, nur die Freischaltungen.
- Alles, was hier nicht steht, ist entweder unbestritten oder von außen nicht prüfbar.


## 1. Innere Widersprüche (0)

Fließtext gegen Datenblatt **desselben** Eintrags. Hier steht fest, dass eine der beiden Zahlen falsch ist.

- keine

## 2. Vertauschungsverdacht (1)

Eintrag A trägt genau die Werte, die das Faktenblatt für Eintrag B nennt – und umgekehrt. Ein Patch verschiebt Werte, er tauscht sie nicht paarweise.

- **GGX 17** ↔ **Judge**

## 3. Im Faktenblatt genannt, in der Datenbank nicht eindeutig (2)

- **M249** – mehrdeutig, in Frage kommen: Humvee [M249], Kodiak [M249], M249 Machine Gun, M249 SAW, Ural Defender [M249]
- **PP-19** – mehrdeutig, in Frage kommen: PP-19 Bizon, PP-19 Vityaz

## 4. Abweichung vom Faktenblatt (22)

Beide externen Quellen sind **älter als Season 2** (Faktenblatt 30.09.2026, wardogshub.uk überwiegend Beta vom 21.08.2026). Eine Abweichung ist deshalb erst dann verdächtig, wenn kein Patch sie erklärt – Zeilen mit „belegt“ sind geklärt.

| Eintrag | Feld | Datenbank | Faktenblatt | wardogshub.uk | |
|---|---|---|---|---|---|
| AK-74 | Stufe | Startwaffe, keine Gebühr | Assault 3 | Assault 3 | belegt: Season 2: Startausrüstung, keine Gebühr mehr (season-2-progression) |
| M4 | Stufe | Assault 28 | Assault 20 | Assault 20 | belegt: Season 2: Level 20 → 28, Gebühr $100.000 → $150.000 (season-2-progression) |
| M4 | Gebühr | $150.000 | $100.000 | $100.000 | belegt: Season 2: Level 20 → 28, Gebühr $100.000 → $150.000 (season-2-progression) |
| FAL | Gebühr | $250.000 | $200.000 | $200.000 | belegt: Season 2: Gebühr $200.000 → $250.000 (season-2-progression) |
| MP5 | Stufe | Medic 4 | Medic 15 | Medic 15 | belegt: Season 2: Level 15 → 4, Gebühr $75.000 → $25.000 (season-2-progression) |
| MP5 | Gebühr | $25.000 | $75.000 | $75.000 | belegt: Season 2: Level 15 → 4, Gebühr $75.000 → $25.000 (season-2-progression) |
| Super-45 | Gebühr | $200.000 | $150.000 | $150.000 | **zwei gegen uns** |
| SV-98 | Stufe | Recon 18 | Recon 19 | Recon 19 | **zwei gegen uns** |
| MK22 | Stufe | Recon 30 | Recon 25 | Recon 25 | belegt: Season 2: Level 25 → 30 (season-2-progression) |
| BMR-308 | Stufe | Recon 20 | Recon 30 | Recon 30 | belegt: Season 2: Level 30 → 20 (season-2-progression) |
| AMR 50 | Stufe | Recon 41 | Recon 35 | Recon 35 | belegt: Season 3: Recon 35 → 41 (Eintragshinweis) |
| PKM | Stufe | Support 15 | Support 30 | Support 30 | belegt: Season 2: Level 30 → 15, Gebühr $150.000 → $100.000 (season-2-progression) |
| PKM | Gebühr | $100.000 | $150.000 | $150.000 | belegt: Season 2: Level 30 → 15, Gebühr $150.000 → $100.000 (season-2-progression) |
| GGX 17 | Stufe | Wardog 18 | Wardog 1 | Wardog 1 | **zwei gegen uns** |
| GGX 17 | Gebühr | $15.000 | $5.000 | $5.000 | **zwei gegen uns** |
| Judge | Stufe | Wardog 1 | Wardog 18 | Wardog 18 | **zwei gegen uns** |
| Judge | Gebühr | $5.000 | $15.000 | $15.000 | **zwei gegen uns** |
| GGX 18 | Stufe | Wardog 85 | Wardog 70 | Wardog 70 | **zwei gegen uns** |
| GGX 18 | Gebühr | $125.000 | $50.000 | $50.000 | **zwei gegen uns** |
| Deagle | Stufe | Wardog 65 | Wardog 85 | Wardog 85 | **zwei gegen uns** |
| MAAWS | Stufe | Support 18 | Support 20 | Support 20 | belegt: Season 2: zwei Stufen früher, $25.000 weniger (season-2-progression) |
| MAAWS | Gebühr | $100.000 | $125.000 | $125.000 | belegt: Season 2: zwei Stufen früher, $25.000 weniger (season-2-progression) |
| MGL-40 | Gebühr | $250.000 | $200.000 | $200.000 | **zwei gegen uns** |
| Compound Bow | Stufe | Recon 16 | Recon 17 | Recon 17 | **zwei gegen uns** |
| Compound Bow | Gebühr | $150.000 | $75.000 | $75.000 | **zwei gegen uns** |
| Z20 Lakota [Miniguns] | Gebühr | $85.000 | $75.000 | $75.000 | **zwei gegen uns** |
| Havoc | Gebühr | $650.000 | $500.000 | $500.000 | belegt: Season 2: Gebühr → $650.000 (season-2-progression) |
| SPH-2 | Stufe | Driver 31 | Career 90 | Wardog 90 | belegt: Season 2: WARDOG 90 → Driver 31, $500.000 → $350.000 (season-2-progression) |
| SPH-2 | Gebühr | $350.000 | $500.000 | $500.000 | belegt: Season 2: WARDOG 90 → Driver 31, $500.000 → $350.000 (season-2-progression) |
| L2A6 | Stufe | Driver 50 | Driver 35 | Driver 35 | belegt: Season 3: Driver 35 → 50 (season-3-nerfs-buffs) |
| Humvee | Stufe | Driver 14 | Driver 15 | Driver 15 | **zwei gegen uns** |
| Humvee | Gebühr | $75.000 | $25.000 | $25.000 | **zwei gegen uns** |

## 5. Preise aus den Season-03-Patch-Notes (0 Abweichungen)

Die einzigen belegten Preisänderungen – aus der Ankündigung vom 11.11.2026.

- alle vier stimmen

## 6. Lücken (1)

- **M12G** (`wardogs-m12g`): Pflichtfelder fehlen: preis, freischaltung

## 7. Abgleich mit der veröffentlichten Seite (209 Einträge online, 200 in dieser Datei)

**Dublettenverdacht (9).** Adresse endet auf `-2`, das Original gibt es auch.

- `wardogs-evo-3-ultra-2` neben `wardogs-evo-3-ultra` – „Evo-3 Ultra“
- `wardogs-large-shelter-2` neben `wardogs-large-shelter` – „Large Shelter“
- `wardogs-m14-2` neben `wardogs-m14` – „M14“
- `wardogs-pp-19-bizon-2` neben `wardogs-pp-19-bizon` – „PP-19 Bizon“
- `wardogs-t-72-2` neben `wardogs-t-72` – „T-72“
- `wardogs-tcx-spear-2` neben `wardogs-tcx-spear` – „TCX Spear“
- `wardogs-wolf-110-2` neben `wardogs-wolf-110` – „Wolf-110“
- `wardogs-wolf-110-assault-2` neben `wardogs-wolf-110-assault` – „Wolf-110 (Assault)“
- `wardogs-wooden-step-2` neben `wardogs-wooden-step` – „Wooden Step“

**Nur online, nicht in dieser Datei (2).** Im Backend angelegt – ohne Eintrag hier geht der Wert beim nächsten Einspielen nicht verloren, aber er wird auch nie gepflegt.

- `wardogs-uh-1y` – „UH-1Y“
- `wardogs-uh-1y-miniguns` – „UH-1Y [Miniguns]“

## 8. Ohne Gruppe (0)

