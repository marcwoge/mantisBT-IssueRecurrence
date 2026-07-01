<?php
/**
 * IssueRecurrence - Loescht eine Vorlage
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

form_security_validate( 'plugin_IssueRecurrence_delete' );

$f_id = gpc_get_int( 'id' );
issue_recurrence_template_delete( $f_id );

form_security_purge( 'plugin_IssueRecurrence_delete' );

print_successful_redirect( plugin_page( 'manage', true ) );
