<?php
/**
 * IssueRecurrence - Formular zum Anlegen/Bearbeiten einer Vorlage
 *
 * Dient sowohl dem Erstellen (ohne id) als auch dem Bearbeiten (mit id).
 *
 * @package IssueRecurrence
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License
 */

require_api( 'authentication_api.php' );
require_api( 'access_api.php' );
require_api( 'helper_api.php' );
require_api( 'gpc_api.php' );
require_api( 'print_api.php' );
require_api( 'html_api.php' );
require_api( 'category_api.php' );

auth_ensure_user_authenticated();
access_ensure_project_level( plugin_config_get( 'manage_threshold' ) );

$f_id = gpc_get_int( 'id', 0 );

if( $f_id > 0 ) {
	$t_template = issue_recurrence_template_get( $f_id );
	if( $t_template === null ) {
		trigger_error( ERROR_GENERIC, ERROR );
	}
	$t_is_new = false;
} else {
	$t_template = issue_recurrence_template_blank();
	$t_is_new = true;
}

$t_project_id = (int)$t_template['project_id'] > 0 ? (int)$t_template['project_id'] : helper_get_current_project();
if( $t_project_id == ALL_PROJECTS ) {
	# Vorlagen brauchen ein konkretes Projekt; erstes verfuegbares waehlen.
	$t_projects = current_user_get_accessible_projects();
	$t_project_id = !empty( $t_projects ) ? (int)$t_projects[0] : 1;
}

$t_weekdays_selected = issue_recurrence_parse_weekdays( $t_template['weekdays'] );
$t_weekday_names = issue_recurrence_weekday_names();
$t_month_names = issue_recurrence_month_names();

layout_page_header( plugin_lang_get( $t_is_new ? 'new_template' : 'edit_template' ) );
layout_page_begin();

/**
 * Hilfsfunktion: option-Liste fuer Enum-Konfigurationen ausgeben.
 * @param string $p_enum_name Name der Enum-Konfiguration (priority, severity, ...).
 * @param int    $p_selected  Aktuell gewaehlter Wert.
 * @return void
 */
if( !function_exists( 'issue_recurrence_print_enum' ) ) {
	function issue_recurrence_print_enum( $p_enum_name, $p_selected ) {
		$t_enum = config_get( $p_enum_name . '_enum_string' );
		$t_array = MantisEnum::getAssocArrayIndexedByValues( $t_enum );
		foreach( $t_array as $t_key => $t_label ) {
			echo '<option value="' . $t_key . '"' . ( (int)$p_selected === (int)$t_key ? ' selected="selected"' : '' ) . '>'
				. string_attribute( get_enum_element( $p_enum_name, $t_key ) ) . '</option>';
		}
	}
}

?>
<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<form id="issue-recurrence-form" method="post" action="<?php echo plugin_page( 'template_save' ) ?>">
<?php echo form_security_field( 'plugin_IssueRecurrence_save' ) ?>
<input type="hidden" name="id" value="<?php echo (int)$t_template['id'] ?>"/>

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-refresh"></i>
			<?php echo plugin_lang_get( $t_is_new ? 'new_template' : 'edit_template' ) ?>
		</h4>
	</div>
	<div class="widget-body">
	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed table-striped">

		<!-- Allgemein -->
		<tr>
			<td class="category" width="25%"><?php echo plugin_lang_get( 'field_name' ) ?> <span class="required">*</span></td>
			<td>
				<input type="text" name="name" class="input-sm" size="60" maxlength="128" required
				       value="<?php echo string_attribute( $t_template['name'] ) ?>"/>
				<p class="help-block small"><?php echo plugin_lang_get( 'field_name_hint' ) ?></p>
			</td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'field_enabled' ) ?></td>
			<td>
				<label>
					<input type="checkbox" name="enabled" class="ace" value="1" <?php check_checked( (int)$t_template['enabled'], 1 ) ?>/>
					<span class="lbl"> <?php echo plugin_lang_get( 'field_enabled_label' ) ?></span>
				</label>
			</td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'field_project' ) ?></td>
			<td>
				<select name="project_id" class="input-sm">
					<?php print_project_option_list( $t_project_id, false ) ?>
				</select>
			</td>
		</tr>

		<!-- Ticket-Inhalt (Template) -->
		<tr><td class="category" colspan="2"><strong><?php echo plugin_lang_get( 'section_ticket' ) ?></strong></td></tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'field_category' ) ?></td>
			<td>
				<select name="category_id" class="input-sm">
					<option value="0"><?php echo plugin_lang_get( 'auto_category' ) ?></option>
					<?php print_category_option_list( (int)$t_template['category_id'], $t_project_id ) ?>
				</select>
			</td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'field_summary' ) ?> <span class="required">*</span></td>
			<td><input type="text" name="summary" class="input-sm" size="80" maxlength="128" required
			           value="<?php echo string_attribute( $t_template['summary'] ) ?>"/></td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'field_description' ) ?></td>
			<td>
				<textarea name="description" class="form-control" cols="80" rows="8"><?php echo string_textarea( $t_template['description'] ) ?></textarea>
				<p class="help-block small"><?php echo plugin_lang_get( 'placeholder_hint' ) ?></p>
			</td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'field_handler' ) ?></td>
			<td>
				<select name="handler_id" class="input-sm">
					<option value="0"><?php echo plugin_lang_get( 'unassigned' ) ?></option>
					<?php print_assign_to_option_list( (int)$t_template['handler_id'], $t_project_id ) ?>
				</select>
			</td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'field_priority' ) ?></td>
			<td>
				<select name="priority" class="input-sm"><?php issue_recurrence_print_enum( 'priority', $t_template['priority'] ) ?></select>
			</td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'field_severity' ) ?></td>
			<td>
				<select name="severity" class="input-sm"><?php issue_recurrence_print_enum( 'severity', $t_template['severity'] ) ?></select>
			</td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'field_reproducibility' ) ?></td>
			<td>
				<select name="reproducibility" class="input-sm"><?php issue_recurrence_print_enum( 'reproducibility', $t_template['reproducibility'] ) ?></select>
			</td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'field_view_state' ) ?></td>
			<td>
				<select name="view_state" class="input-sm"><?php issue_recurrence_print_enum( 'view_state', $t_template['view_state'] ) ?></select>
			</td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'field_due_offset' ) ?></td>
			<td>
				<input type="number" name="due_date_offset" class="input-sm" min="0" max="3650" style="width:90px"
				       value="<?php echo (int)$t_template['due_date_offset'] ?>"/>
				<span class="small"><?php echo plugin_lang_get( 'field_due_offset_hint' ) ?></span>
			</td>
		</tr>

		<!-- Wiederholungsregel -->
		<tr><td class="category" colspan="2"><strong><?php echo plugin_lang_get( 'section_recurrence' ) ?></strong></td></tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'field_freq_type' ) ?></td>
			<td>
				<select name="freq_type" id="ir-freq-type" class="input-sm">
<?php foreach( issue_recurrence_freq_types() as $t_ft ) { ?>
					<option value="<?php echo $t_ft ?>" <?php check_selected( $t_template['freq_type'], $t_ft ) ?>><?php echo plugin_lang_get( 'freq_' . $t_ft ) ?></option>
<?php } ?>
				</select>
			</td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'field_interval' ) ?></td>
			<td>
				<?php echo plugin_lang_get( 'every' ) ?>
				<input type="number" name="freq_interval" class="input-sm" min="1" max="999" style="width:70px"
				       value="<?php echo max( 1, (int)$t_template['freq_interval'] ) ?>"/>
				<span id="ir-interval-unit"></span>
			</td>
		</tr>

		<!-- Woechentlich: Wochentage -->
		<tr id="ir-row-weekdays">
			<td class="category"><?php echo plugin_lang_get( 'field_weekdays' ) ?></td>
			<td>
<?php for( $d = 0; $d <= 6; $d++ ) { ?>
				<label class="inline" style="margin-right:12px">
					<input type="checkbox" name="weekdays[]" class="ace" value="<?php echo $d ?>" <?php echo in_array( $d, $t_weekdays_selected ) ? 'checked="checked"' : '' ?>/>
					<span class="lbl"> <?php echo $t_weekday_names[$d] ?></span>
				</label>
<?php } ?>
			</td>
		</tr>

		<!-- Monatlich/Jaehrlich: Tag im Monat -->
		<tr id="ir-row-dom">
			<td class="category"><?php echo plugin_lang_get( 'field_day_of_month' ) ?></td>
			<td>
				<select name="day_of_month" class="input-sm">
<?php for( $d = 1; $d <= 31; $d++ ) { ?>
					<option value="<?php echo $d ?>" <?php check_selected( (int)$t_template['day_of_month'], $d ) ?>><?php echo $d ?></option>
<?php } ?>
					<option value="0" <?php check_selected( (int)$t_template['day_of_month'], 0 ) ?>><?php echo plugin_lang_get( 'last_day' ) ?></option>
				</select>
			</td>
		</tr>

		<!-- Jaehrlich: Monat -->
		<tr id="ir-row-month">
			<td class="category"><?php echo plugin_lang_get( 'field_month' ) ?></td>
			<td>
				<select name="month_of_year" class="input-sm">
<?php for( $m = 1; $m <= 12; $m++ ) { ?>
					<option value="<?php echo $m ?>" <?php check_selected( (int)$t_template['month_of_year'], $m ) ?>><?php echo $t_month_names[$m] ?></option>
<?php } ?>
				</select>
			</td>
		</tr>

		<tr>
			<td class="category"><?php echo plugin_lang_get( 'field_start_date' ) ?> <span class="required">*</span></td>
			<td>
				<input type="datetime-local" name="start_date" class="input-sm" required
				       value="<?php echo date( 'Y-m-d\TH:i', (int)$t_template['start_date'] ) ?>"/>
				<p class="help-block small"><?php echo plugin_lang_get( 'field_start_date_hint' ) ?></p>
			</td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'field_end_date' ) ?></td>
			<td>
				<input type="datetime-local" name="end_date" class="input-sm"
				       value="<?php echo $t_template['end_date'] ? date( 'Y-m-d\TH:i', (int)$t_template['end_date'] ) : '' ?>"/>
				<span class="small"><?php echo plugin_lang_get( 'field_end_date_hint' ) ?></span>
			</td>
		</tr>

	</table>
	</div>
	</div>
	<div class="widget-toolbox padding-8 clearfix">
		<input type="submit" class="btn btn-primary btn-white btn-round" value="<?php echo plugin_lang_get( 'btn_save' ) ?>"/>
		<a class="btn btn-default btn-white btn-round" href="<?php echo plugin_page( 'manage' ) ?>"><?php echo plugin_lang_get( 'btn_cancel' ) ?></a>
	</div>
	</div>
</div>
</form>
</div>

<script type="text/javascript">
(function() {
	function updateRecurrenceFields() {
		var freq = document.getElementById( 'ir-freq-type' ).value;
		var rowWeekdays = document.getElementById( 'ir-row-weekdays' );
		var rowDom = document.getElementById( 'ir-row-dom' );
		var rowMonth = document.getElementById( 'ir-row-month' );
		var unit = document.getElementById( 'ir-interval-unit' );

		rowWeekdays.style.display = ( freq === 'weekly' ) ? '' : 'none';
		rowDom.style.display      = ( freq === 'monthly' || freq === 'yearly' ) ? '' : 'none';
		rowMonth.style.display    = ( freq === 'yearly' ) ? '' : 'none';

		var units = {
			daily:   '<?php echo plugin_lang_get( 'unit_days' ) ?>',
			weekly:  '<?php echo plugin_lang_get( 'unit_weeks' ) ?>',
			monthly: '<?php echo plugin_lang_get( 'unit_months' ) ?>',
			yearly:  '<?php echo plugin_lang_get( 'unit_years' ) ?>'
		};
		unit.textContent = units[freq] || '';
	}
	document.getElementById( 'ir-freq-type' ).addEventListener( 'change', updateRecurrenceFields );
	updateRecurrenceFields();
})();
</script>
<?php

layout_page_end();
