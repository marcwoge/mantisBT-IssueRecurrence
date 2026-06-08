<?php
/**
 * IssueRecurrence - Zeitplan-Logik
 *
 * Berechnet den naechsten Faelligkeitszeitpunkt einer Wiederholungsregel,
 * analog zu Serienterminen (taeglich / woechentlich / monatlich / jaehrlich).
 *
 * @package IssueRecurrence
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License
 */

if( !defined( 'MANTIS_DIR' ) && !function_exists( 'plugin_lang_get' ) ) {
	# Direkter Aufruf nicht erlaubt.
	exit( 1 );
}

/**
 * Gibt die unterstuetzten Frequenztypen zurueck.
 * @return array
 */
function issue_recurrence_freq_types() {
	return array( 'daily', 'weekly', 'monthly', 'yearly' );
}

/**
 * Wandelt eine Wochentagsliste ("1,3,5") in ein Array von Integern (0=So..6=Sa).
 * @param string $p_weekdays Kommagetrennte Wochentage.
 * @return array
 */
function issue_recurrence_parse_weekdays( $p_weekdays ) {
	$t_result = array();
	foreach( explode( ',', (string)$p_weekdays ) as $t_day ) {
		$t_day = trim( $t_day );
		if( $t_day === '' ) {
			continue;
		}
		$t_int = (int)$t_day;
		if( $t_int >= 0 && $t_int <= 6 ) {
			$t_result[$t_int] = $t_int;
		}
	}
	ksort( $t_result );
	return array_values( $t_result );
}

/**
 * Berechnet den naechsten Faelligkeitszeitpunkt einer Vorlage.
 *
 * @param array   $p_template Vorlagen-Datensatz (assoziatives Array).
 * @param int     $p_after    Zeitstempel; gesucht wird der naechste Termin > diesem Wert.
 *                            Bei null wird der Start als Basis genommen.
 * @return int|null Unix-Zeitstempel des naechsten Termins oder null, wenn keiner mehr existiert.
 */
function issue_recurrence_calculate_next( array $p_template, $p_after = null ) {
	$t_freq     = $p_template['freq_type'];
	$t_interval = max( 1, (int)$p_template['freq_interval'] );
	$t_start    = (int)$p_template['start_date'];
	$t_end      = isset( $p_template['end_date'] ) && $p_template['end_date'] ? (int)$p_template['end_date'] : null;

	if( $p_after === null ) {
		$t_after = $t_start - 1;
	} else {
		$t_after = max( (int)$p_after, $t_start - 1 );
	}

	# Uhrzeit (Stunde/Minute) aus dem Startdatum uebernehmen.
	$t_hour = (int)date( 'G', $t_start );
	$t_min  = (int)date( 'i', $t_start );

	switch( $t_freq ) {
		case 'daily':
			$t_next = issue_recurrence_next_daily( $t_start, $t_after, $t_interval );
			break;
		case 'weekly':
			$t_next = issue_recurrence_next_weekly( $t_start, $t_after, $t_interval, $p_template['weekdays'], $t_hour, $t_min );
			break;
		case 'monthly':
			$t_next = issue_recurrence_next_monthly( $t_start, $t_after, $t_interval, (int)$p_template['day_of_month'], $t_hour, $t_min );
			break;
		case 'yearly':
			$t_next = issue_recurrence_next_yearly( $t_start, $t_after, $t_interval, (int)$p_template['month_of_year'], (int)$p_template['day_of_month'], $t_hour, $t_min );
			break;
		default:
			return null;
	}

	if( $t_next === null ) {
		return null;
	}
	if( $t_end !== null && $t_next > $t_end ) {
		return null;
	}
	return $t_next;
}

/**
 * Naechster taeglicher Termin.
 * @param int $p_start    Startzeitstempel.
 * @param int $p_after    Suche Termin > diesem Wert.
 * @param int $p_interval Alle N Tage.
 * @return int
 */
function issue_recurrence_next_daily( $p_start, $p_after, $p_interval ) {
	if( $p_after < $p_start ) {
		return $p_start;
	}
	$t_day_secs = 86400;
	# Anzahl ganzer Tage zwischen Start und "after".
	$t_days = (int)floor( ( $p_after - $p_start ) / $t_day_secs );
	# Auf das naechste Vielfache von interval aufrunden, das > after liegt.
	$t_steps = (int)floor( $t_days / $p_interval ) + 1;
	$t_candidate = strtotime( '+' . ( $t_steps * $p_interval ) . ' days', $p_start );
	# Sicherheits-Schleife (Sommer-/Winterzeit-Korrektur).
	while( $t_candidate <= $p_after ) {
		$t_candidate = strtotime( '+' . $p_interval . ' days', $t_candidate );
	}
	return $t_candidate;
}

/**
 * Naechster woechentlicher Termin an ausgewaehlten Wochentagen.
 * @param int    $p_start    Startzeitstempel.
 * @param int    $p_after    Suche Termin > diesem Wert.
 * @param int    $p_interval Alle N Wochen.
 * @param string $p_weekdays Kommagetrennte Wochentage (0=So..6=Sa).
 * @param int    $p_hour     Stunde.
 * @param int    $p_min      Minute.
 * @return int|null
 */
function issue_recurrence_next_weekly( $p_start, $p_after, $p_interval, $p_weekdays, $p_hour, $p_min ) {
	$t_days = issue_recurrence_parse_weekdays( $p_weekdays );
	if( empty( $t_days ) ) {
		# Kein Wochentag gewaehlt -> Wochentag des Startdatums nutzen.
		$t_days = array( (int)date( 'w', $p_start ) );
	}

	# Beginn der Startwoche: Sonntag der Woche, in der das Startdatum liegt.
	# Ueber mktime mit (Tag - Wochentag) zuverlaessig und zeitzonensicher bestimmt.
	$t_start_y   = (int)date( 'Y', $p_start );
	$t_start_m   = (int)date( 'n', $p_start );
	$t_start_d   = (int)date( 'j', $p_start );
	$t_start_dow = (int)date( 'w', $p_start ); # 0=So..6=Sa

	# Iteriere Woche fuer Woche (begrenzt), bis ein gueltiger Termin > after gefunden ist.
	$t_max_weeks = 520; # Sicherheitsgrenze ~10 Jahre.
	for( $w = 0; $w < $t_max_weeks; $w++ ) {
		# Nur Wochen im Interval-Raster beruecksichtigen.
		if( $w % $p_interval !== 0 ) {
			continue;
		}
		foreach( $t_days as $t_dow ) {
			# Tag-Offset ab dem Sonntag der Startwoche.
			$t_day_offset = ( $w * 7 ) + ( $t_dow - $t_start_dow );
			$t_candidate = mktime( $p_hour, $p_min, 0, $t_start_m, $t_start_d + $t_day_offset, $t_start_y );
			if( $t_candidate >= $p_start && $t_candidate > $p_after ) {
				return $t_candidate;
			}
		}
	}
	return null;
}

/**
 * Naechster monatlicher Termin.
 * @param int $p_start    Startzeitstempel.
 * @param int $p_after    Suche Termin > diesem Wert.
 * @param int $p_interval Alle N Monate.
 * @param int $p_dom      Tag im Monat (1-31, oder 0 = letzter Tag des Monats).
 * @param int $p_hour     Stunde.
 * @param int $p_min      Minute.
 * @return int|null
 */
function issue_recurrence_next_monthly( $p_start, $p_after, $p_interval, $p_dom, $p_hour, $p_min ) {
	$t_year  = (int)date( 'Y', $p_start );
	$t_month = (int)date( 'n', $p_start );

	$t_max = 1200; # Sicherheitsgrenze.
	for( $i = 0; $i < $t_max; $i++ ) {
		# Nur Monate im Interval-Raster.
		$t_total = ( $t_year * 12 + ( $t_month - 1 ) ) + $i;
		$t_start_total = ( (int)date( 'Y', $p_start ) * 12 + ( (int)date( 'n', $p_start ) - 1 ) );
		if( ( $t_total - $t_start_total ) % $p_interval !== 0 ) {
			continue;
		}
		$t_y = (int)floor( $t_total / 12 );
		$t_m = ( $t_total % 12 ) + 1;
		$t_day = issue_recurrence_resolve_day( $t_y, $t_m, $p_dom );
		$t_candidate = mktime( $p_hour, $p_min, 0, $t_m, $t_day, $t_y );
		if( $t_candidate >= $p_start && $t_candidate > $p_after ) {
			return $t_candidate;
		}
	}
	return null;
}

/**
 * Naechster jaehrlicher Termin.
 * @param int $p_start    Startzeitstempel.
 * @param int $p_after    Suche Termin > diesem Wert.
 * @param int $p_interval Alle N Jahre.
 * @param int $p_month    Monat (1-12).
 * @param int $p_dom      Tag im Monat (1-31, oder 0 = letzter Tag).
 * @param int $p_hour     Stunde.
 * @param int $p_min      Minute.
 * @return int|null
 */
function issue_recurrence_next_yearly( $p_start, $p_after, $p_interval, $p_month, $p_dom, $p_hour, $p_min ) {
	$t_start_year = (int)date( 'Y', $p_start );
	$t_month = ( $p_month >= 1 && $p_month <= 12 ) ? $p_month : (int)date( 'n', $p_start );

	$t_max = 200;
	for( $i = 0; $i < $t_max; $i++ ) {
		if( $i % $p_interval !== 0 ) {
			continue;
		}
		$t_y = $t_start_year + $i;
		$t_day = issue_recurrence_resolve_day( $t_y, $t_month, $p_dom );
		$t_candidate = mktime( $p_hour, $p_min, 0, $t_month, $t_day, $t_y );
		if( $t_candidate >= $p_start && $t_candidate > $p_after ) {
			return $t_candidate;
		}
	}
	return null;
}

/**
 * Loest den konkreten Tag im Monat auf und beachtet Monatslaengen.
 * @param int $p_year  Jahr.
 * @param int $p_month Monat (1-12).
 * @param int $p_dom   Gewuenschter Tag (0 = letzter Tag, sonst 1-31).
 * @return int Tatsaechlicher Tag (auf gueltigen Bereich begrenzt).
 */
function issue_recurrence_resolve_day( $p_year, $p_month, $p_dom ) {
	$t_days_in_month = (int)date( 't', mktime( 0, 0, 0, $p_month, 1, $p_year ) );
	if( (int)$p_dom <= 0 ) {
		return $t_days_in_month; # letzter Tag des Monats.
	}
	return min( (int)$p_dom, $t_days_in_month );
}

/**
 * Liefert einen lesbaren Text fuer die Wiederholungsregel (z.B. fuer die Verwaltung).
 * @param array $p_template Vorlagen-Datensatz.
 * @return string
 */
function issue_recurrence_describe( array $p_template ) {
	$t_interval = max( 1, (int)$p_template['freq_interval'] );

	switch( $p_template['freq_type'] ) {
		case 'daily':
			return $t_interval == 1
				? plugin_lang_get( 'desc_daily' )
				: sprintf( plugin_lang_get( 'desc_daily_n' ), $t_interval );
		case 'weekly':
			$t_days = issue_recurrence_parse_weekdays( $p_template['weekdays'] );
			$t_names = array();
			$t_weekday_names = issue_recurrence_weekday_names();
			foreach( $t_days as $t_d ) {
				$t_names[] = $t_weekday_names[$t_d];
			}
			$t_list = implode( ', ', $t_names );
			return $t_interval == 1
				? sprintf( plugin_lang_get( 'desc_weekly' ), $t_list )
				: sprintf( plugin_lang_get( 'desc_weekly_n' ), $t_interval, $t_list );
		case 'monthly':
			$t_dom = (int)$p_template['day_of_month'];
			$t_day_label = $t_dom <= 0 ? plugin_lang_get( 'last_day' ) : $t_dom;
			return $t_interval == 1
				? sprintf( plugin_lang_get( 'desc_monthly' ), $t_day_label )
				: sprintf( plugin_lang_get( 'desc_monthly_n' ), $t_interval, $t_day_label );
		case 'yearly':
			$t_dom = (int)$p_template['day_of_month'];
			$t_day_label = $t_dom <= 0 ? plugin_lang_get( 'last_day' ) : $t_dom;
			$t_month_names = issue_recurrence_month_names();
			$t_month = $t_month_names[max( 1, min( 12, (int)$p_template['month_of_year'] ) )];
			return $t_interval == 1
				? sprintf( plugin_lang_get( 'desc_yearly' ), $t_day_label, $t_month )
				: sprintf( plugin_lang_get( 'desc_yearly_n' ), $t_interval, $t_day_label, $t_month );
	}
	return '';
}

/**
 * Lokalisierte Wochentagsnamen (Index 0=So..6=Sa).
 * @return array
 */
function issue_recurrence_weekday_names() {
	return array(
		0 => plugin_lang_get( 'weekday_0' ),
		1 => plugin_lang_get( 'weekday_1' ),
		2 => plugin_lang_get( 'weekday_2' ),
		3 => plugin_lang_get( 'weekday_3' ),
		4 => plugin_lang_get( 'weekday_4' ),
		5 => plugin_lang_get( 'weekday_5' ),
		6 => plugin_lang_get( 'weekday_6' ),
	);
}

/**
 * Lokalisierte Monatsnamen (Index 1=Januar..12=Dezember).
 * @return array
 */
function issue_recurrence_month_names() {
	return array(
		1  => plugin_lang_get( 'month_1' ),
		2  => plugin_lang_get( 'month_2' ),
		3  => plugin_lang_get( 'month_3' ),
		4  => plugin_lang_get( 'month_4' ),
		5  => plugin_lang_get( 'month_5' ),
		6  => plugin_lang_get( 'month_6' ),
		7  => plugin_lang_get( 'month_7' ),
		8  => plugin_lang_get( 'month_8' ),
		9  => plugin_lang_get( 'month_9' ),
		10 => plugin_lang_get( 'month_10' ),
		11 => plugin_lang_get( 'month_11' ),
		12 => plugin_lang_get( 'month_12' ),
	);
}
