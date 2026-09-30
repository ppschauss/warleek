---
title: HOTAS-Empfindlichkeit, Deadzone und Kurven – dreh den Regler nicht herunter
slug: wardogs-hotas-empfindlichkeit
thema: technik
order: 54
image: guide-hotas-settings
image_alt: Joystick und Schubhebel eines HOTAS-Aufbaus auf einem dunklen Schreibtisch im Monitorlicht
excerpt: Der Regler unter 1.0 nimmt dir nicht nur das Zappeln, sondern auch die maximale Drehrate. Warum das so ist, was stattdessen hilft – und wo sich zwei erfahrene Piloten offen widersprechen.
seo_title: Wardogs HOTAS Empfindlichkeit & Kurven (Deutsch) | Warleek
seo_description: Warum der Regler unter 1.0 Drehrate kostet, wann eine Deadzone nötig ist und wie eine Kurve hilft. Wardogs auf Deutsch für die DACH-Region.
---

Wenn der Helikopter am Stick zappelt, ist der erste Griff immer derselbe: Empfindlichkeit runter. In Wardogs ist das die falsche Richtung – und der Grund dafür ist der interessanteste Teil dieses Guides.

> [!stand] September 2026 · Early Access – die HOTAS-Unterstützung ist erklärtermaßen unfertig und ändert sich mit Patches.

## Erst verstehen, wie das Spiel den Stick liest

Wardogs behandelt einen analogen Stick im Kern wie **Tastatureingaben**. Das klingt technisch, hat aber eine sehr praktische Folge:

> [!hinweis] Solange ein Ausschlag anliegt, **dreht der Helikopter weiter** in diese Richtung. Er nimmt keine Lage ein und hält sie. Die Mitte des Sticks ist das Äquivalent zu „Finger von der Taste nehmen" – nur dort hört die Drehung auf.

Wer aus einem Flugsimulator kommt, stellt den Stick irgendwohin und lässt ihn dort. Das führt hier dazu, dass sich der Helikopter immer weiter dreht. Richtig ist das Gegenteil: **Ausschlag geben, zurück zur Mitte. Kleine Rollbewegung, zurück zur Mitte. Korrektur, Mitte.** Tippen statt halten.

Allein diese Umstellung im Kopf verändert das Fliegen mehr als jede Einstellung.

## Warum der Empfindlichkeitsregler eine Falle ist

Es gibt Regler für Pitch, Roll, Yaw und Kollektiv. Dreht man Roll auf zum Beispiel 0.1, passiert nicht nur das Erhoffte:

- **Erhofft:** ruhiger um die Mittelstellung, weniger Zappeln bei kleinen Korrekturen.
- **Ebenfalls passiert:** Am vollen Anschlag rollt der Helikopter jetzt **langsam**. Die maximale Drehrate ist weg.

Das ist ein schlechtes Geschäft. Präzision beim Schweben hilft wenig, wenn du im Ernstfall nicht mehr schnell wegdrehen kannst. Je weiter unter 1.0, desto mehr Steuerautorität gibst du ab.

![Drei Kennlinien im Vergleich: linear bei 1.0, abgesenkt auf 0.3 und eine Kurve aus dem Stick-Treiber.](dia-kurve "Der Regler senkt die ganze Gerade ab – die Kurve senkt nur die Mitte und lässt den Anschlag, wo er war.")

## Die Lösung: eine Kurve im Treiber deines Sticks

Wardogs hat **keine** eigene Kurveneinstellung. Die meisten Joysticks bringen dafür eine Software mit (VKB, Virpil, Thrustmaster, Logitech). Dort baust du die Kennlinie, die das Spiel nicht anbietet:

1. Der Bereich um die Mitte reagiert **flacher** – dort passieren Schweben, Landen und Zielen.
2. Weiter außen steigt die Kurve an.
3. Am Anschlag erreichst du **denselben Maximalwert wie vorher**.

Damit hast du die Feinarbeit, ohne das obere Ende zu verschenken. Im Spiel bleibt die Empfindlichkeit bei 1.0 oder darüber.

## Deadzone: nur, wenn dein Stick sie braucht

Eine Deadzone ist kein Komfortregler, sondern eine Reparatur für ein bestimmtes Problem.

| Dein Stick | Deadzone |
| --- | --- |
| Zentriert sich mit Federn sauber auf null | **keine oder sehr wenig** |
| Bleibt stehen, wo du ihn loslässt (Federn ausgebaut, Kupplung festgezogen) | **klein, etwa 0.02** auf Pitch und Roll |
| Gieren über Twist-Achse (zentriert sich selbst) | **keine** |
| Kollektiv / Schubhebel | **keine** |
| Pedale mit mechanischem Spiel | so viel wie nötig, nicht mehr |

Der Grund ist derselbe wie oben: Wardogs muss **null** zuverlässig erkennen, sonst dreht der Helikopter endlos weiter. Ein Stick ohne Selbstzentrierung findet die Null nicht von allein – dafür ist die kleine Deadzone da. Kopier fremde Werte nicht, ohne zu wissen, welches Problem sie lösen sollen.

## Das Kollektiv kalibrieren – die einzige Achse, die du sehen kannst

Wardogs zeigt für das Kollektiv eine Anzeige im Bild. Das ist Gold wert, denn damit lässt sich tatsächlich messen statt raten:

1. Schubhebel langsam von ganz unten nach ganz oben.
2. Beobachten, **wann die Anzeige das Maximum erreicht**.
3. Ist sie schon oben, bevor dein Hebel am Anschlag ist, bleibt physischer Weg ungenutzt – Empfindlichkeit senken, bis beide gleichzeitig ankommen.

Ein üblicher Wert liegt bei rund 0.6, wenn die Achse invertiert läuft; bei dir kann es ein ganz anderer sein. **Das ist der einzige Regler in diesem Guide, den du sauber kalibrieren kannst.** Für Pitch, Roll und Yaw fehlt genau diese Anzeige – deshalb weiß niemand mit Sicherheit, ob 1.0 den vollen Stickweg nutzt. Solange das so ist, gilt: nicht unter 1.0, und nach oben vorsichtig.

Und noch eine Einstellung, die unter den Tisch fällt: **selbstzentrierendes Kollektiv abschalten.** Ein Schubhebel, der von allein in die Mitte zurückfedert, ist beim Fliegen eine Zumutung.

## Wo sich zwei erfahrene Piloten widersprechen

Beim Thema Kurven gehen die Meinungen auseinander, und das ist kein Detail:

**Gegen Kurven:** Das Flugmodell sei ohnehin träge und schwerfällig; der Helikopter reagiere langsam. Wer die Eingabe zusätzlich weichzeichnet, kämpfe doppelt. In einer Notlage willst du, dass ein hektischer Ausschlag sofort ankommt. Schweben gelinge trotzdem, gerade weil das Modell so träge ist.

**Für Kurven:** Genau dieses Flugmodell zwinge dich zu vielen winzigen Korrekturen um die Mitte herum, und dort sei ein linearer Verlauf zu grob. Die Kurve nehme nur der Mitte die Schärfe und lasse den Anschlag unangetastet – man verliere also nichts.

Beide fliegen damit gut. Der Unterschied liegt vermutlich weniger im Spiel als in der Hardware: Wer einen Stick mit langem Weg und ausgebauten Federn fährt, hat um die Mitte viel Auflösung und braucht keine Kurve. Wer einen kurzen, straffen Stick mit starker Zentrierung hat, gewinnt durch die Kurve spürbar.

> [!hinweis] Probier beides an einem Abend. Erst 1.0 linear, zehn Landungen. Dann dieselbe Empfindlichkeit mit Kurve, zehn Landungen. Der Unterschied ist an der Landung sofort zu spüren – und die Antwort gilt für deinen Aufbau, nicht für den aus einem Video.

## Was aktuell schlicht nicht gut funktioniert

Ehrlichkeit gehört dazu, das ist Early Access:

- **Kamerasteuerung.** Auf Hat-Schaltern oder Analogachsen ruckelt sie und reagiert unzuverlässig; eine brauchbare Empfindlichkeit dafür fehlt. Leg sie trotzdem auf eine erreichbare Taste – du brauchst sie –, aber such den Fehler nicht bei deinem Stick.
- **Karte.** Zoomen und Bedienen sind auf dem HOTAS umständlich, und eine halbtransparente Karte, mit der man weiterfliegen kann, gibt es nicht.
- **Keine Achsenanzeige** für Pitch, Roll und Yaw – siehe oben.

## Was auf die Finger gehört

Alles, was du **im Flug und im Gefecht** brauchst, muss erreichbar sein, ohne die Hand vom Stick zu nehmen:

- Täuschkörper
- Umschalten Innen-/Außenansicht
- Kameradrehung
- Karte
- Funk
- Fracht abwerfen

Der Rest darf hingehen, wo Platz ist.

## Danke an

Dieser Guide fasst zwei englischsprachige Videos zusammen und ergänzt sie um das, was dazwischen steht:

- **Sim Controls** – [WARDOGS HOTAS Setup Guide | Better Controls & Sensitivity](https://www.youtube.com/watch?v=wtRRZ8nPfFM) (VKB Gladiator + TWCS, Deadzone, Kalibrierung, Kurven)
- **Tote Torres** – [How I Set Up My HOTAS for War Dogs Helicopters](https://www.youtube.com/watch?v=IHcD8c_iqD0) (Logitech G940, Empfindlichkeit hoch, ohne Kurven)

[warleek_video id="wtRRZ8nPfFM" title="WARDOGS HOTAS Setup Guide: Better Controls & Sensitivity" kanal="Sim Controls" bild="guide-hotas-settings"]

Wie du damit tatsächlich fliegst, steht im Guide [Mit dem HOTAS fliegen](/guides/wardogs-hotas-fliegen/). Für die reine Tastenbelegung am Dual-Stick: [Wardogs mit HOTAS – T.16000M einrichten](/guides/wardogs-hotas-dual-stick/).
