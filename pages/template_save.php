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
require_api( 'custom_field_api.php' );

auth_ensure_user_authenticated();
access_ensure_project_level( plugin_config_get( 'manage_threshold' ) );

form_security_validate( 'plugin_IssueRecurrence_save' );

# Formulardaten in ein Vorlagen-Array uebernehmen.
$t_template = issue_recurrence_gpc_to_template();

# --- Validierung ---
if( $t_template['name'] === '' ) {
	error_parameters( plugin_lang_get( 'field_name' ) );
	trigger_error( ERROR_EMPTY_FIELD, ERROR );
}
if( $t_template['summary'] === '' ) {
	error_parameters( plugin_lang_get( 'field_summary' ) );
	trigger_error( ERROR_EMPTY_FIELD, ERROR );
}

# Bei bestehender Vorlage den urspruenglichen Ersteller beibehalten.
if( (int)$t_template['id'] > 0 ) {
	$t_existing = issue_recurrence_template_get( (int)$t_template['id'] );
	if( $t_existing !== null ) {
		$t_template['reporter_id'] = (int)$t_existing['reporter_id'];
	}
}

# Vorlage speichern (berechnet next_run automatisch).
$t_id = issue_recurrence_template_save( $t_template );

# Custom-Field-Werte des gewaehlten Projekts speichern.
$t_cf_values = issue_recurrence_cf_gpc_values( (int)$t_template['project_id'] );
issue_recurrence_cf_save_values( $t_id, $t_cf_values );

form_security_purge( 'plugin_IssueRecurrence_save' );

print_successful_redirect( plugin_page( 'manage', true ) );
