# Prüfliste Datenbank

Erzeugt von `bin/datenbank-pruefen.py` über 198 Einträge.

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
Traceback (most recent call last):
  File "/mnt/cache/appdata/scratch/wardogs-gc/bin/datenbank-pruefen.py", line 450, in <module>
    sys.exit(main())
             ^^^^^^
  File "/mnt/cache/appdata/scratch/wardogs-gc/bin/datenbank-pruefen.py", line 403, in main
    marke = "belegt: " + marke if marke else ("**zwei gegen uns**" if feld in einig else "")
                                  ^^^^^
UnboundLocalError: cannot access local variable 'marke' where it is not associated with a value
