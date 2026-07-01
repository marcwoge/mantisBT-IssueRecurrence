<?php
/**
 * IssueRecurrence - Konfigurationsseite
 *
 * @package IssueRecurrence
 * @license https://opensource.org/licenses/MIT MIT License
 */

require_api( 'authentication_api.php' );
require_api( 'access_api.php' );
require_api( 'config_api.php' );
require_api( 'html_api.php' );
require_api( 'print_api.php' );

auth_ensure_user_authenticated();
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

$t_manage_threshold     = plugin_config_get( 'manage_threshold' );
$t_page_trigger_enabled = plugin_config_get( 'page_trigger_enabled' );
$t_page_trigger_interval= plugin_config_get( 'page_trigger_interval' );

layout_page_header( plugin_lang_get( 'config_title' ) );
layout_page_begin();

?>
<div class="col-md-12 col-xs-12">
<div class="space-10"></div>
<form method="post" action="<?php echo plugin_page( 'config_save' ) ?>">
<?php echo form_security_field( 'plugin_IssueRecurrence_config_save' ) ?>

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter"><i class="ace-icon fa fa-cogs"></i> <?php echo plugin_lang_get( 'config_title' ) ?></h4>
	</div>
	<div class="widget-body">
	<div class="widget-main no-padding">
	<table class="table table-bordered table-condensed table-striped">
		<tr>
			<td class="category" width="35%"><?php echo plugin_lang_get( 'config_manage_threshold' ) ?></td>
			<td>
				<select name="manage_threshold" class="input-sm">
					<?php print_enum_string_option_list( 'access_levels', $t_manage_threshold ) ?>
				</select>
			</td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'config_page_trigger' ) ?></td>
			<td>
				<label>
					<input type="checkbox" name="page_trigger_enabled" class="ace" value="1" <?php check_checked( (int)$t_page_trigger_enabled, ON ) ?>/>
					<span class="lbl"> <?php echo plugin_lang_get( 'config_page_trigger_label' ) ?></span>
				</label>
				<p class="help-block small"><?php echo plugin_lang_get( 'config_page_trigger_hint' ) ?></p>
			</td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'config_page_interval' ) ?></td>
			<td>
				<input type="number" name="page_trigger_interval" class="input-sm" min="60" max="86400" style="width:120px"
				       value="<?php echo (int)$t_page_trigger_interval ?>"/>
				<span class="small"><?php echo plugin_lang_get( 'config_page_interval_hint' ) ?></span>
			</td>
		</tr>
	</table>
	</div>
	<div class="widget-toolbox padding-8 clearfix">
		<input type="submit" class="btn btn-primary btn-white btn-round" value="<?php echo plugin_lang_get( 'btn_save' ) ?>"/>
	</div>
	</div>
</div>
</form>
</div>
<?php

layout_page_end();
