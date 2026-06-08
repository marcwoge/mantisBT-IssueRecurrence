<?php
/**
 * IssueRecurrence - Verwaltungsseite (Uebersicht aller Wiederholungen)
 *
 * @package IssueRecurrence
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License
 */

require_api( 'authentication_api.php' );
require_api( 'access_api.php' );
require_api( 'helper_api.php' );
require_api( 'print_api.php' );
require_api( 'html_api.php' );
require_api( 'user_api.php' );
require_api( 'project_api.php' );
require_api( 'category_api.php' );

auth_ensure_user_authenticated();
access_ensure_project_level( plugin_config_get( 'manage_threshold' ) );

$t_project_id = helper_get_current_project();
$t_templates = issue_recurrence_template_get_all( $t_project_id == ALL_PROJECTS ? null : $t_project_id );

layout_page_header( plugin_lang_get( 'menu_recurrence' ) );
layout_page_begin();

?>
<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-refresh"></i>
			<?php echo plugin_lang_get( 'manage_title' ) ?>
		</h4>
		<div class="widget-toolbar">
			<a class="btn btn-primary btn-sm btn-white btn-round"
			   href="<?php echo plugin_page( 'template_edit_page' ) ?>">
				<i class="ace-icon fa fa-plus"></i>
				<?php echo plugin_lang_get( 'new_template' ) ?>
			</a>
			<a class="btn btn-default btn-sm btn-white btn-round"
			   href="<?php echo plugin_page( 'run' ) ?>">
				<i class="ace-icon fa fa-play"></i>
				<?php echo plugin_lang_get( 'run_now' ) ?>
			</a>
		</div>
	</div>

	<div class="widget-body">
	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed table-hover">
		<thead>
			<tr>
				<th><?php echo plugin_lang_get( 'col_status' ) ?></th>
				<th><?php echo plugin_lang_get( 'col_name' ) ?></th>
				<th><?php echo plugin_lang_get( 'col_project' ) ?></th>
				<th><?php echo plugin_lang_get( 'col_summary' ) ?></th>
				<th><?php echo plugin_lang_get( 'col_rule' ) ?></th>
				<th><?php echo plugin_lang_get( 'col_next_run' ) ?></th>
				<th><?php echo plugin_lang_get( 'col_created_count' ) ?></th>
				<th><?php echo plugin_lang_get( 'col_actions' ) ?></th>
			</tr>
		</thead>
		<tbody>
<?php if( empty( $t_templates ) ) { ?>
			<tr><td colspan="8" class="center"><em><?php echo plugin_lang_get( 'no_templates' ) ?></em></td></tr>
<?php } else {
	foreach( $t_templates as $t_template ) {
		$t_id = (int)$t_template['id'];
		$t_enabled = (int)$t_template['enabled'] == 1;
		$t_next = $t_template['next_run'] ? date( config_get( 'normal_date_format' ), (int)$t_template['next_run'] ) : '-';
		$t_count = issue_recurrence_history_count( $t_id );
?>
			<tr>
				<td>
<?php if( $t_enabled ) { ?>
					<span class="label label-success"><?php echo plugin_lang_get( 'status_active' ) ?></span>
<?php } else { ?>
					<span class="label label-default"><?php echo plugin_lang_get( 'status_paused' ) ?></span>
<?php } ?>
				</td>
				<td><?php echo string_display_line( $t_template['name'] ) ?></td>
				<td><?php echo string_display_line( project_get_name( (int)$t_template['project_id'] ) ) ?></td>
				<td><?php echo string_display_line( $t_template['summary'] ) ?></td>
				<td><?php echo string_display_line( issue_recurrence_describe( $t_template ) ) ?></td>
				<td><?php echo $t_next ?></td>
				<td class="center"><?php echo $t_count ?></td>
				<td class="nowrap">
					<a class="btn btn-xs btn-primary btn-white btn-round"
					   href="<?php echo plugin_page( 'template_edit_page' ) ?>&amp;id=<?php echo $t_id ?>">
						<i class="ace-icon fa fa-edit"></i> <?php echo plugin_lang_get( 'action_edit' ) ?>
					</a>
					<a class="btn btn-xs btn-default btn-white btn-round"
					   href="<?php echo plugin_page( 'template_toggle' ) ?>&amp;id=<?php echo $t_id ?>&amp;<?php echo form_security_param( 'plugin_IssueRecurrence_toggle' ) ?>">
						<i class="ace-icon fa <?php echo $t_enabled ? 'fa-pause' : 'fa-play' ?>"></i>
						<?php echo $t_enabled ? plugin_lang_get( 'action_pause' ) : plugin_lang_get( 'action_resume' ) ?>
					</a>
					<a class="btn btn-xs btn-danger btn-white btn-round"
					   href="<?php echo plugin_page( 'template_delete' ) ?>&amp;id=<?php echo $t_id ?>&amp;<?php echo form_security_param( 'plugin_IssueRecurrence_delete' ) ?>"
					   onclick="return confirm('<?php echo plugin_lang_get( 'confirm_delete' ) ?>');">
						<i class="ace-icon fa fa-trash-o"></i> <?php echo plugin_lang_get( 'action_delete' ) ?>
					</a>
				</td>
			</tr>
<?php	}
} ?>
		</tbody>
	</table>
	</div>
	</div>
	</div>
</div>

<p class="lighter small">
	<i class="ace-icon fa fa-info-circle"></i>
	<?php echo plugin_lang_get( 'cron_hint' ) ?>
</p>

</div>
<?php

layout_page_end();
