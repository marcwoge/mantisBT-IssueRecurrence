<?php
/**
 * IssueRecurrence - Speichert die Konfiguration
 *
 * @package IssueRecurrence
 * @license https://opensource.org/licenses/MIT MIT License
 */

require_api( 'authentication_api.php' );
require_api( 'access_api.php' );
require_api( 'config_api.php' );
require_api( 'gpc_api.php' );
require_api( 'form_api.php' );

auth_ensure_user_authenticated();
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

form_security_validate( 'plugin_IssueRecurrence_config_save' );

$f_manage_threshold      = gpc_get_int( 'manage_threshold', MANAGER );
$f_page_trigger_enabled  = gpc_get_bool( 'page_trigger_enabled', false ) ? ON : OFF;
$f_page_trigger_interval = max( 60, gpc_get_int( 'page_trigger_interval', 3600 ) );

plugin_config_set( 'manage_threshold', $f_manage_threshold );
plugin_config_set( 'page_trigger_enabled', $f_page_trigger_enabled );
plugin_config_set( 'page_trigger_interval', $f_page_trigger_interval );

form_security_purge( 'plugin_IssueRecurrence_config_save' );

print_successful_redirect( plugin_page( 'config', true ) );
