# Lokale MantisBT-Testumgebung

Komplett lauffähige MantisBT-Instanz zum Ausprobieren des **IssueRecurrence**
Plugins – inklusive Datenbank und einem Mail-Catcher, der alle ausgehenden
E-Mails abfängt (es wird also nichts wirklich verschickt).

| Dienst | URL | Zugang |
| --- | --- | --- |
| MantisBT | http://localhost:8989 | `administrator` / `root` |
| Mailpit (E-Mails ansehen) | http://localhost:8025 | – |
| MariaDB | intern `db:3306` | `mantisbt` / `mantisbt` |

> Reines Test-Setup. Die Zugangsdaten/Salt in `docker-compose.yml` und
> `config_inc.php` sind absichtlich simpel – **nicht in Produktion verwenden.**

## Starten

Aus dem Projekt-Stammverzeichnis (eine Ebene über `docker/`):

```bash
docker compose up -d --build
```

Beim ersten Start wird das MantisBT-Image gebaut (lädt das offizielle Release
herunter). Danach **einmalig** den Installer abschließen:

1. http://localhost:8989/admin/install.php öffnen.
2. Die Datenbankfelder sind bereits aus `docker/config_inc.php` vorbefüllt
   (Host `db`, DB `bugtracker`, Benutzer `mantisbt`/`mantisbt`).
3. Auf **„Install/Upgrade Database"** klicken → die Tabellen werden angelegt.
4. Fertig. Anmelden mit `administrator` / `root`.

## Plugin installieren

1. In MantisBT: **Manage → Manage Plugins**.
2. Bei *Recurring Issues* (IssueRecurrence) auf **Install** klicken.
3. Konfigurieren unter **Manage → Manage Plugins → IssueRecurrence**.

Das Plugin ist bereits in den Container gemountet
(Repo-Stamm → `/var/www/html/plugins/IssueRecurrence`). Änderungen an den
Plugin-Dateien wirken sofort, ohne Neubau.

## Wiederkehrende Tickets testen

1. Ein Projekt (mit Kategorie) anlegen, falls noch keins existiert.
2. Im Hauptmenü **„Wiederkehrende Tickets"** öffnen → **Neue Vorlage** anlegen.
3. Den Erzeugungs-Lauf auslösen – ohne auf den nächsten Termin zu warten:

   ```bash
   MSYS_NO_PATHCONV=1 docker compose exec mantis \
     php /var/www/html/plugins/IssueRecurrence/cli/run_recurrence.php
   ```

   Das Skript meldet sich als der konfigurierte `script_login_user` an
   (Standard `administrator`) und erzeugt alle fälligen Tickets.
4. Die erzeugten Tickets in MantisBT ansehen (View Issues).

## Häufige Befehle

```bash
# Logs ansehen
docker compose logs -f mantis

# In den MantisBT-Container
docker compose exec mantis bash

# Stoppen (Daten bleiben erhalten)
docker compose down

# Stoppen und ALLES löschen (DB-Daten zurücksetzen)
docker compose down -v
```

## Hinweis zu Windows / Git Bash

Wird `docker compose exec` aus Git Bash mit einem absoluten Container-Pfad
aufgerufen, wandelt Git Bash den Pfad evtl. um. Dann die Variante mit
`MSYS_NO_PATHCONV=1` davor verwenden (siehe oben).
