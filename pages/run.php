<?php
/**
 * IssueRecurrence - Manueller Lauf (erzeugt jetzt alle faelligen Tickets)
 *
 * @package IssueRecurrence
 * @license https://opensource.org/licenses/MIT MIT License
 */

require_api( 'authentication_api.php' );
require_api( 'access_api.php' );
require_api( 'html_api.php' );
require_api( 'print_api.php' );

auth_ensure_user_authenticated();
access_ensure_project_level( plugin_config_get( 'manage_threshold' ) );

$t_stats = issue_recurrence_run_due();

layout_page_header( plugin_lang_get( 'run_now' ) );
layout_page_begin();

?>
<div class="col-md-12 col-xs-12">
<div class="space-10"></div>
<div class="widget-box widget-color-green2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter"><i class="ace-icon fa fa-check"></i> <?php echo plugin_lang_get( 'run_done' ) ?></h4>
	</div>
	<div class="widget-body">
	<div class="widget-main">
		<p><?php echo sprintf( plugin_lang_get( 'run_result' ), (int)$t_stats['created'], (int)$t_stats['templates'] ) ?></p>
<?php if( !empty( $t_stats['bug_ids'] ) ) { ?>
		<p>
			<?php echo plugin_lang_get( 'run_created_issues' ) ?>:
<?php	foreach( $t_stats['bug_ids'] as $t_bug_id ) {
			echo ' ' . string_get_bug_view_link( $t_bug_id );
		} ?>
		</p>
<?php } ?>
		<a class="btn btn-primary btn-white btn-round" href="<?php echo plugin_page( 'manage' ) ?>"><?php echo plugin_lang_get( 'btn_back' ) ?></a>
	</div>
	</div>
</div>
</div>
<?php

layout_page_end();
