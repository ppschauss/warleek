# Prüfliste Datenbank

Erzeugt von `bin/datenbank-pruefen.py` über 198 Einträge.

Das Werkzeug entscheidet nichts. Es sagt nur, wo zwei Quellen sich widersprechen oder etwas fehlt. Was stimmt, steht im Laden im Spiel.


## 0. Was nur im Spiel zu klären ist

- **21 Einträge** haben eine Freischaltstufe oder -gebühr, die dem Faktenblatt widerspricht (Abschnitt 4). Einmal durch den Laden und die Track-Übersicht, dann ist das erledigt: AK-74, AMR 50, BMR-308, Compound Bow, Deagle, FAL, GGX 17, GGX 18, Havoc, Humvee, Judge, L2A6, M4, MAAWS, MGL-40, MK22, MP5, PKM, SPH-2, SV-98, Super-45.
- Ein Paar sieht vertauscht aus (Abschnitt 2) – dort zuerst hinsehen.
- Alles, was hier nicht steht, ist entweder unbestritten oder von außen nicht prüfbar.


## 1. Innere Widersprüche (0)

Fließtext gegen Datenblatt **desselben** Eintrags. Hier steht fest, dass eine der beiden Zahlen falsch ist.

- keine

## 2. Vertauschungsverdacht (1)

Eintrag A trägt genau die Werte, die das Faktenblatt für Eintrag B nennt – und umgekehrt. Ein Patch verschiebt Werte, er tauscht sie nicht paarweise.

- **GGX 17** ↔ **Judge**

## 3. Im Faktenblatt genannt, in der Datenbank nicht eindeutig (3)

- **M249** – mehrdeutig, in Frage kommen: Humvee [M249], Kodiak [M249], M249 Machine Gun, M249 SAW, Ural Defender [M249]
- **PP-19** – mehrdeutig, in Frage kommen: PP-19 Bizon, PP-19 Vityaz
- **Z20 Lakota [Miniguns]** – kein Eintrag

## 4. Abweichung vom Faktenblatt (21)

Beide Seiten sind Community-Angaben, das Faktenblatt stammt vom 30.09.2026 und kennt Season 3 nicht. Eine Abweichung kann eine Änderung sein – oder ein Fehler.

| Eintrag | Feld | Datenbank | Faktenblatt |
|---|---|---|---|
| AK-74 | Stufe | Startwaffe, keine Gebühr | Assault 3 |
| M4 | Stufe | Assault 28 | Assault 20 |
| M4 | Gebühr | $150.000 | $100.000 |
| FAL | Gebühr | $250.000 | $200.000 |
| MP5 | Stufe | Medic 4 | Medic 15 |
| MP5 | Gebühr | $25.000 | $75.000 |
| Super-45 | Gebühr | $200.000 | $150.000 |
| SV-98 | Stufe | Recon 18 | Recon 19 |
| MK22 | Stufe | Recon 30 | Recon 25 |
| BMR-308 | Stufe | Recon 20 | Recon 30 |
| AMR 50 | Stufe | Recon 41 | Recon 35 |
| PKM | Stufe | Support 15 | Support 30 |
| PKM | Gebühr | $100.000 | $150.000 |
| GGX 17 | Stufe | Wardog 18 | Wardog 1 |
| GGX 17 | Gebühr | $15.000 | $5.000 |
| Judge | Stufe | Wardog 1 | Wardog 18 |
| Judge | Gebühr | $5.000 | $15.000 |
| GGX 18 | Stufe | Wardog 85 | Wardog 70 |
| GGX 18 | Gebühr | $125.000 | $50.000 |
| Deagle | Stufe | Wardog 65 | Wardog 85 |
| MAAWS | Stufe | Support 18 | Support 20 |
| MAAWS | Gebühr | $100.000 | $125.000 |
| MGL-40 | Gebühr | $250.000 | $200.000 |
| Compound Bow | Stufe | Recon 16 | Recon 17 |
| Compound Bow | Gebühr | $150.000 | $75.000 |
| Havoc | Gebühr | $650.000 | $500.000 |
| SPH-2 | Stufe | Driver 31 | Career 90 |
| SPH-2 | Gebühr | $350.000 | $500.000 |
| L2A6 | Stufe | Driver 50 | Driver 35 |
| Humvee | Stufe | Driver 14 | Driver 15 |
| Humvee | Gebühr | $75.000 | $25.000 |

## 5. Preise aus den Season-03-Patch-Notes (1 Abweichungen)

Die einzigen belegten Preisänderungen – aus der Ankündigung vom 11.11.2026.

- **Wolf-110 [M2 Browning]**: Datenbank $7.800, Patch Notes $6.800 (weicht ab)

## 6. Lücken (5)

- **Bushmaster M17S** (`wardogs-bushmaster-m17s`): Pflichtfelder fehlen: preis
- **A-91** (`wardogs-a-91`): Pflichtfelder fehlen: preis
- **KH-2002** (`wardogs-kh-2002`): Pflichtfelder fehlen: preis
- **M113 APC SV** (`wardogs-m113-apc-sv`): Pflichtfelder fehlen: preis
- **M12G** (`wardogs-m12g`): Pflichtfelder fehlen: preis, freischaltung

## 7. Abgleich mit der veröffentlichten Seite (209 Einträge online, 198 in dieser Datei)

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

- `wardogs-z20-lakota` – „Z20 Lakota“
- `wardogs-z20-lakota-miniguns` – „Z20 Lakota [Miniguns]“

## 8. Ohne Gruppe (0)

