---
title: "Season 3 technisch: Grafiktricks sind jetzt bannbar, Ping bis 250 – und eine lange Fehlerliste"
slug: wardogs-season-3-bugfixes
thema: technik
order: 74
image: guide-s3-bugfixes
image_alt: Blick aus einem Hubschrauber-Cockpit bei Regen auf eine Landschaft
excerpt: NVIDIA Inspector und manipulierte .ini-Dateien können ab Season 3 den Account kosten, die Ping-Grenze steigt auf 250, und BULKHEAD hat eine ungewöhnlich lange Liste behobener Fehler abgearbeitet. Was davon dich betrifft.
veroeffentlichen: 2026-11-16 09:00
seo_title: Wardogs Season 3 – Technik, Bann-Regeln, Bugfixes | Warleek
seo_description: Welche Grafikeinstellungen jetzt verboten sind, was die Ping-Grenze bedeutet und welche Fehler behoben wurden. Für Deutschland und Österreich.
quellen: ['Steam-Ankündigung „WARDOGS | Season 03 Patch Notes“ von BULKHEAD, 11.11.2026']
geprueft: nein – Angaben aus den Patch Notes vom 11.11.2026, von uns nicht im Spiel nachgeprüft
autor: techleek
frage: Welche Grafikeinstellungen sind in Wardogs verboten?
antwort: Seit Season 3 gilt es als bannbar, mit NVIDIA Inspector oder ähnlicher Fremdsoftware Rauch oder Gras zu entfernen, Geometrie zu verändern, den LOD-Bias anzupassen oder Texturen auszutauschen. Ebenso das Erzwingen der Potato-Schatten über die .ini-Dateien.
---

Der technische Teil der Season-3-Notes hat zwei Hälften. Die erste zieht eine Grenze, die vorher unscharf war, und sie betrifft deinen Account. Die zweite ist die längste Fehlerliste, die BULKHEAD bisher veröffentlicht hat.

> [!stand] November 2026 · Early Access — Angaben aus den Patch Notes vom 11.11.2026, von uns nicht im Spiel nachgeprüft.

## Was ab Season 3 bannbar ist

> [!achtung] Fremdsoftware wie **NVIDIA Inspector** zu benutzen, um Rauch oder Gras zu entfernen, Geometrie zu verändern, den LOD-Bias anzupassen oder Texturen auszutauschen, gilt ab Season 3 als **bannwürdig**.

Das ist keine Ankündigung für die Zukunft, sondern ab sofort gültig. Wer entsprechende Einstellungen gesetzt hat, wird beim Start **blockiert**: Ein Fenster erscheint und bittet darum, das Spiel zu schließen und die Einstellung zurückzunehmen. Wer dieses Fenster umgeht und trotzdem weiterspielt, riskiert den Account.

### Der Fall „Potato-Schatten"

Der zweite Teil ist feiner und trifft mehr Leute, als man denkt. Wardogs zeigt die niedrigste Schattenstufe im Menü **nur dann an**, wenn beim Start erkannt wird, dass die Hardware tatsächlich an der Mindestanforderung liegt. Wer sie sieht, hat laut Entwickler ein System, das gerade so reicht.

Ab Season 3 passieren zwei Dinge:

- Wurde die Einstellung **außerhalb des Menüs** verändert, also direkt in den `.ini`-Dateien, wird sie **beim Start zurückgesetzt**.
- Wer das umgeht — etwa indem er schwächere Hardware vortäuscht, während stärkere verbaut ist — begeht nach Lesart der Entwickler eine **bannwürdige Handlung**.

Das ist eine deutliche Ansage, und sie ist nachvollziehbar: Schatten liefern Information. Wer sie abschaltet, sieht nicht schlechter, sondern bekommt weniger Hinweise auf Bewegung geliefert — aber eben auch keine Blätter, keinen Bewuchs und keine Verdeckung mehr.

### Globale Beleuchtung nur noch im Overkill-Preset

| Preset | Globale Beleuchtung |
|---|---|
| Overkill | an- und abschaltbar, startet **an** |
| alle anderen | **aus und ausgeblendet** |

Wer bisher auf einem mittleren Preset die globale Beleuchtung gezielt abgeschaltet hat, findet den Schalter nicht mehr — sie ist dort ohnehin aus. Nur im Overkill-Preset bleibt die Wahl. Ein Preset-Wechsel kann einen Neustart erfordern; die Einstellung greift dann danach.

Unsere Empfehlungen in den [besten Einstellungen](/guides/wardogs-beste-einstellungen/) bleiben gültig — sie beziehen sich ausschließlich auf das Menü im Spiel. Nur der Hinweis zur globalen Beleuchtung ist jetzt in den meisten Presets gegenstandslos.

## Die Ping-Grenze steigt auf 250

Auf allen **offiziellen** Servern und in **allen Regionen**. Community-Server dürfen weiterhin eigene Grenzen setzen.

Die Begründung verbindet zwei Dinge, die vorher getrennt diskutiert wurden. Nach der Ban-Welle von über 185.000 Konten und einer Prüfung der regionalen Verteilung gegen echte Daten sieht BULKHEAD die Grundlage für strenge Grenzen nicht mehr. Dazu kommt das Argument, das von Anfang an im Raum stand: Mit Freunden zu spielen ist eine Kernidee des Spiels, und viele spielen über Ländergrenzen hinweg.

Die Zahlen hinter der Ban-Welle stehen in unserem [Beitrag zum Anti-Cheat](/guides/wardogs-anti-cheat-ban-welle/).

## DDoS-Angriffe in Asien

BULKHEAD bestätigt laufende Angriffe auf offizielle und Community-Server in asiatischen Regionen. Der Angriffsvektor sei identifiziert, Infrastruktur-Änderungen dagegen waren **für den Season-03-Start vorgesehen**. Gegen beteiligte Konten werde vorgegangen.

Ob die Maßnahme gewirkt hat, lässt sich von außen nicht sagen — wir tragen es nach, wenn es belastbare Hinweise gibt.

## Leistung

![Zerstörtes Gebäudeinneres mit Loch in der Wand und Trümmern](bild-pn-zerstoerung "Bodenbewuchs und Trümmer kosteten mehr Leistung als nötig — beides wurde optimiert."){rechts}

- **Bodenbewuchs** — Efeu, Laub, Schutt und Unrat — wurde optimiert, dazu unnötige Schattenaktualisierungen über die ganze Karte behoben.
- **Regen** rendert effizienter. Das Flackern auf Fahrzeugscheiben ist weg, ebenso der Fehler, der Regen nach einem Händlerbesuch aufhören ließ.
- Der **Ladebildschirm** bleibt jetzt stehen, bis die Matchdaten da sind. Vorher sah man kurz falsche Werte.
- Zwei **Serverabstürze** behoben: einer, wenn ein anvisiertes Ziel ungültig wurde, einer bei Schäden an Reifen oder Motoren.
- Der **Grafik-Benchmark** läuft beim ersten Start nicht mehr zweimal.

Wenn deine Bildrate stimmt und es trotzdem ruckelt, liegt das meist woanders — die Ursachen stehen im Guide zu den [Rucklern](/guides/wardogs-ruckler-beheben/).

## Behobene Fehler, die im Gefecht zählen

Die Liste ist lang. Das hier sind die Einträge, die eine Runde entscheiden können:

- **Gegner auf Höhe hinter niedrigen Mauern** wurden auf Entfernung manchmal als verdeckt dargestellt, obwohl sie sichtbar sein mussten. Behoben.
- **Leichensäcke und am Boden liegende Spieler** galten als Teamkill und ließen sich deshalb teils nicht plündern. Jetzt zeigen Leichensäcke den Namen ihres Besitzers, und wer den Server verlässt, lässt seine Beute fallen.
- **Modus-Beschränkungen beim Händler** wurden nach dem Kartenwechsel ins nächste Match mitgenommen.
- Das **TCX-SPR** benutzte im Liegen den Rückstoß aus dem Stand.
- **Fahrzeugtreffer** wurden manchmal der Handwaffe zugerechnet — samt deren Schaden.
- **Abschüsse durch Rammen** gingen auf das Konto des niedergestreckten Fahrers statt auf das des Angreifers.
- **Kugeln konnten den Fahrer des T-72B3 von außen töten.**
- **Schützen in Fahrzeugen** konnten an der Garage keine Munition kaufen.
- Der **Chat** konnte eingeklappt hängen bleiben und dabei Bewegungseingaben blockieren.
- **Rebuy Last Loadout** scheiterte, wenn das Loadout ein nicht freigeschaltetes Magazin enthielt.

Dazu kommen Dutzende kleinere Korrekturen an Kollisionen, Bauteilen, Tooltips und Menütexten.

## Ton

Die Audio-Änderungen sind in Summe ein Informationsgewinn:

- **Hollow-Point-Treffer** haben jetzt eigene Trefferlaute für Panzerung und Helm.
- **Panzerungs- und Helmbrüche** klingen zuverlässig — vorher nicht immer.
- Neue **Markierungs-Sprachzeilen**, darunter eigene für Panzer, Flugabwehrfahrzeuge, den Wolf-110, MG Nests und Nachschubkisten. Wer eine Markierung auffrischt, hört das jetzt.
- Eigene Zeilen für Spieler, die **brennen** oder **im Stacheldraht** hängen.
- Neue Geräusche beim Waffeninspizieren, inklusive einer eigenen Fassung für leere Waffen.

Dass eine Markierung jetzt sagt, *was* markiert wurde, ist für die Zusammenarbeit im Trupp mehr wert als die meisten Balance-Änderungen dieser Season.

## Controller

![Enge Gasse zwischen Wohnblöcken mit Pfützen](bild-pn-sabatulo-gasse2 "Direkt auf den Fahrersitz, Nachladen mit Magazinwurf: Die Controller-Belegung wurde an mehreren Stellen umgebaut."){links}

Die Belegung wurde umgebaut, und zwar so, dass alte Gewohnheiten nicht mehr passen:

| Taste | Neu |
|---|---|
| A | Sitz wechseln |
| A (halten) | **direkt auf den Fahrersitz** |
| B | Handbremse / Feuer (Pilot) |
| X (halten) | **Nachladen mit Magazinwurf** — schneller, verwirft das Magazin |

Handbremse und Pilotenfeuer sind von A auf B gewandert, um Platz für den direkten Fahrerwechsel zu machen. Weltinteraktionen brauchen jetzt **einen einzelnen Druck** statt Drücken und Halten; im Fahrzeug bleibt es beim Halten.

Am Boden liegend gilt: **A** Hilfe rufen, **B** Schadensprotokoll, **X** Aufgeben, **Y** Bestechen.

Dazu behoben: driftender Analog-Cursor, kürzere Haltezeit für Taktikkarte und Pausenmenü, Minikarten-Zoom im Liegen, D-Pad-Navigation im Lernbereich, IED-Zündung als Halten statt Umschalter, und der Kauf von Stingray-Munition.

## Karten

**Alle Karten** bekommen behobene Kollisionen, geschlossene Lücken und korrigiertes Licht in gemeinsam genutzten Gebäuden. Türen bleiben nicht mehr stehen, wenn ihre Wand zerstört wurde. Und: Die **Lonestar-Propagandaplakate** sind aus den Spawn-Bereichen verschwunden — ein stilles Ende für ein Detail, das es bis in unseren [Lonestar-Überlebensguide](/guides/lonestar-ueberlebensguide/) geschafft hat.

| Karte | Behoben |
|---|---|
| Bakurani | Regen fällt jetzt korrekt durch das Glasdach der Fabrik |
| Zestafona | Mörser konnten durch das Dach der grünen Backsteinhalle feuern; Kollision an den Traktoren angepasst |
| Ozeti | Blockierte Fahrzeug-Spawns; Fahrzeuge konnten durch die Stützmauer und die Pfeiler des Stadions fahren |

## Häufige Fragen

### Kann man für Grafikeinstellungen in Wardogs gebannt werden?

Ja, seit Season 3. Betroffen ist Fremdsoftware wie NVIDIA Inspector, mit der Rauch oder Gras entfernt, Geometrie verändert, der LOD-Bias angepasst oder Texturen getauscht werden. Auch das Erzwingen der Potato-Schatten über die .ini-Dateien gilt als bannbar.

### Was passiert, wenn ich solche Einstellungen gesetzt habe?

Beim Start erscheint ein Hinweisfenster, das dich bittet, das Spiel zu schließen und die Einstellung zurückzunehmen. Gespielt werden kann bis dahin nicht. Wer das Fenster umgeht, riskiert nach Angabe der Entwickler eine Sperre.

### Wie hoch ist die Ping-Grenze in Wardogs?

Seit Season 3 liegt sie bei 250 auf allen offiziellen Servern und in allen Regionen. Community-Server dürfen eigene Grenzen festlegen.

### Warum finde ich die globale Beleuchtung nicht mehr?

Sie erscheint seit Season 3 nur noch im Preset „Overkill" und ist dort standardmäßig an. In allen anderen Presets ist sie ausgeschaltet und der Schalter ausgeblendet.

### Was hat sich an der Controller-Belegung geändert?

A hält jetzt für den direkten Wechsel auf den Fahrersitz, Handbremse und Pilotenfeuer sind dafür von A auf B gewandert. Neu ist Nachladen mit Magazinwurf auf X-Halten. Weltinteraktionen brauchen nur noch einen Druck statt Drücken und Halten.
