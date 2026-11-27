---
title: "Zehn Zahlen, die niemand nachprüft"
slug: zehn-zahlen-die-niemand-nachprueft
thema: hinter-den-kulissen
excerpt: Wir haben unsere Datenbank gegen zwei unabhängige Quellen gestellt. Von 22 strittigen Freischaltwerten bleiben zehn, die nur im Spiel zu klären sind. Hier stehen sie — und warum das überhaupt passiert.
seo_title: Zehn ungeklärte Zahlen in der Wardogs-Datenbank | Warleek
seo_description: Warum Freischaltwerte in Wardogs-Datenbanken auseinandergehen, welche zehn bei uns offen sind und wie du helfen kannst, sie zu klären.
veroeffentlichen: 2026-12-01
autor: adminleek
frage: Warum widersprechen sich Wardogs-Datenbanken bei den Freischaltstufen?
antwort: Weil die meisten Angaben aus der Beta oder aus Season 1 stammen und seitdem nicht nachgezogen wurden. Season 2 hat über ein Dutzend Freischaltungen verschoben, Season 3 weitere. Wer den alten Wert nennt, hat nicht unrecht gehabt — er ist nur nicht nachgegangen.
quellen:
  - 'Eigener Abgleich gegen wardogshub.uk, gelesen am 27.11.2026'
  - 'Steam-Ankündigung „WARDOGS | Season 02 Patch Notes" von BULKHEAD, 14.10.2026'
geprueft: ja – der Abgleich ist dokumentiert in docs/pruefliste-datenbank.md
---

Wir führen zu jedem Eintrag unserer [Datenbank](/datenbank/) einen Prüfstand: im Spiel nachgeprüft, aus den Patch Notes übernommen, oder ungeprüft aus Community-Quellen. Diese Woche haben wir alle 198 Einträge gegen zwei unabhängige Quellen gestellt. Das Ergebnis ist unangenehm genug, um es aufzuschreiben.

## Die Preise stimmen

Zuerst die gute Nachricht. Jeder Kaufpreis, den wir abgleichen konnten, stimmt auf den Dollar mit der englischen Community-Datenbank wardogshub.uk überein — von der AK-74 mit $800 bis zum [Havoc](/datenbank/wardogs-havoc/) mit {preis:wardogs-havoc}. Zwei unabhängig gepflegte Datenbanken, dieselben Zahlen.

Das hat sogar einen offenen Punkt geklärt: Beim [Z20 Lakota [Miniguns]](/datenbank/wardogs-z20-lakota-miniguns/) stand bei uns an einer Stelle $8.000, an anderer {preis:wardogs-z20-lakota-miniguns}. Die dritte Quelle und unser eigener Season-2-Guide sagen übereinstimmend {preis:wardogs-z20-lakota-miniguns}. Erledigt.

## Die Freischaltungen nicht

Bei den Freischaltstufen gehen die Quellen an 22 Stellen auseinander. Unser erster Verdacht war, dass wir Werte vertauscht haben — bei MP5 und PP-19 sah es genau so aus.

Falsch gedacht. **Beide Vergleichsquellen sind älter als Season 2.** Das Faktenblatt stammt vom 30. September, die englische Datenbank überwiegend aus der Beta vom 21. August. Season 2 kam am 14. Oktober und hat über ein Dutzend Freischaltungen verschoben — die MP5 von Stufe 15 auf 4, die PKM von 30 auf 15, die M249 SAW von 15 auf 20, die SPH-2 vom WARDOG-Track auf Driver 31.

Unsere Zahlen waren richtig. Die Vergleichsquellen sind einfach stehen geblieben.

## Was wirklich offen ist

Zwölf der 22 Abweichungen erklären sich so. Zehn bleiben, und für die haben wir keine Erklärung:

**Compound Bow, Deagle, GGX 17, GGX 18, Humvee, Judge, MGL-40, SV-98, Super-45 und Z20 Lakota [Miniguns]** — bei allen zehn steht unsere Zahl gegen zwei voneinander unabhängige Quellen, ohne dass ein Patch die Verschiebung erklärt.

Besonders auffällig sind [GGX 17](/datenbank/wardogs-ggx-17/) und [Judge](/datenbank/wardogs-judge/): Sie tragen bei uns paarweise die Werte des jeweils anderen. Entweder hat Season 2 die beiden tatsächlich getauscht — dann fehlt uns der Beleg — oder bei uns ist eine Zeile verrutscht.

## Warum wir das veröffentlichen

Weil eine Datenbank ohne Prüfstand eine Behauptung ist. In einem Early-Access-Spiel ändern sich Preise und Stufen mit jedem Patch, und die Hälfte der Zahlen, die im Netz kursiert, stammt aus einer Season, die es nicht mehr gibt. Wir sind davon nicht ausgenommen — wir schreiben nur dazu, woher jede Zahl kommt und wann sie zuletzt geprüft wurde.

Unter jedem Eintrag steht das im Klartext. Maschinenlesbar gibt es dasselbe unter [herkunft.json](/herkunft.json).

## Du kannst helfen

Wenn du einen der zehn Gegenstände im Laden stehen siehst: Die Freischaltstufe und die einmalige Gebühr stehen direkt dort. Ein Screenshot oder eine Zeile reicht — wir tragen den Wert mit Datum und Herkunft nach, und der Eintrag bekommt den Vermerk „im Spiel geprüft".

Das ist kein Mitmach-Aufruf aus Höflichkeit. Es sind zehn Zahlen, und wer regelmäßig spielt, hat die meisten davon ohnehin vor Augen.
