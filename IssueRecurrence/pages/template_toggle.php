<?php
/**
 * IssueRecurrence - Aktiviert/Pausiert eine Vorlage
 *
 * @package IssueRecurrence
 * @license https://opensource.org/licenses/MIT MIT License
 */

require_api( 'authentication_api.php' );
require_api( 'access_api.php' );
require_api( 'gpc_api.php' );
require_api( 'form_api.php' );

auth_ensure_user_authenticated();
access_ensure_project_level( plugin_config_get( 'manage_threshold' ) );

form_security_validate( 'plugin_IssueRecurrence_toggle' );

$f_id = gpc_get_int( 'id' );
$t_template = issue_recurrence_template_get( $f_id );
if( $t_template !== null ) {
	issue_recurrence_template_set_enabled( $f_id, (int)$t_template['enabled'] != 1 );
}

form_security_purge( 'plugin_IssueRecurrence_toggle' );

print_successful_redirect( plugin_page( 'manage', true ) );
