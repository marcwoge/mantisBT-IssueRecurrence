<?php
/**
 * IssueRecurrence - Core-API
 *
 * Verwaltung der Vorlagen (CRUD) und Erzeugung neuer Tickets aus faelligen Vorlagen.
 *
 * @package IssueRecurrence
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License
 */

if( !function_exists( 'plugin_lang_get' ) ) {
	exit( 1 );
}

require_once( __DIR__ . '/schedule_api.php' );

require_api( 'bug_api.php' );
require_api( 'category_api.php' );
require_api( 'database_api.php' );
require_api( 'user_api.php' );
require_api( 'project_api.php' );

/**
 * Liefert die Standardstruktur einer leeren Vorlage.
 * @return array
 */
function issue_recurrence_template_blank() {
	return array(
		'id'              => 0,
		'name'            => '',
		'enabled'         => 1,
		'project_id'      => helper_get_current_project(),
		'reporter_id'     => auth_get_current_user_id(),
		'handler_id'      => 0,
		'category_id'     => 0,
		'summary'         => '',
		'description'     => '',
		'priority'        => 30,
		'severity'        => 50,
		'reproducibility' => 70,
		'view_state'      => VS_PUBLIC,
		'due_date_offset' => 0,
		'freq_type'       => 'weekly',
		'freq_interval'   => 1,
		'weekdays'        => (string)date( 'w' ),
		'day_of_month'    => (int)date( 'j' ),
		'month_of_year'   => (int)date( 'n' ),
		'start_date'      => time(),
		'end_date'        => null,
		'last_run'        => null,
		'next_run'        => null,
	);
}

/**
 * Laedt eine Vorlage anhand der ID.
 * @param int $p_id Vorlagen-ID.
 * @return array|null
 */
function issue_recurrence_template_get( $p_id ) {
	$t_table = plugin_table( 'template' );
	$t_query = 'SELECT * FROM ' . $t_table . ' WHERE id = ' . db_param();
	$t_result = db_query( $t_query, array( (int)$p_id ) );
	$t_row = db_fetch_array( $t_result );
	return $t_row ? $t_row : null;
}

/**
 * Laedt alle Vorlagen (optional gefiltert nach Projekt).
 * @param int|null $p_project_id Projekt-ID oder null fuer alle.
 * @return array Liste von Vorlagen.
 */
function issue_recurrence_template_get_all( $p_project_id = null ) {
	$t_table = plugin_table( 'template' );
	$t_params = array();
	$t_query = 'SELECT * FROM ' . $t_table;
	if( $p_project_id !== null && (int)$p_project_id !== ALL_PROJECTS ) {
		$t_query .= ' WHERE project_id = ' . db_param();
		$t_params[] = (int)$p_project_id;
	}
	$t_query .= ' ORDER BY enabled DESC, next_run ASC, name ASC';
	$t_result = db_query( $t_query, $t_params );
	$t_list = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_list[] = $t_row;
	}
	return $t_list;
}

/**
 * Speichert (erstellt oder aktualisiert) eine Vorlage.
 * Berechnet dabei automatisch den naechsten Faelligkeitszeitpunkt neu.
 * @param array $p_template Vorlagen-Daten.
 * @return int ID der gespeicherten Vorlage.
 */
function issue_recurrence_template_save( array $p_template ) {
	$t_table = plugin_table( 'template' );
	$t_now = time();

	# Naechsten Lauf berechnen: liegt der Start in der Zukunft, ist der erste
	# Termin der Start selbst; sonst der naechste Termin nach "jetzt".
	$t_after = ( (int)$p_template['start_date'] > $t_now ) ? ( (int)$p_template['start_date'] - 1 ) : $t_now;
	$t_next = issue_recurrence_calculate_next( $p_template, $t_after );

	$t_fields = array(
		'name'            => (string)$p_template['name'],
		'enabled'         => (int)$p_template['enabled'],
		'project_id'      => (int)$p_template['project_id'],
		'reporter_id'     => (int)$p_template['reporter_id'],
		'handler_id'      => (int)$p_template['handler_id'],
		'category_id'     => (int)$p_template['category_id'],
		'summary'         => (string)$p_template['summary'],
		'description'     => (string)$p_template['description'],
		'priority'        => (int)$p_template['priority'],
		'severity'        => (int)$p_template['severity'],
		'reproducibility' => (int)$p_template['reproducibility'],
		'view_state'      => (int)$p_template['view_state'],
		'due_date_offset' => (int)$p_template['due_date_offset'],
		'freq_type'       => (string)$p_template['freq_type'],
		'freq_interval'   => max( 1, (int)$p_template['freq_interval'] ),
		'weekdays'        => (string)$p_template['weekdays'],
		'day_of_month'    => (int)$p_template['day_of_month'],
		'month_of_year'   => (int)$p_template['month_of_year'],
		'start_date'      => (int)$p_template['start_date'],
		'end_date'        => $p_template['end_date'] ? (int)$p_template['end_date'] : null,
		'next_run'        => $t_next,
	);

	if( (int)$p_template['id'] > 0 ) {
		# Update
		$t_set = array();
		$t_params = array();
		foreach( $t_fields as $t_col => $t_val ) {
			$t_set[] = $t_col . ' = ' . db_param();
			$t_params[] = $t_val;
		}
		$t_set[] = 'updated_at = ' . db_param();
		$t_params[] = $t_now;
		$t_params[] = (int)$p_template['id'];
		$t_query = 'UPDATE ' . $t_table . ' SET ' . implode( ', ', $t_set ) . ' WHERE id = ' . db_param();
		db_query( $t_query, $t_params );
		return (int)$p_template['id'];
	}

	# Insert
	$t_fields['created_at'] = $t_now;
	$t_fields['updated_at'] = $t_now;
	$t_cols = array_keys( $t_fields );
	$t_placeholders = array();
	$t_params = array();
	foreach( $t_fields as $t_val ) {
		$t_placeholders[] = db_param();
		$t_params[] = $t_val;
	}
	$t_query = 'INSERT INTO ' . $t_table . ' (' . implode( ', ', $t_cols ) . ') VALUES (' . implode( ', ', $t_placeholders ) . ')';
	db_query( $t_query, $t_params );
	return db_insert_id( $t_table );
}

/**
 * Loescht eine Vorlage (inkl. Historie).
 * @param int $p_id Vorlagen-ID.
 * @return void
 */
function issue_recurrence_template_delete( $p_id ) {
	$t_id = (int)$p_id;
	db_query( 'DELETE FROM ' . plugin_table( 'template' ) . ' WHERE id = ' . db_param(), array( $t_id ) );
	db_query( 'DELETE FROM ' . plugin_table( 'history' ) . ' WHERE template_id = ' . db_param(), array( $t_id ) );
}

/**
 * Aktiviert / deaktiviert eine Vorlage.
 * @param int  $p_id      Vorlagen-ID.
 * @param bool $p_enabled Neuer Status.
 * @return void
 */
function issue_recurrence_template_set_enabled( $p_id, $p_enabled ) {
	db_query(
		'UPDATE ' . plugin_table( 'template' ) . ' SET enabled = ' . db_param() . ', updated_at = ' . db_param() . ' WHERE id = ' . db_param(),
		array( $p_enabled ? 1 : 0, time(), (int)$p_id )
	);
}

/**
 * Erzeugt aus einer Vorlage ein konkretes Ticket.
 * @param array $p_template Vorlagen-Datensatz.
 * @return int Neue Bug-ID.
 */
function issue_recurrence_create_bug( array $p_template ) {
	$t_project_id = (int)$p_template['project_id'];

	# Kategorie validieren / Fallback bestimmen.
	$t_category_id = (int)$p_template['category_id'];
	if( $t_category_id <= 0 || !category_exists( $t_category_id ) ) {
		$t_category_id = issue_recurrence_default_category( $t_project_id );
	}

	$t_bug = new BugData;
	$t_bug->project_id      = $t_project_id;
	$t_bug->reporter_id     = (int)$p_template['reporter_id'] > 0 ? (int)$p_template['reporter_id'] : user_get_id_by_name( 'administrator' );
	$t_bug->handler_id      = (int)$p_template['handler_id'];
	$t_bug->category_id     = $t_category_id;
	$t_bug->summary         = issue_recurrence_expand_placeholders( $p_template['summary'] );
	$t_bug->description     = issue_recurrence_expand_placeholders( $p_template['description'] );
	$t_bug->priority        = (int)$p_template['priority'];
	$t_bug->severity        = (int)$p_template['severity'];
	$t_bug->reproducibility = (int)$p_template['reproducibility'];
	$t_bug->view_state      = (int)$p_template['view_state'];
	$t_bug->status          = config_get( 'bug_submit_status' );
	$t_bug->resolution      = config_get( 'default_bug_resolution' );

	# Faelligkeitsdatum optional setzen (Offset in Tagen ab Erstellung).
	$t_offset = (int)$p_template['due_date_offset'];
	if( $t_offset > 0 && config_get( 'due_date_update_threshold' ) !== NOBODY ) {
		$t_bug->due_date = strtotime( '+' . $t_offset . ' days', time() );
	}

	$t_bug_id = $t_bug->create();

	# Historie protokollieren.
	db_query(
		'INSERT INTO ' . plugin_table( 'history' ) . ' (template_id, bug_id, created_at) VALUES (' . db_param() . ', ' . db_param() . ', ' . db_param() . ')',
		array( (int)$p_template['id'], (int)$t_bug_id, time() )
	);

	return $t_bug_id;
}

/**
 * Ermittelt eine gueltige Standard-Kategorie fuer ein Projekt.
 * @param int $p_project_id Projekt-ID.
 * @return int Kategorie-ID.
 */
function issue_recurrence_default_category( $p_project_id ) {
	$t_categories = category_get_all_rows( $p_project_id );
	if( !empty( $t_categories ) ) {
		return (int)$t_categories[0]['id'];
	}
	# Globale Fallback-Kategorie (in MantisBT immer vorhanden, id 1 = "General" / sonst 0).
	$t_default = (int)config_get( 'default_category_for_moves' );
	return $t_default > 0 ? $t_default : 1;
}

/**
 * Ersetzt einfache Platzhalter in Texten (z.B. {date}, {datetime}, {time}).
 * @param string $p_text Eingabetext.
 * @return string
 */
function issue_recurrence_expand_placeholders( $p_text ) {
	$t_now = time();
	$t_replace = array(
		'{date}'     => date( config_get( 'short_date_format' ), $t_now ),
		'{datetime}' => date( config_get( 'normal_date_format' ), $t_now ),
		'{time}'     => date( 'H:i', $t_now ),
		'{year}'     => date( 'Y', $t_now ),
		'{month}'    => date( 'm', $t_now ),
		'{day}'      => date( 'd', $t_now ),
		'{week}'     => date( 'W', $t_now ),
	);
	return strtr( (string)$p_text, $t_replace );
}

/**
 * Verarbeitet alle faelligen Vorlagen: erzeugt Tickets und plant den naechsten Lauf.
 * Kann mehrere ausgelassene Termine nachholen (catch-up), z.B. wenn der Cron
 * laenger nicht lief.
 *
 * @param int|null $p_now Referenzzeitpunkt (Standard: jetzt), v.a. fuer Tests.
 * @return array Statistik: array( 'templates' => int, 'created' => int, 'bug_ids' => array ).
 */
function issue_recurrence_run_due( $p_now = null ) {
	$t_now = $p_now === null ? time() : (int)$p_now;
	$t_table = plugin_table( 'template' );

	$t_query = 'SELECT * FROM ' . $t_table
		. ' WHERE enabled = ' . db_param()
		. ' AND next_run IS NOT NULL AND next_run <= ' . db_param();
	$t_result = db_query( $t_query, array( 1, $t_now ) );

	$t_stats = array( 'templates' => 0, 'created' => 0, 'bug_ids' => array() );
	$t_max_catchup = 50; # Schutz gegen Endlosschleifen / Massenanlage.

	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_stats['templates']++;
		$t_next = (int)$t_row['next_run'];
		$t_last_created = null;
		$t_iterations = 0;

		# Alle faelligen Termine bis "jetzt" abarbeiten.
		while( $t_next !== null && $t_next <= $t_now && $t_iterations < $t_max_catchup ) {
			$t_iterations++;
			$t_bug_id = issue_recurrence_create_bug( $t_row );
			$t_stats['created']++;
			$t_stats['bug_ids'][] = $t_bug_id;
			$t_last_created = $t_next;

			# Naechsten Termin nach dem gerade verarbeiteten berechnen.
			$t_next = issue_recurrence_calculate_next( $t_row, $t_next );
		}

		# Vorlage mit neuem next_run / last_run aktualisieren.
		db_query(
			'UPDATE ' . $t_table . ' SET next_run = ' . db_param() . ', last_run = ' . db_param() . ' WHERE id = ' . db_param(),
			array( $t_next, $t_last_created, (int)$t_row['id'] )
		);
	}

	return $t_stats;
}

/**
 * Zaehlt, wie viele Tickets aus einer Vorlage bereits erzeugt wurden.
 * @param int $p_template_id Vorlagen-ID.
 * @return int
 */
function issue_recurrence_history_count( $p_template_id ) {
	$t_result = db_query(
		'SELECT COUNT(*) AS c FROM ' . plugin_table( 'history' ) . ' WHERE template_id = ' . db_param(),
		array( (int)$p_template_id )
	);
	$t_row = db_fetch_array( $t_result );
	return $t_row ? (int)$t_row['c'] : 0;
}
