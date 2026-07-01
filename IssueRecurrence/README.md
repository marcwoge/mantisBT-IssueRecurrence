# IssueRecurrence – MantisBT Plugin

Erstellt **wiederkehrende Tickets** (Serien-Issues) aus Vorlagen – vergleichbar
mit Serienterminen in Outlook. Du legst ein Ticket als **Vorlage** an, definierst
eine **Wiederholungsregel** (täglich / wöchentlich / monatlich / jährlich), und
MantisBT erzeugt daraus automatisch in den festgelegten Abständen neue Tickets.

## Funktionen

- 📋 **Vorlagenverwaltung** – Übersicht aller wiederkehrenden Tickets mit Status, nächster Ausführung und Anzahl bereits erstellter Tickets.
- 🔁 **Wiederholungsregeln** – täglich, wöchentlich (mit Wochentagsauswahl), monatlich (inkl. „letzter Tag"), jährlich; jeweils alle *N* Einheiten, mit Start- und optionalem Enddatum.
- 🎯 **Vollständige Ticket-Vorlage** – Projekt, Kategorie, Zusammenfassung, Beschreibung, Bearbeiter, Priorität, Schweregrad, Reproduzierbarkeit, Sichtbarkeit, optionales Fälligkeitsdatum.
- 🧩 **Benutzerdefinierte Felder (Custom Fields)** des Zielprojekts werden angezeigt, gespeichert und beim Erstellen auf das Ticket übertragen.
- ♻️ **Bestehendes Ticket umwandeln** – Button „In wiederkehrendes Ticket umwandeln" in der Ticket-Ansicht; die Vorlage verlinkt anschließend auf das Ursprungsticket.
- 🔣 **Platzhalter** in Zusammenfassung/Beschreibung: `{date}`, `{datetime}`, `{time}`, `{year}`, `{month}`, `{day}`, `{week}`.
- ⏱️ **Zwei Auslöser** – Cronjob (`cli/run_recurrence.php`, empfohlen) und Fallback bei Seitenaufruf (gedrosselt). Verpasste Termine werden nachgeholt.
- 🌐 Deutsch und Englisch.

## Installation

Den Ordner `IssueRecurrence` in das Plugin-Verzeichnis deiner MantisBT-Installation
legen (der Ordner **muss** `IssueRecurrence` heißen):

```
mantisbt/plugins/IssueRecurrence/
```

Dann in MantisBT als Administrator: **Verwaltung → Plugins verwalten** →
bei *Recurring Issues* auf **Installieren**. Die Datenbanktabellen werden
automatisch angelegt.

> Kompatibilität: MantisBT **2.x**, PHP **7.4+** (getestet mit 2.28 / PHP 8.2).

## Cronjob einrichten (empfohlen)

```cron
# jede Stunde
0 * * * * php /pfad/zu/mantisbt/plugins/IssueRecurrence/cli/run_recurrence.php >> /var/log/mantis_recurrence.log 2>&1
```

Das Skript meldet sich als der unter **Plugin-Konfiguration → `script_login_user`**
hinterlegte Benutzer an (Standard: `administrator`) und erzeugt alle fälligen
Tickets. Ohne Cron kann der Fallback-Trigger bei Seitenaufruf aktiviert bleiben.

## Konfiguration

**Verwaltung → Plugins verwalten → IssueRecurrence → Konfiguration**:

| Option | Beschreibung | Standard |
| ------ | ------------ | -------- |
| `manage_threshold` | Zugriffsebene zum Verwalten der Wiederholungen | `MANAGER` |
| `page_trigger_enabled` | Fallback-Trigger bei Seitenaufruf | `ON` |
| `page_trigger_interval` | Mindestabstand (Sek.) zwischen zwei Fallback-Prüfungen | `3600` |
| `script_login_user` | Benutzer, unter dem das Cron-Skript handelt | `administrator` |

## Lizenz

[MIT License](LICENSE) © 2026 Marc-Philipp Woge
