<?php
/**
 * IssueRecurrence - Speichert eine Vorlage (Create/Update)
 *
 * @package IssueRecurrence
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License
 */

require_api( 'authentication_api.php' );
require_api( 'access_api.php' );
require_api( 'gpc_api.php' );
require_api( 'form_api.php' );

auth_ensure_user_authenticated();
access_ensure_project_level( plugin_config_get( 'manage_threshold' ) );

form_security_validate( 'plugin_IssueRecurrence_save' );

$f_id              = gpc_get_int( 'id', 0 );
$f_name            = trim( gpc_get_string( 'name', '' ) );
$f_enabled         = gpc_get_bool( 'enabled', false );
$f_project_id      = gpc_get_int( 'project_id' );
$f_category_id     = gpc_get_int( 'category_id', 0 );
$f_summary         = trim( gpc_get_string( 'summary', '' ) );
$f_description     = gpc_get_string( 'description', '' );
$f_handler_id      = gpc_get_int( 'handler_id', 0 );
$f_priority        = gpc_get_int( 'priority' );
$f_severity        = gpc_get_int( 'severity' );
$f_reproducibility = gpc_get_int( 'reproducibility' );
$f_view_state      = gpc_get_int( 'view_state', VS_PUBLIC );
$f_due_offset      = gpc_get_int( 'due_date_offset', 0 );
$f_freq_type       = gpc_get_string( 'freq_type', 'daily' );
$f_interval        = gpc_get_int( 'freq_interval', 1 );
$f_weekdays        = gpc_get_int_array( 'weekdays', array() );
$f_day_of_month    = gpc_get_int( 'day_of_month', 1 );
$f_month_of_year   = gpc_get_int( 'month_of_year', 1 );
$f_start_date_raw  = gpc_get_string( 'start_date', '' );
$f_end_date_raw    = gpc_get_string( 'end_date', '' );

# --- Validierung ---
if( $f_name === '' ) {
	error_parameters( plugin_lang_get( 'field_name' ) );
	trigger_error( ERROR_EMPTY_FIELD, ERROR );
}
if( $f_summary === '' ) {
	error_parameters( plugin_lang_get( 'field_summary' ) );
	trigger_error( ERROR_EMPTY_FIELD, ERROR );
}
if( !in_array( $f_freq_type, issue_recurrence_freq_types(), true ) ) {
	$f_freq_type = 'daily';
}

# Datum aus "datetime-local" (Y-m-dTH:i) in Zeitstempel umwandeln.
$t_start_date = strtotime( str_replace( 'T', ' ', $f_start_date_raw ) );
if( $t_start_date === false || $t_start_date <= 0 ) {
	$t_start_date = time();
}
$t_end_date = null;
if( trim( $f_end_date_raw ) !== '' ) {
	$t_end_parsed = strtotime( str_replace( 'T', ' ', $f_end_date_raw ) );
	if( $t_end_parsed !== false && $t_end_parsed > 0 ) {
		$t_end_date = $t_end_parsed;
	}
}

# Wochentage als sortierte, kommagetrennte Liste ablegen.
sort( $f_weekdays );
$t_weekdays = implode( ',', array_map( 'intval', $f_weekdays ) );

$t_template = array(
	'id'              => $f_id,
	'name'            => $f_name,
	'enabled'         => $f_enabled ? 1 : 0,
	'project_id'      => $f_project_id,
	'reporter_id'     => auth_get_current_user_id(),
	'handler_id'      => $f_handler_id,
	'category_id'     => $f_category_id,
	'summary'         => $f_summary,
	'description'     => $f_description,
	'priority'        => $f_priority,
	'severity'        => $f_severity,
	'reproducibility' => $f_reproducibility,
	'view_state'      => $f_view_state,
	'due_date_offset' => $f_due_offset,
	'freq_type'       => $f_freq_type,
	'freq_interval'   => max( 1, $f_interval ),
	'weekdays'        => $t_weekdays,
	'day_of_month'    => $f_day_of_month,
	'month_of_year'   => $f_month_of_year,
	'start_date'      => $t_start_date,
	'end_date'        => $t_end_date,
);

# Bestehenden Reporter beibehalten, falls bearbeitet.
if( $f_id > 0 ) {
	$t_existing = issue_recurrence_template_get( $f_id );
	if( $t_existing !== null ) {
		$t_template['reporter_id'] = (int)$t_existing['reporter_id'];
	}
}

$t_id = issue_recurrence_template_save( $t_template );

form_security_purge( 'plugin_IssueRecurrence_save' );

print_successful_redirect( plugin_page( 'manage', true ) );
