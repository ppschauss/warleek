# r/WardogsDeutsch in Betrieb nehmen

Der Code steht (`plugin/warleek-core/inc/reddit.php`), die Grafik liegt in
`dist/reddit/`, sechs fertige Beiträge stehen in
[reddit-beitraege.md](reddit-beitraege.md). Was fehlt, ist alles, was nur auf
reddit.com selbst geht — und das kann niemand von hier aus erledigen.

**Ein Befund vorweg:** Von diesem Rechner aus antwortet Reddit mit **403**. Die
Bot-Abwehr blockt die Rechenzentrums-IP, bevor überhaupt eine Anmeldung
stattfindet. Ein echter Sendeversuch ist auf der Dev-Instanz deshalb nicht
möglich; geprüft wurde alles als Trockenlauf (`--probe`, verschickt nichts).
Ob All-Inkl durchkommt, zeigt erst der erste Lauf dort.

---

## 1 · Der Subreddit

Falls **r/WardogsDeutsch** noch nicht existiert: anlegen unter
`reddit.com/subreddits/create`. Reddit verlangt dafür ein Konto, das einige
Tage alt ist und etwas Karma hat — ein frisches Konto darf keinen Sub gründen.

| Feld | Wert |
|---|---|
| Name | `WardogsDeutsch` |
| Typ | Public |
| Themen | Gaming · Shooter |
| 18+ | nein |

Die Schreibweise ist nur der Anzeigename; Adressen sind bei Reddit nicht
case-sensitiv, `/r/wardogsdeutsch` landet am selben Ort.

### Grafik

Liegt fertig in `dist/reddit/`, erzeugt mit `python3 bin/reddit-assets.py`:

| Datei | Größe | wohin |
|---|---|---|
| `reddit-banner-desktop.png` | 1920×576 | Appearance → Banner |
| `reddit-banner-mobil.png` | 1280×384 | Appearance → Mobile banner |
| `reddit-symbol.png` | 512×512 | Appearance → Community icon |

Unten links im Banner steht absichtlich nichts — dort liegt in der App das
Community-Symbol darüber.

### Beschreibung

**Kurzbeschreibung** (unter dem Namen, 500 Zeichen):

> Die deutschsprachige Wardogs-Community. Taktik, FOB-Bauten, Patch Notes auf
> Deutsch und eine Datenbank mit Waffen, Fahrzeugen und Bauwerken. Für Leute
> aus Deutschland, Österreich und der Schweiz. Zum Mitlesen braucht es kein
> Konto.

**Seitenleiste:**

> **Wardogs auf Deutsch**
>
> Taktischer Militär-Shooter von BULKHEAD, veröffentlicht von Team17, seit
> September 2026 im Early Access. 100 Spieler, drei Fraktionen, echte
> Ballistik, Basenbau — und eine Wirtschaft, die jede Entscheidung bepreist.
>
> Hier: Fragen, Taktik, eigene Messungen, FOB-Bauten, Korrekturen.
> Kein Clan, keine Rekrutierung.
>
> **Regeln**
>
> 1. Deutsch oder Englisch — beides geht.
> 2. Keine Cheats, keine Exploit-Anleitungen. Mechaniken erklären ja, Lücken
>    ausnutzen nein.
> 3. Anständiger Umgang. Auch mit Leuten, die eine Frage zum zehnten Mal
>    stellen.
> 4. Zahlen mit Quelle. Wer etwas gemessen hat, schreibt dazu, wie.
>
> Guides und Datenbank: warleek.de

**Post-Flairs** anlegen: `Guide` · `Frage` · `Patch Notes` · `Datenbank` ·
`FOB-Bau` · `Korrektur` · `Diskussion`.

---

## 2 · Zugang zum Posten

Eine App vom Typ **script** unter `reddit.com/prefs/apps` → „create another
app…":

| Feld | Wert |
|---|---|
| Name | `warleek-core` |
| Typ | **script** |
| redirect uri | `https://www.warleek.de/` (wird nicht benutzt, Pflichtfeld) |

Danach steht die **Client-ID** klein unter dem App-Namen, das
**Client-Secret** daneben.

**Wichtig:** Das Skript-Verfahren funktioniert **nicht** mit aktiver
Zwei-Faktor-Anmeldung am Reddit-Konto. Entweder 2FA für dieses Konto aus, oder
ein eigenes Konto nur zum Posten (das dann Moderator des Subs sein muss).

Die vier Angaben gehören in die `wp-config.php` auf All-Inkl, **nicht** in die
Datenbank und niemals in einen Commit:

```php
define( 'WARLEEK_REDDIT_ID',     '…' );
define( 'WARLEEK_REDDIT_SECRET', '…' );
define( 'WARLEEK_REDDIT_USER',   '…' );
define( 'WARLEEK_REDDIT_PASS',   '…' );
```

Die Felder unter **Warleek → Reddit** sind der Notweg, falls der Zugriff auf
die `wp-config.php` fehlt. Die Konstante schlägt immer die Option.

Prüfen:

```bash
wp warleek reddit --stand                      # „Zugangsdaten: vollständig"?
wp warleek reddit wardogs-munition --probe     # zeigt den Beitrag, sendet nichts
wp warleek reddit wardogs-munition             # sendet wirklich
```

---

## 3 · Reihenfolge, nicht Schalter

Die beiden Automatik-Schalter bleiben am Anfang **aus**. Ein leerer Sub, in den
ab Tag eins täglich ein Datenbank-Eintrag fällt, ist ein RSS-Feed, und so wird
er auch gelesen.

1. **Woche 1–2: von Hand.** Die sechs Beiträge aus
   [reddit-beitraege.md](reddit-beitraege.md), einer alle zwei bis drei Tage.
   Sie stehen für sich, stellen Fragen und laden zum Antworten ein — das ist
   es, was einen Sub startet.
2. **Dann `reddit_auto`.** Neue Guides gehen beim Veröffentlichen automatisch
   raus. Risiko gering: Es sind wenige Beiträge, und jeder hat Substanz.
3. **Zuletzt `reddit_drip`.** Täglich um 17:30 ein älterer Guide oder
   Datenbank-Eintrag. Derzeit **149 offene Beiträge** — das läuft knapp fünf
   Monate.

**Zu neuen Konten:** Reddit drosselt Konten ohne Historie hart, und ein Konto,
das aus dem Stand täglich postet, fällt der Spam-Erkennung auf. Nach den
ersten automatischen Beiträgen einmal ausgeloggt im Browser prüfen, ob sie
öffentlich sichtbar sind — ein Shadowban ist von innen unsichtbar.

---

## 4 · Wie ein Beitrag aussieht

**Guide** — Direktantwort, bis zu drei Fragen, Quellenlage, Link:

```
Munition wählen: FMJ, Flesh Damage und Armor Piercing

**Welche Munition sollte man in Wardogs nehmen?**

Gegen Ungepanzerte Flesh Damage, denn es verdoppelt den Schaden. …

**Welche Munition macht in Wardogs am meisten Schaden?**
…
---
**Quellen:** metabot.gg (Schießstand, Oktober 2026) · allthings.how
**Im Spiel nachgeprüft:** nein
Langfassung mit allen Quellen und dem Änderungsverlauf: https://…
```

**Datenbank-Eintrag** — der Titel trägt die Zahl, die Tabelle die Werte:

```
Wardogs MP5 – $1.500, Freischaltung: Medic 15

MP5 – Maschinenpistole in WARDOGS. Stand: Oktober 2026 · Early Access.

| Angabe | Wert |
|---|---|
| Preis | $1.500 |
| Freischaltung | Medic 15 |
| Freischaltgebühr (einmalig) | $75.000 |
| Kaliber | 9 × 19 mm |
| Rolle | Maschinenpistole |
---
**Quellen:** …
**Im Spiel nachgeprüft:** …
Langfassung …
```

Drei Entscheidungen stecken darin, alle aus demselben Grund — ein kurzer
Beitrag verträgt keine Wiederholung:

- **Das Datenblatt steht im Beitrag.** Ohne es bestand ein Eintrag aus einem
  Satz und einem Link.
- **Der Eintrag bekommt keinen FAQ-Abschnitt.** Seine Fragen entstehen aus
  denselben Werten wie die Tabelle; auf der Seite ist das für Antwortmaschinen
  gedacht, hier wäre es dieselbe Zahl zum vierten Mal.
- **Sätze, die nur das Datenblatt wiederholen, fallen weg** —
  „Kaliber 9 × 19 mm." verschwindet, „Stand: Oktober 2026 · Early Access."
  und „Von Anfang an verfügbar …" bleiben.

Abgesichert durch `tests/reddit-test.php` (stellt keine Netzanfrage).

---

## 5 · Wenn etwas schiefgeht

| Meldung | Ursache |
|---|---|
| `Anmeldung fehlgeschlagen (HTTP 401)` | Falsche ID/Secret, oder 2FA am Konto aktiv. |
| `Anmeldung fehlgeschlagen (HTTP 403)` | Reddit blockt die Server-IP. Von einer anderen Leitung versuchen. |
| `Reddit lehnt ab: SUBREDDIT_NOEXIST` | Subreddit-Name falsch geschrieben oder Sub gelöscht. |
| `Reddit lehnt ab: RATELIMIT` | Zu schnell hintereinander. Der Drip postet einen pro Tag — das trifft nur Handläufe. |
| `Schon gepostet.` | Steht als `_warleek_reddit` am Beitrag. Löschen erlaubt ein erneutes Posten. |

Die letzten 40 Zeilen Protokoll: `wp warleek reddit --stand`.
