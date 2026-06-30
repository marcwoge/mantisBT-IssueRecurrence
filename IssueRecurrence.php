<?php
/**
 * IssueRecurrence - MantisBT Plugin
 *
 * Erstellt wiederkehrende Tickets (Issues) auf Basis von Vorlagen (Templates)
 * mit einer Wiederholungsregel (taeglich / woechentlich / monatlich / jaehrlich),
 * vergleichbar mit Serienterminen in Outlook.
 *
 * @package   IssueRecurrence
 * @copyright Copyright (C) 2026  marcwoge
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License
 * @link      https://github.com/marcwoge/mantisBT-IssueRecurrence
 */

if( !defined( 'MANTIS_DIR' ) ) {
	# Erlaubt das Laden ueber den normalen Mantis-Plugin-Mechanismus.
}

/**
 * Haupt-Plugin-Klasse fuer IssueRecurrence.
 */
class IssueRecurrencePlugin extends MantisPlugin {

	/**
	 * Registriert die Plugin-Metadaten.
	 * @return void
	 */
	function register() {
		$this->name        = plugin_lang_get( 'plugin_title' );
		$this->description  = plugin_lang_get( 'plugin_description' );
		$this->page         = 'config';

		$this->version      = '0.1.0';
		$this->requires     = array(
			'MantisCore' => '2.0.0',
		);

		$this->author       = 'marcwoge';
		$this->contact      = '';
		$this->url          = 'https://github.com/marcwoge/mantisBT-IssueRecurrence';
	}

	/**
	 * Standard-Konfigurationswerte.
	 * @return array
	 */
	function config() {
		return array(
			# Zugriffsebene, ab der die Verwaltung der Wiederholungen erlaubt ist.
			'manage_threshold' => MANAGER,
			# Erlaubt den Fallback, faellige Vorlagen auch bei Seitenaufrufen zu pruefen
			# (zusaetzlich zum bevorzugten Cron-Lauf).
			'page_trigger_enabled' => ON,
			# Mindestabstand (Sekunden) zwischen zwei Hook-getriggerten Pruefungen,
			# um Last bei jedem Seitenaufruf zu vermeiden. Standard: 1 Stunde.
			'page_trigger_interval' => 3600,
			# Benutzername, unter dem das Cron-Skript (cli/run_recurrence.php) handelt.
			# Dieser Benutzer wird als "ausfuehrender" Akteur fuer den History-Eintrag genutzt.
			'script_login_user' => 'administrator',
		);
	}

	/**
	 * Definiert das Datenbankschema der Plugin-Tabellen.
	 * Wird beim Installieren / Upgrade des Plugins automatisch angewendet.
	 * @return array
	 */
	function schema() {
		return array(
			# --- Tabelle: Vorlagen / Wiederholungsregeln ---
			array( 'CreateTableSQL', array( plugin_table( 'template' ), "
				id                  I       NOTNULL UNSIGNED AUTOINCREMENT PRIMARY,
				name                C(128)  NOTNULL DEFAULT '\'\'',
				enabled             L       NOTNULL DEFAULT '1',
				project_id          I       NOTNULL UNSIGNED DEFAULT '0',
				reporter_id         I       NOTNULL UNSIGNED DEFAULT '0',
				handler_id          I       NOTNULL UNSIGNED DEFAULT '0',
				category_id         I       NOTNULL UNSIGNED DEFAULT '0',
				summary             C(128)  NOTNULL DEFAULT '\'\'',
				description         XL      NOTNULL,
				priority            I       NOTNULL UNSIGNED DEFAULT '30',
				severity            I       NOTNULL UNSIGNED DEFAULT '50',
				reproducibility     I       NOTNULL UNSIGNED DEFAULT '70',
				view_state          I       NOTNULL UNSIGNED DEFAULT '10',
				due_date_offset     I       NOTNULL DEFAULT '0',
				freq_type           C(16)   NOTNULL DEFAULT '\'daily\'',
				freq_interval       I       NOTNULL UNSIGNED DEFAULT '1',
				weekdays            C(32)   NOTNULL DEFAULT '\'\'',
				day_of_month        I       NOTNULL DEFAULT '1',
				month_of_year       I       NOTNULL DEFAULT '1',
				start_date          I       UNSIGNED NOTNULL DEFAULT '1',
				end_date            I       UNSIGNED,
				last_run            I       UNSIGNED,
				next_run            I       UNSIGNED,
				created_at          I       UNSIGNED NOTNULL DEFAULT '1',
				updated_at          I       UNSIGNED NOTNULL DEFAULT '1'
				", array( 'mysql' => 'DEFAULT CHARSET=utf8' ) ) ),
			array( 'CreateIndexSQL', array( 'idx_irt_next_run', plugin_table( 'template' ), 'enabled,next_run' ) ),

			# --- Tabelle: Protokoll erzeugter Tickets (Historie) ---
			array( 'CreateTableSQL', array( plugin_table( 'history' ), "
				id                  I       NOTNULL UNSIGNED AUTOINCREMENT PRIMARY,
				template_id         I       NOTNULL UNSIGNED DEFAULT '0',
				bug_id              I       NOTNULL UNSIGNED DEFAULT '0',
				created_at          I       UNSIGNED NOTNULL DEFAULT '1'
				", array( 'mysql' => 'DEFAULT CHARSET=utf8' ) ) ),
			array( 'CreateIndexSQL', array( 'idx_irh_template', plugin_table( 'history' ), 'template_id' ) ),

			# --- Tabelle: Custom-Field-Werte je Vorlage ---
			array( 'CreateTableSQL', array( plugin_table( 'cf_value' ), "
				id                  I       NOTNULL UNSIGNED AUTOINCREMENT PRIMARY,
				template_id         I       NOTNULL UNSIGNED DEFAULT '0',
				field_id            I       NOTNULL UNSIGNED DEFAULT '0',
				value               XL      NOTNULL
				", array( 'mysql' => 'DEFAULT CHARSET=utf8' ) ) ),
			array( 'CreateIndexSQL', array( 'idx_ircf_template_field', plugin_table( 'cf_value' ), 'template_id,field_id', array( 'UNIQUE' ) ) ),
		);
	}

	/**
	 * Registriert die Event-Hooks.
	 * @return array
	 */
	function hooks() {
		return array(
			'EVENT_MENU_MAIN'        => 'menu_main',
			'EVENT_MENU_MANAGE'      => 'menu_manage',
			'EVENT_LAYOUT_RESOURCES' => 'resources',
			# Fallback-Trigger: prueft bei Seitenaufrufen, ob faellige Vorlagen existieren.
			'EVENT_CORE_READY'       => 'maybe_run_on_page',
		);
	}

	/**
	 * Bindet das Plugin-Stylesheet ein.
	 * @return string
	 */
	function resources() {
		return '<link rel="stylesheet" type="text/css" href="'
			. plugin_file( 'issue_recurrence.css' ) . '"/>';
	}

	/**
	 * Fuegt einen Eintrag im Hauptmenue hinzu (nur fuer berechtigte Nutzer).
	 * @return array
	 */
	function menu_main() {
		if( access_has_project_level( plugin_config_get( 'manage_threshold' ) ) ) {
			return array(
				array(
					'title' => plugin_lang_get( 'menu_recurrence' ),
					'access_level' => plugin_config_get( 'manage_threshold' ),
					'url' => plugin_page( 'manage' ),
					'icon' => 'fa-refresh',
				),
			);
		}
		return array();
	}

	/**
	 * Fuegt einen Eintrag im Verwaltungsmenue hinzu.
	 * @return string
	 */
	function menu_manage() {
		return array(
			'<a href="' . plugin_page( 'manage' ) . '">' . plugin_lang_get( 'menu_recurrence' ) . '</a>',
		);
	}

	/**
	 * Fallback-Trigger: prueft bei Seitenaufrufen (gedrosselt) faellige Vorlagen.
	 * Der bevorzugte Weg bleibt der Cron-Lauf via cli/run_recurrence.php.
	 * @return void
	 */
	function maybe_run_on_page() {
		if( OFF == plugin_config_get( 'page_trigger_enabled' ) ) {
			return;
		}

		# Nur fuer angemeldete Nutzer ausfuehren (nicht auf Login-/Anmeldeseiten
		# oder im anonymen Kontext), damit der History-Eintrag einen Akteur hat.
		if( !auth_is_user_authenticated() ) {
			return;
		}

		# Drosselung: nur in festgelegten Intervallen pruefen.
		$t_interval = (int)plugin_config_get( 'page_trigger_interval' );
		$t_last = (int)plugin_config_get( 'page_trigger_last', 0 );
		$t_now = time();
		if( $t_now - $t_last < $t_interval ) {
			return;
		}
		# Sofort den Zeitstempel setzen, um parallele Laeufe zu vermeiden.
		plugin_config_set( 'page_trigger_last', $t_now );

		require_once( $this->basepath . '/core/recurrence_api.php' );
		# Fehler hier duerfen niemals den normalen Seitenaufruf stoeren.
		try {
			issue_recurrence_run_due();
		} catch( Exception $e ) {
			# bewusst still: Seitenaufruf darf nicht scheitern.
		}
	}

	/**
	 * Laedt die Core-API beim Initialisieren.
	 * @return void
	 */
	function init() {
		require_once( $this->basepath . '/core/schedule_api.php' );
		require_once( $this->basepath . '/core/recurrence_api.php' );
	}
}
