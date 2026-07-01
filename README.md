# MantisBT IssueRecurrence

Ein MantisBT-Plugin zum Erstellen **wiederkehrender Tickets** (Serien-Issues) aus
Vorlagen – vergleichbar mit Serienterminen in Outlook.

Du legst ein Ticket als **Vorlage** an, definierst eine **Wiederholungsregel**
(täglich / wöchentlich / monatlich / jährlich) und MantisBT erzeugt daraus
automatisch in den festgelegten Abständen neue Tickets.

---

## Funktionen

- 📋 **Vorlagenverwaltung** – zentrale Übersicht aller wiederkehrenden Tickets mit Status, nächster Ausführung und Anzahl bereits erstellter Tickets.
- 🔁 **Flexible Wiederholungsregeln**
  - **Täglich** – alle *N* Tage
  - **Wöchentlich** – alle *N* Wochen an ausgewählten Wochentagen (z. B. Mo + Mi + Fr)
  - **Monatlich** – alle *N* Monate an einem bestimmten Tag (inkl. „letzter Tag des Monats")
  - **Jährlich** – alle *N* Jahre an einem bestimmten Tag/Monat
- 🗓️ **Start- und (optionales) Enddatum** mit Uhrzeit.
- 🎯 **Vollständige Ticket-Vorlage** – Projekt, Kategorie, Zusammenfassung, Beschreibung, Bearbeiter, Priorität, Schweregrad, Reproduzierbarkeit, Sichtbarkeit und optionales Fälligkeitsdatum.
- 🧩 **Benutzerdefinierte Felder (Custom Fields)** – die dem gewählten Projekt zugeordneten Custom Fields werden im Formular angezeigt, in der Vorlage gespeichert und beim Erstellen auf das neue Ticket übertragen. Beim Wechsel des Projekts lädt das Formular automatisch die passenden Felder.
- 🔣 **Platzhalter** in Zusammenfassung/Beschreibung: `{date}`, `{datetime}`, `{time}`, `{year}`, `{month}`, `{day}`, `{week}`.
- ⏯️ **Aktivieren/Pausieren** einzelner Regeln, ohne sie zu löschen.
- ⏱️ **Zwei Auslöse-Mechanismen** (kombinierbar):
  1. **Cronjob** (empfohlen, präzise) über `cli/run_recurrence.php`.
  2. **Fallback bei Seitenaufruf** (gedrosselt) – falls kein Cron eingerichtet werden kann.
- 🧮 **Catch-up** – verpasste Termine (z. B. wenn der Server aus war) werden beim nächsten Lauf nachgeholt.

---

## Installation

1. Repository in das Plugin-Verzeichnis deiner MantisBT-Installation klonen
   bzw. den Ordner dorthin kopieren. Der Ordner **muss** `IssueRecurrence` heißen:

   ```bash
   cd /pfad/zu/mantisbt/plugins
   git clone https://github.com/marcwoge/mantisBT-IssueRecurrence.git IssueRecurrence
   ```

2. In MantisBT als Administrator anmelden → **Verwaltung → Plugins verwalten**.
3. Bei **Recurring Issues / Wiederkehrende Tickets** auf **Installieren** klicken.
   (Die benötigten Datenbanktabellen werden automatisch angelegt.)

> Kompatibilität: MantisBT **2.x**, PHP **7.4+**.

---

## Cronjob einrichten (empfohlen)

Damit Tickets zuverlässig und pünktlich erzeugt werden, sollte das CLI-Skript
regelmäßig laufen. Die Häufigkeit bestimmt die zeitliche Genauigkeit
(z. B. stündlich genügt für tagesgenaue Serien).

**Linux / cron:**

```cron
# jede Stunde
0 * * * * php /pfad/zu/mantisbt/plugins/IssueRecurrence/cli/run_recurrence.php >> /var/log/mantis_recurrence.log 2>&1
```

**Windows / Aufgabenplanung:**

```bat
php D:\mantisbt\plugins\IssueRecurrence\cli\run_recurrence.php
```

Das Skript meldet sich als der unter
**Plugin-Konfiguration → `script_login_user`** hinterlegte Benutzer an
(Standard: `administrator`). Dieser Benutzer erscheint als Ersteller in der
Ticket-Historie und muss die nötigen Rechte im Zielprojekt besitzen.

### Ohne Cron: Fallback bei Seitenaufruf

Ist kein Cron möglich, kann der **Trigger bei Seitenaufruf** aktiviert bleiben
(Plugin-Konfiguration). Dann wird bei Seitenaufrufen – höchstens einmal pro
eingestelltem Intervall (Standard 1 Stunde) – geprüft, ob Tickets fällig sind.
Das ist abhängig vom Seitenverkehr und daher weniger präzise als ein Cronjob.

---

## Benutzung

1. Im Hauptmenü **„Wiederkehrende Tickets"** öffnen (oder Verwaltung → Wiederkehrende Tickets).
2. **Neue Vorlage** anlegen:
   - Bezeichnung + Ticket-Inhalt (wie beim normalen Ticket) ausfüllen.
   - Wiederholungsregel und Startdatum festlegen.
3. Speichern – die nächste Ausführung wird automatisch berechnet und angezeigt.
4. Über **Jetzt ausführen** lässt sich ein Lauf manuell anstoßen (z. B. zum Testen).

---

## Konfiguration

Unter **Verwaltung → Plugins verwalten → IssueRecurrence → Konfiguration**:

| Option | Beschreibung | Standard |
| ------ | ------------ | -------- |
| `manage_threshold` | Zugriffsebene zum Verwalten der Wiederholungen | `MANAGER` |
| `page_trigger_enabled` | Fallback-Trigger bei Seitenaufruf | `ON` |
| `page_trigger_interval` | Mindestabstand (Sek.) zwischen zwei Fallback-Prüfungen | `3600` |
| `script_login_user` | Benutzer, unter dem das Cron-Skript handelt | `administrator` |

---

## Projektstruktur

```
IssueRecurrence/
├── IssueRecurrence.php          # Haupt-Plugin-Klasse (Registrierung, Schema, Hooks)
├── core/
│   ├── recurrence_api.php       # CRUD der Vorlagen + Ticket-Erstellung
│   └── schedule_api.php         # Berechnung der Wiederholungstermine
├── pages/
│   ├── manage.php               # Übersicht / Verwaltung
│   ├── template_edit_page.php   # Formular (Anlegen & Bearbeiten)
│   ├── template_save.php        # Speichern
│   ├── template_delete.php      # Löschen
│   ├── template_toggle.php      # Aktivieren / Pausieren
│   ├── run.php                  # Manueller Lauf
│   ├── config.php               # Konfigurationsseite
│   └── config_save.php          # Konfiguration speichern
├── cli/
│   └── run_recurrence.php       # Cron-/CLI-Einstiegspunkt
├── lang/
│   ├── strings_german.txt
│   └── strings_english.txt
└── files/
    └── issue_recurrence.css
```

---

## Datenmodell

**`plugin_IssueRecurrence_template`** – die Vorlagen/Regeln (Ticket-Felder +
Wiederholungsparameter + `next_run`/`last_run`).

**`plugin_IssueRecurrence_history`** – Protokoll, welches Ticket aus welcher
Vorlage wann erzeugt wurde.

**`plugin_IssueRecurrence_cf_value`** – die je Vorlage gespeicherten Werte der
benutzerdefinierten Felder (`template_id`, `field_id`, `value`).

> Hinweis zu Custom Fields: Angezeigt und gespeichert werden die Felder, die dem
> im Formular gewählten Projekt zugeordnet sind. Wird das Projekt einer Vorlage
> später geändert, sollten die projektspezifischen Felder erneut geprüft werden.

---

## Lokal testen (Docker)

Im Ordner [`docker/`](docker/) liegt eine komplette MantisBT-Sandbox
(MantisBT 2.28 + MariaDB + Mailpit). Damit wurde dieses Plugin end-to-end
verifiziert (Installation, Vorlagen, Custom Fields, manueller Lauf und
Cron-Skript).

```bash
docker compose up -d --build
# MantisBT: http://localhost:8989  (Installer einmalig abschließen)
```

Details siehe [docker/README.md](docker/README.md).

## Lizenz

[GNU General Public License v2.0](LICENSE) – wie MantisBT selbst.
