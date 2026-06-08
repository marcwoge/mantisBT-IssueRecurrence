#!/usr/bin/env php
<?php
/**
 * IssueRecurrence - Cron-/CLI-Einstiegspunkt
 *
 * Erzeugt alle faelligen wiederkehrenden Tickets. Per Cronjob aufrufen, z.B.:
 *
 *   # jede Stunde (Linux crontab)
 *   0 * * * * php /pfad/zu/mantisbt/plugins/IssueRecurrence/cli/run_recurrence.php >> /var/log/mantis_recurrence.log 2>&1
 *
 *   # taeglich um 06:00 (Windows Aufgabenplanung)
 *   php D:\mantisbt\plugins\IssueRecurrence\cli\run_recurrence.php
 *
 * @package IssueRecurrence
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License
 */

# Nur ueber die Kommandozeile ausfuehrbar.
if( php_sapi_name() !== 'cli' ) {
	http_response_code( 403 );
	die( 'This script can only be run from the command line.' );
}

set_time_limit( 0 );
$g_bypass_headers = 1;

# MantisBT-Core laden. Das Skript liegt unter plugins/IssueRecurrence/cli/,
# daher liegt core.php drei Ebenen darueber.
$t_core_path = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'core.php';
if( !file_exists( $t_core_path ) ) {
	fwrite( STDERR, "MantisBT core.php nicht gefunden unter: $t_core_path\n" );
	fwrite( STDERR, "Bitte das Plugin nach plugins/IssueRecurrence/ installieren.\n" );
	exit( 2 );
}
require_once( $t_core_path );

require_api( 'authentication_api.php' );
require_api( 'plugin_api.php' );
require_api( 'config_api.php' );

# Plugin-Kontext setzen, damit plugin_table()/plugin_config_get() korrekt aufloesen.
plugin_push_current( 'IssueRecurrence' );

$t_api = config_get_global( 'plugin_path' ) . 'IssueRecurrence' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'recurrence_api.php';
require_once( $t_api );

# Als konfigurierter Benutzer "anmelden" (fuer History-Eintraege und Berechtigungen).
$t_script_user = plugin_config_get( 'script_login_user', 'administrator' );
if( !auth_attempt_script_login( $t_script_user ) ) {
	fwrite( STDERR, "Script-Login fuer Benutzer '$t_script_user' fehlgeschlagen.\n" );
	fwrite( STDERR, "Konfiguration: Plugin IssueRecurrence -> script_login_user.\n" );
	exit( 3 );
}

# Faellige Vorlagen abarbeiten.
$t_stats = issue_recurrence_run_due();

$t_msg = sprintf(
	'[%s] IssueRecurrence: %d faellige Vorlage(n), %d Ticket(s) erstellt.',
	date( 'Y-m-d H:i:s' ),
	(int)$t_stats['templates'],
	(int)$t_stats['created']
);
if( !empty( $t_stats['bug_ids'] ) ) {
	$t_msg .= ' IDs: ' . implode( ', ', $t_stats['bug_ids'] );
}
echo $t_msg . "\n";

plugin_pop_current();
exit( 0 );
