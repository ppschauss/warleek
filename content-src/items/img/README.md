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
2. **Offizielles Presse- und Store-Material** von Team17/BULKHEAD, soweit deren
   Presse- oder Fan-Content-Bedingungen es erlauben.

**Nicht zulässig:** aus fremden Datenbanken extrahierte Symbole oder Renderings.
Deren Sammlung ist nach § 87b UrhG geschützt, und übernommene Bilddateien sind
ohnehin fremdes Material.

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

## Format

Breite 1024 px, WebP bevorzugt (`cwebp -q 85 quelle.png -o wardogs-ak-74.webp`).
Screenshots mit feinen Kanten dürfen PNG bleiben.

## Einspielen

```bash
php bin/items-media.php --dry-run   # zeigt die Zuordnung
php bin/items-media.php             # übernimmt die Dateien
./manage.sh seed                    # setzt sie als Beitragsbilder
```

Solange hier nichts liegt, bleiben die Einträge bildlos. Das ist beabsichtigt:
lieber kein Bild als ein falsches.
