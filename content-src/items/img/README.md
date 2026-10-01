# Bilder für die Datenbank

Hier gehören **nur Originalbilder** hin – Aufnahmen aus dem Spiel oder offizielles
Material von BULKHEAD/Team17. Keine erzeugten Bilder: Ein KI-Bild zeigt nicht die
Waffe, die im Eintrag steht, und wäre damit genau die Sorte kleiner Unwahrheit,
gegen die die Herkunftsangabe gebaut wurde.

## Zulässige Quellen

1. **Eigene Aufnahmen aus dem Spiel.** Der sauberste Weg: Screenshot im Händler-
   oder Ausrüstungsmenü, zugeschnitten auf den Gegenstand. Wer das Spiel besitzt,
   darf davon Bildschirmfotos für redaktionelle Zwecke verwenden; wir nennen die
   Quelle im Eintrag.
2. **Offizielles Pressematerial** von Team17/BULKHEAD. Beide Pressekits sind frei
   herunterladbar und enthalten Key-Art, Logos und 4K-Screenshots:

   - Team17 (Sept. 2026, gebrandete Screenshots):
     <https://www.team17.com/press-and-creator-hub>
   - BULKHEAD (Jan. 2026, ungebrandete Screenshots):
     <https://www.wardogs.com/press-kit>

   Den Kits liegt kein Lizenztext bei. Pressematerial ist für die Berichterstattung
   gedacht, und genau dafür nutzen wir es – mit Quellenangabe am Eintrag. Wer es
   schriftlich braucht, fragt `pr@team17.com`.

3. **Spiel-Symbole aus einer Fan-Datenbank**, konkret `wardogshub.uk`. Deren eigene
   [Credits-Seite](https://wardogshub.uk/en/image-credits/) stellt klar, dass es sich
   um Spiel-Assets handelt: *„Game interface elements are © BULKHEAD / Team17, used
   unchanged as reference marks … all game material belongs to its rights holders."*
   Es ist also kein fremdes Werk, das wir übernehmen, sondern dasselbe Material aus
   derselben Quelle — und die Rechtsgrundlage ist dieselbe: unverändert, redaktionell,
   mit Nennung des Rechteinhabers. Holen mit `php bin/items-bilder-holen.php`
   (eine Anfrage pro Sekunde, eigene Kennung); rückgängig mit `--entfernen`.

**Nicht zulässig:** die *Datensammlung* einer fremden Seite nachbauen — die ist nach
§ 87b UrhG geschützt. Die Zahlen in `items.json` sind recherchiert und im Spiel
abgeglichen, nicht abgeschrieben.

## Benennung

Der Dateiname bestimmt die Zuordnung:

```
wardogs-ak-74.webp     → Bild für genau diesen Eintrag
typ-waffe.webp         → Ersatzbild für alle Waffen ohne eigenes Bild
typ-fahrzeug.webp      → dasselbe für Fahrzeuge
typ-emplacement.webp   → Emplacements
typ-bauwerk.webp       → Bauwerke
```

Die Slugs aller Einträge stehen in `plugin/warleek-core/content/_content/items.json`.
Passt ein Dateiname zu nichts, meldet das Skript es – es wird nichts still übergangen.

## Quellenangabe (`credits.json`)

Fremdes Material wird **nur mit Quelle** gezeigt. `credits.json` setzt sie pro Datei,
Schlüssel ist der Dateiname ohne Endung:

```json
{
  "typ-waffe": {
    "alt": "Was auf dem Bild zu sehen ist",
    "credit": "Offizielles Pressematerial · © BULKHEAD / Team17"
  }
}
```

`credit` erscheint unter dem Eintrag im Kasten „Woher die Angaben stammen". Eigene
Aufnahmen brauchen keinen Eintrag – dann steht dort nichts, und das stimmt dann auch.

## Format

Breite 1024 px, WebP bevorzugt (`cwebp -q 85 quelle.png -o wardogs-ak-74.webp`).
Screenshots mit feinen Kanten dürfen PNG bleiben.

## Einspielen

```bash
php bin/items-media.php --dry-run   # zeigt die Zuordnung
php bin/items-media.php             # übernimmt die Dateien
./manage.sh seed                    # setzt sie als Beitragsbilder
```

## Drei Stufen

Der Installer sucht in dieser Reihenfolge und nimmt das erste, was er findet:

```
item-<slug>          eigenes Bild für genau diesen Eintrag
item-gruppe-<gruppe> Bild der Gruppe (alle Scharfschützengewehre, alle Helikopter …)
item-typ-<typ>       Bild der Kategorie
```

Die Gruppe steht als `gruppe` in `items.json` und wird aus der Rolle abgeleitet.

## Stand

Die Gruppen- und Kategoriebilder liegen aus dem offiziellen Pressematerial vor. Für
*Stellungen* und *Bauwerke* zeigen die Pressekits nichts Passendes – dort steht
vorerst die Key-Art, im Alt-Text als Platzhalter gekennzeichnet. Bilder einzelner
Gegenstände gibt es nur im Spiel selbst; sie müssen aus eigenen Aufnahmen kommen.

Was fehlt, bleibt bildlos. Das ist beabsichtigt: lieber kein Bild als ein falsches.
