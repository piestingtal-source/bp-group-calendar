<?php
/*
BP Group Calendar
*/

//------------------------------------------------------------------------//

//---Config---------------------------------------------------------------//

//------------------------------------------------------------------------//

//include calendar class
require_once( WP_PLUGIN_DIR . '/bp-group-calendar/groupcalendar/calendar.class.php' );

//------------------------------------------------------------------------//

//---Hook-----------------------------------------------------------------//

//------------------------------------------------------------------------//

//check for activating

add_action( 'admin_init', 'bp_group_calendar_make_current' );

add_action( 'groups_group_after_save', 'bp_group_calendar_settings_save' );
add_action( 'bp_after_group_settings_creation_step', 'bp_group_calendar_settings' );
add_action( 'bp_after_group_settings_admin', 'bp_group_calendar_settings' );
add_action( 'wp_enqueue_scripts', 'bp_group_calendar_js' );
add_action( 'wp_head', 'bp_group_calendar_js_output' );
add_action( 'groups_screen_notification_settings', 'bp_group_calendar_notification_settings' );

//activity stream
add_action( 'groups_register_activity_actions', 'bp_group_calendar_reg_activity' );
add_action( 'groups_new_calendar_event', 'groups_update_last_activity' );
add_action( 'groups_edit_calendar_event', 'groups_update_last_activity' );


//widgets
add_action( 'widgets_init', create_function( '', 'return register_widget("BP_Group_Calendar_Widget");' ) );
add_action( 'widgets_init', create_function( '', 'return register_widget("BP_Group_Calendar_Widget_Single");' ) );
add_action( 'widgets_init', create_function( '', 'return register_widget("BP_Group_Calendar_Widget_User_Groups");' ) );

//------------------------------------------------------------------------//

//---Functions------------------------------------------------------------//

//------------------------------------------------------------------------//


function bp_group_calendar_make_current() {

	global $wpdb, $bp_group_calendar_current_version;

	if ( get_site_option( "bp_group_calendar_version" ) == '' ) {
		add_site_option( 'bp_group_calendar_version', '0.0.0' );
	}

	if ( get_site_option( "bp_group_calendar_version" ) == $bp_group_calendar_current_version ) {
		// do nothing
	} else {
		//update to current version
		update_site_option( "bp_group_calendar_installed", "no" );
		update_site_option( "bp_group_calendar_version", $bp_group_calendar_current_version );
	}
	bp_group_calendar_global_install();
}


function bp_group_calendar_global_install() {

	global $wpdb, $bp_group_calendar_current_version;

	if ( get_site_option( "bp_group_calendar_installed" ) == '' ) {
		add_site_option( 'bp_group_calendar_installed', 'no' );
	}

	if ( get_site_option( "bp_group_calendar_installed" ) == "yes" ) {
		// do nothing
	} else {
		$bp_group_calendar_table1 = "CREATE TABLE IF NOT EXISTS `" . $wpdb->base_prefix . "bp_groups_calendars` (
                                  `id` bigint(20) unsigned NOT NULL auto_increment,
                                  `group_id` bigint(20) NOT NULL default '0',
                                  `user_id` bigint(20) NOT NULL default '0',
                                  `event_time` DATETIME NOT NULL,
                                  `event_title` TEXT NOT NULL,
                                  `event_description` TEXT,
                                  `event_location` TEXT,
                                  `event_map` BOOL NOT NULL,
                                  `created_stamp` bigint(30) NOT NULL,
                                  `last_edited_id` bigint(20) NOT NULL default '0',
                                  `last_edited_stamp` bigint(30) NOT NULL,
                                  PRIMARY KEY  (`id`)
                                ) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci;";

		$wpdb->query( $bp_group_calendar_table1 );

		$row = $wpdb->get_results(  "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
			WHERE table_name = '{$wpdb->base_prefix}bp_groups_calendars' AND column_name = 'event_all_day'"  );

		if ( empty( $row ) ){
		   $wpdb->query("ALTER TABLE {$wpdb->base_prefix}bp_groups_calendars ADD event_all_day INT(1) NOT NULL default '0'");
		}

		update_site_option( "bp_group_calendar_installed", "yes" );
	}
}

//extend the group
if ( class_exists( 'BP_Group_Extension' ) ) {
	class BP_Group_Calendar_Extension extends BP_Group_Extension {

		var $visibility = 'private'; // 'public' will show your extension to non-group members, 'private' means you have to be a member of the group to view your extension.
		var $enable_create_step = false; // If your extension does not need a creation step, set this to false
		var $enable_nav_item = false; // If your extension does not need a navigation item, set this to false
		var $enable_edit_item = false; // If your extension does not need an edit screen, set this to false

		function __construct() {
			global $bp;

			$this->name            = __( 'Kalender', 'groupcalendar' );
			$this->slug            = 'calendar';
			$this->enable_nav_item = isset( $bp->groups->current_group->user_has_access ) ? $bp->groups->current_group->user_has_access : false;

			//$this->create_step_position = 21;
			$this->nav_item_position = 36;

		}

		function display( $group_id = NULL ) {
			global $bp;
			$event_id = bp_group_calendar_event_url_parse();

			$edit_event = bp_group_calendar_event_is_edit();

			bp_group_calendar_event_is_delete();

			if ( bp_group_calendar_event_save() === false ) {
				$edit_event = true;
			}

			$date = bp_group_calendar_url_parse();

			do_action( 'template_notices' );

			if ( $edit_event ) {

				//show edit event form
				if ( ! bp_group_calendar_widget_edit_event( $event_id ) ) {

					//default to current month view
					bp_group_calendar_widget_month( $date );
					bp_group_calendar_widget_upcoming_events();
					bp_group_calendar_widget_my_events();
					bp_group_calendar_widget_create_event( $date );

				}

			} else if ( $event_id ) {

				//display_event
				bp_group_calendar_widget_event_display( $event_id );

				//current month view
				bp_group_calendar_widget_month( $date );

			} else if ( isset( $date['year'] ) && ! isset( $date['month'] ) && ! isset( $date['day'] ) ) {

				//year view
				bp_group_calendar_widget_year( $date );

				bp_group_calendar_widget_create_event( $date );

			} else if ( isset( $date['year'] ) && isset( $date['month'] ) && ! isset( $date['day'] ) ) {

				//month view
				bp_group_calendar_widget_month( $date );

				bp_group_calendar_widget_create_event( $date );

			} else if ( isset( $date['year'] ) && isset( $date['month'] ) && isset( $date['day'] ) ) {

				//day view
				bp_group_calendar_widget_day( $date );

				bp_group_calendar_widget_create_event( $date );

			} else {

				//default to current month view
				bp_group_calendar_widget_month( $date );

				bp_group_calendar_widget_upcoming_events();

				bp_group_calendar_widget_my_events();

				bp_group_calendar_widget_create_event( $date );

			}
		}

		function widget_display() {
			bp_group_calendar_widget_upcoming_events();
		}
	}

	bp_register_group_extension( 'BP_Group_Calendar_Extension' );
}

/**
 * Takes a GMT time-date string or timestamp and converts for localized display output in local WP timezone
 *
 * @param int|string $time Timestamp
 * @param string $format PHP datetiem format string. Optional, defaults to WP preference
 * @param boolean $all_day Event lasts the whole day. Optional
 *
 * @return string Localized time
 */
function bgc_date_display( $time, $format = false, $all_day = false ) {
	if ( $all_day ) {
		$format = get_option( 'date_format' );
	}
	if ( ! $format ) {
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
	}

	$timestamp = is_numeric( $time ) ? (int) $time : strtotime( $time );

	//convert GMT time stored in db to local timezone setting
	$localtime = $timestamp + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );

	return date_i18n( $format, $localtime );
}


function bp_group_calendar_settings_save( $group ) {
	global $wpdb;

	if ( isset( $_POST['group-calendar-moderator-capabilities'] ) ) {
		groups_update_groupmeta( $group->id, 'group_calendar_moderator_capabilities', $_POST['group-calendar-moderator-capabilities'] );
	}

	if ( isset( $_POST['group-calendar-member-capabilities'] ) ) {
		groups_update_groupmeta( $group->id, 'group_calendar_member_capabilities', $_POST['group-calendar-member-capabilities'] );
	}

	if ( isset( $_POST['group-calendar-send-email'] ) ) {
		groups_update_groupmeta( $group->id, 'group_calendar_send_email', $_POST['group-calendar-send-email'] );
	}
}


function bp_group_calendar_event_save() {
	global $wpdb, $current_user;

	$calendar_capabilities = bp_group_calendar_get_capabilities();

	if ( isset( $_POST['create-event'] ) ) {
		if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bp_group_calendar' ) ) {
			bp_core_add_message( __( 'Es gab ein Sicherheitsproblem', 'groupcalendar' ), 'error' );

			return false;
		}

		//reject unqualified users
		if ( $calendar_capabilities == 'none' ) {
			bp_core_add_message( __( "Du bist nicht berechtigt, Ereignisse zu bearbeiten", 'groupcalendar' ), 'error' );

			return false;
		}

		//prepare fields
		$group_id    = (int) $_POST['group-id'];
		$event_title = esc_attr( strip_tags( trim( $_POST['event-title'] ) ) );

		//check that required title isset after filtering
		if ( empty( $event_title ) ) {
			bp_core_add_message( __( "Ein Veranstaltungstitel ist erforderlich", 'groupcalendar' ), 'error' );

			return false;
		}

		if ( !empty( $_POST['event-all-day'] ) ) {
			$tmp_date = $_POST['event-date'] . ' 0:00';
		} else {
			$tmp_date = $_POST['event-date'] . ' ' . $_POST['event-hour'] . ':' . $_POST['event-minute'] . $_POST['event-ampm'];
		}
		$tmp_date = strtotime( $tmp_date );
		//check for valid date/time
		if ( $tmp_date && strtotime( $_POST['event-date'] ) && strtotime( $_POST['event-date'] ) != - 1 ) {
			$event_date = gmdate( 'Y-m-d H:i:s', ( $tmp_date - ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) ); //assumed date entered in timezone offset. Subtract the offset to get GMT for storage.
		} else {
			bp_core_add_message( __( "Bitte gib ein gültiges Veranstaltungsdatum ein.", 'groupcalendar' ), 'error' );

			return false;
		}

		$event_description = wp_filter_post_kses( wpautop( $_POST['event-desc'] ) );
		$event_location    = esc_attr( strip_tags( trim( $_POST['event-loc'] ) ) );
		$event_map         = ( isset( $_POST['event-map'] ) && $_POST['event-map'] == 1 ) ? 1 : 0;
		$event_all_day     = (int)( !empty( $_POST['event-all-day'] ) );

		//editing previous event
		if ( isset( $_POST['event-id'] ) ) {

			//can user modify this event?
			if ( $calendar_capabilities == 'limited' ) {
				$creator_id = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM " . $wpdb->base_prefix . "bp_groups_calendars WHERE id = %d AND group_id = %d", (int) $_POST['event-id'], $group_id ) );

				if ( $creator_id != $current_user->ID ) {
					bp_core_add_message( __( "Du bist nicht berechtigt, diesen Termin zu bearbeiten", 'groupcalendar' ), 'error' );

					return false;
				}
			}

			$query = $wpdb->prepare( "UPDATE " . $wpdb->base_prefix . "bp_groups_calendars
                            	SET event_time = %s, event_title = %s, event_description = %s, event_location = %s, event_map = %d, last_edited_id = %d, last_edited_stamp = %d, event_all_day = %d
                            	WHERE id = %d AND group_id = %d LIMIT 1",
				$event_date, $event_title, $event_description, $event_location, $event_map, $current_user->ID, current_time( 'timestamp', true ), $event_all_day, (int) $_POST['event-id'], $group_id );

			if ( $wpdb->query( $query ) ) {
				bp_core_add_message( __( "Ereignis gespeichert", 'groupcalendar' ) );

				//record activity
				bp_group_calendar_event_add_action_message( false, (int) $_POST['event-id'], $event_date, $event_title, $event_all_day );

				return true;
			} else {
				bp_core_add_message( __( "Beim Speichern in der DB ist ein Problem aufgetreten", 'groupcalendar' ), 'error' );

				return false;
			}

		} else { //new event
			$query = $wpdb->prepare(
				"SELECT * FROM " . $wpdb->base_prefix . "bp_groups_calendars WHERE group_id='%d' AND user_id='%d' AND event_time='%s' AND event_title='%s' AND event_description='%s' AND event_location='%s'", $group_id, $current_user->ID, $event_date, $event_title, $event_description, $event_location
			);
			
			$res = $wpdb->query( $query );
			
			if( $res != 0 )
			{
				bp_core_add_message( __( "Anscheinend erstellst Du ein doppeltes Ereignis!", 'groupcalendar' ), 'error' );
				return;
			}

			$query = $wpdb->prepare( "INSERT INTO " . $wpdb->base_prefix . "bp_groups_calendars
                            	( group_id, user_id, event_time, event_title, event_description, event_location, event_map, created_stamp, last_edited_id, last_edited_stamp, event_all_day )
                            	VALUES ( %d, %d, %s, %s, %s, %s, %d, %d, %d, %d, %d )",
				$group_id, $current_user->ID, $event_date, $event_title, $event_description, $event_location, $event_map, current_time( 'timestamp', true ), $current_user->ID, time(), $event_all_day );

			if ( $wpdb->query( $query ) ) {
				$new_id = $wpdb->insert_id;
				bp_core_add_message( __( "Ereignis gespeichert", 'groupcalendar' ) );
				bp_group_calendar_event_add_action_message( true, $new_id, $event_date, $event_title, $event_all_day );

				//email group members
				bp_group_calendar_event_email( true, $new_id, $event_date, $event_title, $event_all_day );

				return true;
			} else {
				bp_core_add_message( __( "Beim Speichern in der DB ist ein Problem aufgetreten", 'groupcalendar' ), 'error' );

				return false;
			}

		}

	}
}

//register activities
function bp_group_calendar_reg_activity() {
	global $bp;
	bp_activity_set_action( $bp->groups->id, 'new_calendar_event', __( 'Neue Gruppenveranstaltung', 'groupcalendar' ) );
	bp_activity_set_action( $bp->groups->id, 'edit_calendar_event', __( 'Geänderte Gruppenveranstaltung', 'groupcalendar' ) );
}

//adds actions to the group recent actions
function bp_group_calendar_event_add_action_message( $new, $event_id, $event_date, $event_title, $event_all_day = false ) {
	global $bp;

	$url = bp_group_calendar_create_event_url( $event_id );

	if ( $new ) {
		$created_type     = __( 'erstellte', 'groupcalendar' );
		$component_action = 'new_calendar_event';
	} else {
		$created_type     = __( 'geändert', 'groupcalendar' );
		$component_action = 'edit_calendar_event';
	}

	$date = bgc_date_display( $event_date, false, $event_all_day );

	/* Record this in group activity stream */
	$action  = sprintf( __( '%s %s ein Ereignis für die Gruppe %s:', 'groupcalendar' ), bp_core_get_userlink( $bp->loggedin_user->id ), $created_type, '<a href="' . bp_get_group_permalink( $bp->groups->current_group ) . '">' . esc_attr( $bp->groups->current_group->name ) . '</a>' );
	$content = '<a href="' . $url . '" title="' . __( 'Ereignis anzeigen', 'groupcalendar' ) . '">' . $date . ': ' . stripslashes( $event_title ) . '</a>';

	$activity_id = groups_record_activity( array(
		'action'            => $action,
		'content'           => $content,
		'primary_link'      => $url,
		'type'              => $component_action,
		'item_id'           => $bp->groups->current_group->id,
		'secondary_item_id' => $event_id
	) );

	//update group last updated
	groups_update_last_activity( $bp->groups->current_group->id );
	do_action( 'bp_groups_posted_update', $content, get_current_user_id(), $bp->groups->current_group->id, $activity_id );

}

//send the email
function bp_group_calendar_event_email( $new, $event_id, $event_date, $event_title, $event_all_day ) {
	global $wpdb, $current_user, $bp, $bgc_email_default;

	//check if email is disabled for group
	$email = groups_get_groupmeta( $bp->groups->current_group->id, 'group_calendar_send_email' );
	if ( empty( $email ) ) {
		$email = BGC_EMAIL_DEFAULT;
	}
	if ( $email == 'no' ) {
		return;
	}

	//prepare fields
	$date              = bgc_date_display( $event_date, false, $event_all_day );
	$url               = bp_group_calendar_create_event_url( $event_id );
	$site_name         = get_blog_option( BP_ROOT_BLOG, 'blogname' );
	$email_subject     = sprintf( __( '%s hat eine neue Veranstaltung für Deine Gruppe %s erstellt', 'groupcalendar' ), bp_core_get_userlink( $bp->loggedin_user->id, true ), $bp->groups->current_group->name );
	$event_description = strip_tags( stripslashes( $_POST['event-desc'] ) );
	$location          = strip_tags( trim( stripslashes( $_POST['event-loc'] ) ) );

	//send emails
	$group_link = trailingslashit( bp_get_group_permalink( $bp->groups->current_group ) );

	$user_ids = BP_Groups_Member::get_group_member_ids( $bp->groups->current_group->id );

	foreach ( $user_ids as $user_id ) {
		//skip opt-outs
		if ( 'no' == get_user_meta( $user_id, 'notification_groups_calendar_event', true ) ) {
			continue;
		}

		$ud = get_userdata( $user_id );

		// Set up and send the message
		$to = $ud->user_email;

		$settings_link = bp_core_get_user_domain( $user_id ) . 'settings/notifications/';

		$message = sprintf( __(
			"%s:
---------------------

%s
%s

Beschreibung:
%s

Standort:
%s

---------------------
Diese Veranstaltung ansehen: %s
Diese Gruppe anzeigen: %s

%s
", 'groupcalendar' ), $email_subject, stripslashes( $event_title ), $date, $event_description, $location, $url, $group_link, $site_name );

		$message .= sprintf( __( 'Um Dich von diesen E-Mails abzumelden, logge Dich bitte ein und besuche: %s', 'groupcalendar' ), $settings_link );

		$message = wp_specialchars_decode( $message, ENT_QUOTES ); //decode quotes for email

		// Send it
		wp_mail( $to, $email_subject, $message );

		unset( $message, $to );
	}

}

function bp_group_calendar_get_capabilities() {
	global $bp;

	if ( bp_group_is_admin() ) {
		return 'full';
	} else if ( bp_group_is_mod() ) {

		$group_calendar_moderator_capabilities = groups_get_groupmeta( $bp->groups->current_group->id, 'group_calendar_moderator_capabilities' );
		if ( empty( $group_calendar_moderator_capabilities ) ) {
			return BGC_MODERATOR_DEFAULT;
		} else {
			return $group_calendar_moderator_capabilities;
		}

	} else if ( bp_group_is_member() ) {

		$group_calendar_member_capabilities = groups_get_groupmeta( $bp->groups->current_group->id, 'group_calendar_member_capabilities' );
		if ( empty( $group_calendar_member_capabilities ) ) {
			return BGC_MEMBER_DEFAULT;
		} else {
			return $group_calendar_member_capabilities;
		}

	} else {
		return 'none';
	}

}

function bp_group_calendar_url_parse() {

	global $wpdb, $current_site;

	$calendar_url = $_SERVER['REQUEST_URI'];

	$calendar_url_clean = explode( "?", $calendar_url );

	$calendar_url = $calendar_url_clean[0];

	$calendar_url_sections = explode( "/calendar/", $calendar_url );

	$calendar_url = $calendar_url_sections[1];

	$calendar_url = ltrim( $calendar_url, "/" );

	$calendar_url = rtrim( $calendar_url, "/" );

	$base = $calendar_url_sections[0] . '/calendar/';

	$base = ltrim( $base, "/" );

	$base = rtrim( $base, "/" );

	if ( ! empty( $calendar_url ) ) {

		$calendar_url          = ltrim( $calendar_url, "/" );
		$calendar_url          = rtrim( $calendar_url, "/" );
		$calendar_url_sections = explode( "/", $calendar_url );

		//check for valid dates.
		if ( isset( $calendar_url_sections[0] ) && $calendar_url_sections[0] >= 2000 && $calendar_url_sections[0] < 3000 ) {
			$date['year'] = (int) $calendar_url_sections[0];
		}

		if ( isset( $date['year'] ) && isset( $calendar_url_sections[1] ) && $calendar_url_sections[1] >= 1 && $calendar_url_sections[1] <= 12 ) {
			$date['month'] = (int) $calendar_url_sections[1];
		}

		if ( isset( $date['month'] ) && isset( $calendar_url_sections[2] ) && $calendar_url_sections[2] >= 1 && $calendar_url_sections[2] <= 31 ) {
			$date['day'] = (int) $calendar_url_sections[2];
		}

	}

	$date['base'] = $base;

	return $date;

}

function bp_group_calendar_event_url_parse() {

	$url = $_SERVER['REQUEST_URI'];
	if ( strpos( "/calendar/event/", $url ) !== false ) {
		return false;
	}

	$url_clean    = explode( "?", $url );
	$url          = $url_clean[0];
	$url_sections = explode( "/calendar/event/", $url );
	$url          = isset( $url_sections[1] ) ? $url_sections[1] : '';
	$url          = ltrim( $url, "/" );
	$url          = rtrim( $url, "edit/" );
	$url          = rtrim( $url, "delete/" );
	$url          = rtrim( $url, "/" );

	if ( is_numeric( $url ) ) {
		return (int) $url;
	} else {
		return false;
	}

}

function bp_group_calendar_event_is_edit() {

	$url = $_SERVER['REQUEST_URI'];
	if ( strpos( $url, "/edit/" ) ) {
		return true;
	} else {
		return false;
	}

}

function bp_group_calendar_event_is_delete() {
	global $wpdb, $current_user, $bp;

	$calendar_capabilities = bp_group_calendar_get_capabilities();
	$event_id              = bp_group_calendar_event_url_parse();
	$group_id              = $bp->groups->current_group->id;
	$url                   = $_SERVER['REQUEST_URI'];
	if ( strpos( $url, "/delete/" ) && $event_id ) {

		//check nonce
		if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'event-delete-link' ) ) {
			bp_core_add_message( __( 'Es gab ein Sicherheitsproblem', 'groupcalendar' ), 'error' );

			return false;
		}

		if ( $calendar_capabilities == 'none' ) {
			bp_core_add_message( __( 'Du hast keine Berechtigung zum Löschen von Ereignissen', 'groupcalendar' ), 'error' );

			return false;
		}

		//can user modify this event?
		if ( $calendar_capabilities == 'limited' ) {
			$creator_id = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM " . $wpdb->base_prefix . "bp_groups_calendars WHERE id = %d AND group_id = %d", $event_id, $group_id ) );

			if ( $creator_id != $current_user->ID ) {
				bp_core_add_message( __( "Du bist nicht berechtigt, dieses Ereignis zu löschen", 'groupcalendar' ), 'error' );

				return false;
			}
		}

		//delete event
		$result = $wpdb->query( $wpdb->prepare( "DELETE FROM " . $wpdb->base_prefix . "bp_groups_calendars WHERE id = %d AND group_id = %d LIMIT 1", $event_id, $group_id ) );

		if ( ! $result ) {
			bp_core_add_message( __( "Beim Löschen ist ein Problem aufgetreten", 'groupcalendar' ), 'error' );

			return false;
		}

		//delete activity tied to event
		$result = $wpdb->query( $wpdb->prepare( "DELETE FROM " . $wpdb->base_prefix . "bp_activity WHERE item_id = %d AND secondary_item_id = %d", $group_id, $event_id ) );

		//success!
		bp_core_add_message( __( 'Ereignis erfolgreich gelöscht', 'groupcalendar' ) );

		return true;

	} else {
		return false;
	}

}


function bp_group_calendar_create_event_url( $event_id, $edit = false ) {
	global $bp;

	$url = bp_get_group_permalink( $bp->groups->current_group );
	$url .= 'calendar/event/' . $event_id . '/';

	if ( $edit ) {
		$url .= "edit/";
	}

	return $url;

}

//------------------------------------------------------------------------//

//---Output Functions-----------------------------------------------------//

//------------------------------------------------------------------------//


function bp_group_calendar_settings() {
	global $wpdb, $current_site, $groups_template;

	if ( ! empty( $groups_template->group ) ) {
		$group = $groups_template->group;
	}

	if ( ! empty( $group ) ) {
		$group_calendar_moderator_capabilities = groups_get_groupmeta( $group->id, 'group_calendar_moderator_capabilities' );
	}

	if ( empty( $group_calendar_moderator_capabilities ) ) {
		$group_calendar_moderator_capabilities = BGC_MODERATOR_DEFAULT;
	}

	if ( ! empty( $group ) ) {
		$group_calendar_member_capabilities = groups_get_groupmeta( $group->id, 'group_calendar_member_capabilities' );
	}

	if ( empty( $group_calendar_member_capabilities ) ) {
		$group_calendar_member_capabilities = BGC_MEMBER_DEFAULT;
	}

	if ( ! empty( $group ) ) {
		$email = groups_get_groupmeta( $group->id, 'group_calendar_send_email' );
	}
	if ( empty( $email ) ) {
		$email = BGC_EMAIL_DEFAULT;
	}

	?>
	<h4><?php _e( 'Group Calendar Settings', 'groupcalendar' ) ?></h4>
	<label for="group-calendar-moderator-capabilities"><?php _e( 'Moderatorfunktionen', 'groupcalendar' ) ?></label>

	<select name="group-calendar-moderator-capabilities" id="group-calendar-moderator-capabilities">

		<option value="full" <?php if ( $group_calendar_moderator_capabilities == 'full' ) {
			echo 'selected="selected"';
		} ?>><?php _e( 'Events erstellen / Alle Events bearbeiten', 'groupcalendar' ); ?></option>

		<option value="limited" <?php if ( $group_calendar_moderator_capabilities == 'limited' ) {
			echo 'selected="selected"';
		} ?>><?php _e( 'Events erstellen / Eigene Events bearbeiten', 'groupcalendar' ); ?></option>

		<option value="none" <?php if ( $group_calendar_moderator_capabilities == 'none' ) {
			echo 'selected="selected"';
		} ?>><?php _e( 'Keine Fähigkeiten', 'groupcalendar' ); ?></option>

	</select>

	<label for="group-calendar-member-capabilities"><?php _e( 'Member Capabilities', 'groupcalendar' ) ?></label>

	<select name="group-calendar-member-capabilities" id="group-calendar-member-capabilities">

		<option value="full" <?php if ( $group_calendar_member_capabilities == 'full' ) {
			echo 'selected="selected"';
		} ?>><?php _e( 'Events erstellen / Alle Events bearbeiten', 'groupcalendar' ); ?></option>

		<option value="limited" <?php if ( $group_calendar_member_capabilities == 'limited' ) {
			echo 'selected="selected"';
		} ?>><?php _e( 'Events erstellen / Eigene Events bearbeiten', 'groupcalendar' ); ?></option>

		<option value="none" <?php if ( $group_calendar_member_capabilities == 'none' ) {
			echo 'selected="selected"';
		} ?>><?php _e( 'Keine Fähigkeiten', 'groupcalendar' ); ?></option>

	</select>

	<label><?php _e( 'Enable Email Notification of New Events', 'groupcalendar' ) ?><br/>
		<small><?php _e( 'Einzelne Benutzer können Benachrichtigungen deaktivieren', 'groupcalendar' ) ?></small>
	</label>
	<label><input value="yes" name="group-calendar-send-email"
	              type="radio"<?php echo ( $email == 'yes' ) ? ' checked="checked"' : ''; ?> /> <?php _e( 'Ja', 'groupcalendar' ) ?>
	</label>
	<label><input value="no" name="group-calendar-send-email"
	              type="radio"<?php echo ( $email == 'no' ) ? ' checked="checked"' : ''; ?> /> <?php _e( 'Nein', 'groupcalendar' ) ?>
	</label><br/>
	<hr/>
	<?php
}

function bp_group_calendar_notification_settings() {
	if ( ! $notification_groups_calendar_event = bp_get_user_meta( bp_displayed_user_id(), 'notification_groups_calendar_event', true ) ) {
		$notification_groups_calendar_event = 'yes';
	}
	?>
	<tr>
		<td></td>
		<td><?php _e( 'Ein neues Gruppenereignis wird erstellt', 'groupcalendar' ) ?></td>
		<td class="yes"><input type="radio" name="notifications[notification_groups_calendar_event]"
		                       value="yes" <?php checked( $notification_groups_calendar_event, 'yes', true ) ?>/></td>
		<td class="no"><input type="radio" name="notifications[notification_groups_calendar_event]"
		                      value="no" <?php checked( $notification_groups_calendar_event, 'no', true ) ?>/></td>
	</tr>
	<?php
}

//enqeue js on product settings screen
function bp_group_calendar_js() {

	if ( ! is_admin() && strpos( $_SERVER['REQUEST_URI'], '/calendar/' ) !== false ) {
		global $bgc_locale;

		wp_enqueue_style( 'groupcalendar-css', plugins_url( '/bp-group-calendar/groupcalendar/group_calendar.css' ), false );
		wp_enqueue_style( 'jquery-datepicker-css', plugins_url( '/bp-group-calendar/groupcalendar/datepicker/css/smoothness/jquery-ui-1.10.3.custom.min.css' ), '1.10.3' );
		wp_enqueue_script( 'jquery-ui-datepicker' );

		//only load languages for datepicker if not english (or it will show Chinese!)
		if ( $bgc_locale['code'] != 'en' ) {
			wp_enqueue_script( 'jquery-datepicker-i18n', plugins_url( '/bp-group-calendar/groupcalendar/datepicker/js/jquery-ui-i18n.min.js' ), array( 'jquery-ui-datepicker' ) );
		}
	}
}

function bp_group_calendar_js_output() {
	//display css
	if ( ! is_admin() && strpos( $_SERVER['REQUEST_URI'], '/calendar/' ) !== false ) {
		global $bgc_locale;
		?>
		<script type="text/javascript">
			jQuery(document).ready(function ($) {
				jQuery.datepicker.setDefaults(jQuery.datepicker.regional['<?php echo $bgc_locale['code']; ?>']);
				jQuery('#event-date').datepicker({
					dateFormat: 'yy-mm-dd',
					changeMonth: true,
					changeYear: true,
					firstDay: <?php echo $bgc_locale['week_start']; ?>
				});
				jQuery('a#event-delete-link').click(function () {
					var answer = confirm("<?php _e('Möchtest Du diese Veranstaltung wirklich löschen?', 'groupcalendar'); ?>")
					if (answer) {
						return true;
					} else {
						return false;
					}
					;
				});
				jQuery( '#event-all-day' ).change(function() {
					if ( this.checked ) {
						jQuery( this ).parent( 'label' ).prev().css( 'display', 'none' ).find( 'select' ).prop( 'disabled', 'disabled' );
					} else {
						jQuery( this ).parent( 'label' ).prev().css( 'display', 'block' ).find( 'select' ).removeProp( 'disabled' );
					}
				});
				jQuery( '#event-all-day' ).change();
			});
		</script>
		<?php
	}
}

function bp_group_calendar_highlighted_events( $group_id, $date = '' ) {
	global $wpdb;

	if ( $date ) {
		$start_date = date( 'Y-m-d H:i:s', strtotime( date( 'Y-m-', strtotime( $date ) ) . '01 00:00:00' ) - ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
		$end_date   = date( 'Y-m-d H:i:s', strtotime( "+1 month", strtotime( $date ) ) - ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
	} else {
		$start_date = date( 'Y-m-d H:i:s', strtotime( date( 'Y-m-' ) . '01 00:00:00' ) - ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
		$end_date   = date( 'Y-m-d H:i:s', strtotime( "+1 month", strtotime( $start_date ) ) );
	}

	$filter = $wpdb->prepare( " WHERE group_id = %d AND event_time >= %s AND event_time < %s", $group_id, $start_date, $end_date );

	$events = $wpdb->get_col( "SELECT event_time FROM " . $wpdb->base_prefix . "bp_groups_calendars" . $filter . " ORDER BY event_time ASC" );

	if ( $events ) {

		$highlighted_events = array();
		foreach ( $events as $event ) {
			$tmp_date             = strtotime( $event ) + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
			$highlighted_events[] = date( 'Y-m-d', $tmp_date );
		}

		return $highlighted_events;

	} else {
		return array();
	}

}

function bp_group_calendar_list_events( $group_id, $range, $date = '', $calendar_capabilities, $show_all = 0 ) {
	global $wpdb, $current_user;

	$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
	$group_id    = (int) $group_id;//cast it just in case

	if ( $range == 'all' ) {

		$filter        = " WHERE group_id = $group_id";
		$empty_message = __( 'Es gibt keine geplanten Veranstaltungen', 'groupcalendar' );

	} else if ( $range == 'month' ) {

		if ( $date ) {
			$start_date = date( 'Y-m-d H:i:s', strtotime( date( 'Y-m-', strtotime( $date ) ) . '01 00:00:00' ) - ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
			$end_date   = date( 'Y-m-d H:i:s', strtotime( "+1 month", strtotime( $date ) ) - ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
		} else {
			$start_date = date( 'Y-m-d H:i:s', strtotime( date( 'Y-m-' ) . '01 00:00:00' ) - ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
			$end_date   = date( 'Y-m-d H:i:s', strtotime( "+1 month", strtotime( $start_date ) ) );
		}
		$filter        = " WHERE group_id = $group_id AND event_time >= '$start_date' AND event_time < '$end_date'";
		$empty_message = __( 'Für diesen Monat sind keine Veranstaltungen geplant', 'groupcalendar' );

	} else if ( $range == 'day' ) {

		$start_date    = date( 'Y-m-d H:i:s', strtotime( $date ) - ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
		$end_date      = date( 'Y-m-d H:i:s', strtotime( date( 'Y-m-d', strtotime( $date ) ) . ' 23:59:59' ) - ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
		$filter        = " WHERE group_id = $group_id AND event_time >= '$start_date' AND event_time <= '$end_date'";
		$empty_message = __( 'Für diesen Tag sind keine Veranstaltungen geplant', 'groupcalendar' );
		$date_format   = get_option( 'time_format' );

	} else if ( $range == 'upcoming' ) {

		$filter        = " WHERE group_id = $group_id AND event_time >= '" . date( 'Y-m-d H:i:s' ) . "'";
		$empty_message = __( 'Es gibt keine bevorstehenden Veranstaltungen', 'groupcalendar' );

	} else if ( $range == 'mine' ) {

		$filter        = " WHERE group_id = $group_id AND event_time >= '" . date( 'Y-m-d H:i:s' ) . "' AND user_id = " . $current_user->ID;
		$empty_message = __( 'Du hast keine geplanten Ereignisse', 'groupcalendar' );

	}

	if ( ! $show_all ) {
		$limit = " LIMIT 10";
	}

	$events = $wpdb->get_results( "SELECT * FROM " . $wpdb->base_prefix . "bp_groups_calendars" . $filter . " ORDER BY event_time ASC" . $limit );

	if ( $events ) {

		$events_list = '<ul class="events-list">';
		//loop through events
		foreach ( $events as $event ) {
			$class = ( $event->user_id == $current_user->ID ) ? ' class="my_event"' : '';

			$events_list .= "\n<li" . $class . ">";

			$events_list .= '<a href="' . bp_group_calendar_create_event_url( $event->id ) . '" title="' . __( 'Ereignis anzeigen', 'groupcalendar' ) . '" class="event_title">' . bgc_date_display( $event->event_time, $date_format, $event->event_all_day ) . ': ' . stripslashes( $event->event_title ) . '</a>';

			//add edit link if allowed
			if ( $calendar_capabilities == 'full' || ( $calendar_capabilities == 'limited' && $event->user_id == $current_user->ID ) ) {
				$events_list .= ' | <a href="' . bp_group_calendar_create_event_url( $event->id, true ) . '" title="' . __( 'Ereignis bearbeiten', 'groupcalendar' ) . '">' . __( 'Bearbeiten', 'groupcalendar' ) . ' &raquo;</a>';
			}

			$events_list .= "</li>";
		}
		$events_list .= "</ul>";

	} else { //no events for query
		$events_list = '<div id="message" class="info"><p>' . $empty_message . '</p></div>';
	}

	echo $events_list;
}

//------------------------------------------------------------------------//

//---Page Output Functions------------------------------------------------//

//------------------------------------------------------------------------//

//widgets

function bp_group_calendar_widget_day( $date ) {
	global $bp, $bgc_locale;

	$calendar_capabilities = bp_group_calendar_get_capabilities();

	$day = $date['year'] . '-' . $date['month'] . '-' . $date['day'];

	$cal                    = new Calendar( $date['day'], $date['year'], $date['month'] );
	$cal->week_start        = $bgc_locale['week_start'];
	$first_day              = $cal->year . "-" . $cal->month . "-01";
	$cal->highlighted_dates = bp_group_calendar_highlighted_events( $bp->groups->current_group->id, $first_day );
	$url                    = bp_get_group_permalink( $bp->groups->current_group ) . 'calendar';
	$url                    = rawurldecode( $url );
	$cal->formatted_link_to = $url . '/%Y/%m/%d/';
	?>
	<div class="bp-widget">
		<h4><?php _e( 'Tagesansicht', 'groupcalendar' ); ?>
			: <?php echo date_i18n( get_option( 'date_format' ), strtotime( $day ) ); ?></h4>
		<table class="calendar-view">
			<tr>
				<td class="cal-left">
					<?php print( $cal->output_calendar() ); ?>
				</td>
				<td class="cal-right">
					<h5 class="events-title"><?php _e( "Veranstaltungen für", 'groupcalendar' ); ?> <?php echo date_i18n( get_option( 'date_format' ), strtotime( $day ) ); ?>
						:</h5>
					<?php bp_group_calendar_list_events( $bp->groups->current_group->id, 'day', $day, $calendar_capabilities ); ?>
				</td>
			</tr>
		</table>
	</div>
	<?php
}

function bp_group_calendar_widget_month( $date ) {
	global $bp, $bgc_locale;

	$calendar_capabilities = bp_group_calendar_get_capabilities();

	if ( isset( $date['month'] ) ) {
		//show selected month
		$cal = new Calendar( '', $date['year'], $date['month'] );
	} else {
		//show current month
		$cal = new Calendar();
	}

	$cal->week_start        = $bgc_locale['week_start'];
	$url                    = bp_get_group_permalink( $bp->groups->current_group ) . 'calendar';
	$url                    = rawurldecode( $url );
	$cal->formatted_link_to = $url . '/%Y/%m/%d/';

	//first day of month for calulation previous and next months
	$first_day              = $cal->year . "-" . $cal->month . "-01";
	$cal->highlighted_dates = bp_group_calendar_highlighted_events( $bp->groups->current_group->id, $first_day );
	$previous_month         = $url . date( "/Y/m/", strtotime( "-1 month", strtotime( $first_day ) ) );
	$next_month             = $url . date( "/Y/m/", strtotime( "+1 month", strtotime( $first_day ) ) );
	$this_year              = $url . date( "/Y/", strtotime( $first_day ) );
	?>
	<div class="bp-widget">
		<h4>
			<?php _e( 'Monatsansicht', 'groupcalendar' ); ?>:
			<?php echo date_i18n( __( 'F Y', 'groupcalendar' ), strtotime( $first_day ) ); ?>
			<span>
        <a title="<?php _e( 'Vorheriger Monat', 'groupcalendar' ); ?>"
           href="<?php echo $previous_month; ?>">&larr; <?php _e( 'Vorheriger', 'groupcalendar' ); ?></a> |
        <a title="<?php _e( 'Volles Jahr', 'groupcalendar' ); ?>"
           href="<?php echo $this_year; ?>"><?php _e( 'Jahr', 'groupcalendar' ); ?></a> |
        <a title="<?php _e( 'Nächsten Monat', 'groupcalendar' ); ?>"
           href="<?php echo $next_month; ?>"><?php _e( 'Nächster', 'groupcalendar' ); ?> &rarr;</a>
      </span>
		</h4>
		<table class="calendar-view">
			<tr>
				<td class="cal-left">
					<?php print( $cal->output_calendar() ); ?>
				</td>
				<td class="cal-right">
					<h5 class="events-title"><?php _e( 'Veranstaltungen für', 'groupcalendar' ); ?> <?php echo date_i18n( 'F Y', strtotime( $first_day ) ); ?>
						:</h5>
					<?php bp_group_calendar_list_events( $bp->groups->current_group->id, 'month', $first_day, $calendar_capabilities ); ?>
				</td>
			</tr>
		</table>

	</div>
	<?php
}

function bp_group_calendar_widget_year( $date ) {
	global $bp, $bgc_locale;

	$calendar_capabilities = bp_group_calendar_get_capabilities();

	$year  = $date['year'];
	$month = 1;

	$url = bp_get_group_permalink( $bp->groups->current_group ) . 'calendar';
	//first day of month for calulation previous and next years
	$first_day     = $year . "-01-01";
	$previous_year = $url . date( "/Y/", strtotime( "-1 year", strtotime( $first_day ) ) );
	$next_year     = $url . date( "/Y/", strtotime( "+1 year", strtotime( $first_day ) ) );

	?>
	<div class="bp-widget">
		<h4>
			<?php _e( 'Jahresansicht', 'groupcalendar' ); ?>: <?php echo date_i18n( 'Y', strtotime( $first_day ) ); ?>
			<span>
        <a title="<?php _e( 'Vorheriges Jahr', 'groupcalendar' ); ?>"
           href="<?php echo $previous_year; ?>">&larr; <?php _e( 'Vorheriges', 'groupcalendar' ); ?></a> |
        <a title="<?php _e( 'Nächstes Jahr', 'groupcalendar' ); ?>"
           href="<?php echo $next_year; ?>"><?php _e( 'Nächstes', 'groupcalendar' ); ?> &rarr;</a>
      </span>
		</h4>
		<?php
		//loop through years
		for ( $i = 1; $i <= 12; $i ++ ) {
			$cal                    = new Calendar( '', $year, $month );
			$cal->week_start        = $bgc_locale['week_start'];
			$cal->formatted_link_to = $url . '/%Y/%m/%d/';
			$first_day              = $cal->year . "-" . $cal->month . "-01";
			$cal->highlighted_dates = bp_group_calendar_highlighted_events( $bp->groups->current_group->id, $first_day );
			echo '<div class="year-cal-item">';
			print( $cal->output_calendar() );
			echo '</div>';

			//first day of month for calulation of next month
			$first_day  = $year . "-" . $month . "-01";
			$next_stamp = strtotime( "+1 month", strtotime( $first_day ) );
			$year       = date( "Y", $next_stamp );
			$month      = date( "m", $next_stamp );
		}
		?>
		<div class="clear"></div>
	</div>
	<?php
}

function bp_group_calendar_widget_upcoming_events() {
	global $bp;

	$calendar_capabilities = bp_group_calendar_get_capabilities();

	?>
	<div class="bp-widget">
		<h4><?php _e( 'Upcoming Events', 'groupcalendar' ); ?></h4>
		<?php bp_group_calendar_list_events( $bp->groups->current_group->id, 'upcoming', '', $calendar_capabilities ); ?>
	</div>
	<?php
}

function bp_group_calendar_widget_my_events() {
	global $bp;

	if ( ! is_user_logged_in() ) {
		return;
	}

	$calendar_capabilities = bp_group_calendar_get_capabilities();

	?>
	<div class="bp-widget">
		<h4><?php _e( 'Meine Veranstaltungen', 'groupcalendar' ); ?></h4>
		<?php bp_group_calendar_list_events( $bp->groups->current_group->id, 'mine', '', $calendar_capabilities ); ?>
	</div>
	<?php
}


function bp_group_calendar_widget_event_display( $event_id ) {
	global $wpdb, $current_user, $bp, $bgc_locale;

	$group_id = $bp->groups->current_group->id;

	$calendar_capabilities = bp_group_calendar_get_capabilities();

	$event = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . $wpdb->base_prefix . "bp_groups_calendars WHERE group_id = %d AND id = %d", $group_id, $event_id ) );

	//do nothing if invalid event_id
	if ( ! $event ) {
		return;
	}

	//creat edit link if capable
	if ( $calendar_capabilities == 'full' || ( $calendar_capabilities == 'limited' && $current_user->ID == $event->user_id ) ) {
		$edit_link = '<span><a href="' . bp_group_calendar_create_event_url( $event_id, true ) . '" title="' . __( 'Ereignis bearbeiten', 'groupcalendar' ) . '">' . __( 'Bearbeiten', 'groupcalendar' ) . ' &rarr;</a></span>';
	}

	$map_url = 'http://maps.google.com/maps?hl=' . $bgc_locale['code'] . '&q=' . urlencode( stripslashes( $event->event_location ) );

	$event_created_by    = bp_core_get_userlink( $event->user_id );
	$event_created       = bgc_date_display( $event->created_stamp, get_option( 'date_format' ) . __( ' \a\t ', 'groupcalendar' ) . get_option( 'time_format' ), $event->event_all_day );
	$event_modified_by   = bp_core_get_userlink( $event->last_edited_id );
	$event_last_modified = bgc_date_display( $event->last_edited_stamp, get_option( 'date_format' ) . __( ' \a\t ', 'groupcalendar' ) . get_option( 'time_format' ) );

	$event_meta = '<span class="event-meta">' . sprintf( __( 'Erstellt von %1$s am %2$s. Zuletzt geändert von %3$s am %4$s.', 'groupcalendar' ), $event_created_by, $event_created, $event_modified_by, $event_last_modified ) . '</span>';

	?>
	<div class="bp-widget">
		<h4>
			<?php _e( 'Veranstaltungsdetails', 'groupcalendar' ); ?>
			<?php echo $edit_link; ?>
		</h4>

		<h5 class="events-title"><?php echo stripslashes( $event->event_title ); ?></h5>
		<span
			class="activity"><?php
			echo bgc_date_display( $event->event_time, get_option( 'date_format' ) . __( ' \a\t ', 'groupcalendar' ) . get_option( 'time_format' ), $event->event_all_day ); ?></span>

		<?php if ( $event->event_description ) : ?>
			<h6 class="event-label"><?php _e( 'Beschreibung:', 'groupcalendar' ); ?></h6>
			<div class="event-description">
				<?php echo stripslashes( $event->event_description ); ?>
			</div>
		<?php endif; ?>

		<?php if ( $event->event_location ) : ?>
			<h6 class="event-label"><?php _e( 'Standort:', 'groupcalendar' ); ?></h6>
			<div class="event-location">

				<?php echo stripslashes( $event->event_location ); ?>

				<?php if ( $event->event_map ) : ?>
					<span class="event-map">
    	    <a href="<?php echo $map_url; ?>" target="_blank"
	           title="<?php _e( 'Google Map des Veranstaltungsortes anzeigen', 'groupcalendar' ); ?>"><?php _e( 'Karte', 'groupcalendar' ); ?> &raquo;</a>
    	  </span>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php echo $event_meta; ?>

	</div>
	<?php
}


function bp_group_calendar_widget_create_event( $date ) {
	global $bp, $bgc_locale;

	$calendar_capabilities = bp_group_calendar_get_capabilities();

	//don't display widget if no capabilities
	if ( $calendar_capabilities == 'none' ) {
		return;
	}

	//if date given and valid, default form to it
	$default_date = '';
	if ( ! empty( $date['year'] ) && ! empty( $date['month'] ) && ! empty( $date['day'] ) ) {
		$timestamp = strtotime( $date['month'] . '/' . $date['day'] . '/' . $date['year'] );
		if ( $timestamp >= time() ) {
			$default_date = date( 'Y-m-d', $timestamp );
		}
	}

	$url = bp_get_group_permalink( $bp->groups->current_group ) . 'calendar/';

	?>
	<div class="bp-widget">
		<h4><?php _e( 'Ereignis erstellen', 'groupcalendar' ); ?></h4>

		<form action="<?php echo $url; ?>" name="add-event-form" id="add-event-form" class="standard-form" method="post"
		      enctype="multipart/form-data">
			<label for="event-title"><?php _e( 'Titel', 'groupcalendar' ); ?> *</label>
			<input name="event-title" id="event-title" value="" type="text">

			<label for="event-date"><?php _e( 'Datum', 'groupcalendar' ); ?> *
				<input name="event-date" id="event-date" value="<?php echo $default_date; ?>" type="text"></label>

			<label for="event-time"><?php _e( 'Uhrzeit', 'groupcalendar' ); ?> *
				<select name="event-hour" id="event-hour">
					<?php
					if ( $bgc_locale['time_format'] == 24 ) {
						$max_hour = 23;
						$default_hour = date( 'G' );//default to current hour for new events
					} else {
						$max_hour = 12;
						$default_hour = date( 'g' );//default to current hour for new events
					}
					for ( $i = 1; $i <= $max_hour; $i ++ ) {
						$hour_check = ( $i == $default_hour ) ? ' selected="selected"' : '';
						echo '<option value="' . $i . '"' . $hour_check . '>' . $i . "</option>\n";
					}
					?>
				</select>
				<select name="event-minute" id="event-minute">
					<option value="00">:00</option>
					<option value="15">:15</option>
					<option value="30">:30</option>
					<option value="45">:45</option>
				</select>
				<?php if ( $bgc_locale['time_format'] == 12 ) : ?>
					<select name="event-ampm" id="event-ampm">
						<option value="am">am</option>
						<option value="pm">pm</option>
					</select>
				<?php endif; ?>
			</label>

			<label for="event-all-day">
				<?php _e( 'Keine Veranstaltungszeit', 'groupcalendar' ); ?>
				<input type="checkbox" name="event-all-day" id="event-all-day" value="1">
			</label>

			<label for="event-desc"><?php _e( 'Beschreibung', 'groupcalendar' ); ?></label>
			<?php
			if ( function_exists( 'wp_editor' ) ) {
				wp_editor( '', 'event-desc', array( 'media_buttons' => false, 'dfw' => false ) );
			} else {
				the_editor( '', 'event-desc', 'event-desc', false );
			}
			?>

			<label for="event-loc"><?php _e( 'Standort', 'groupcalendar' ); ?></label>
			<input name="event-loc" id="event-loc" value="" type="text">

			<label for="event-map"><?php _e( 'Kartenlink anzeigen?', 'groupcalendar' ); ?>
				<input name="event-map" id="event-map" value="1" type="checkbox" checked="checked"/>
				<small><?php _e( '(Hinweis: Standort muss eine Adresse sein)', 'groupcalendar' ); ?></small>
			</label>

			<input name="create-event" id="create-event" value="1" type="hidden">
			<input name="group-id" id="group-id" value="<?php echo $bp->groups->current_group->id; ?>" type="hidden">
			<?php wp_nonce_field( 'bp_group_calendar' ); ?>

			<p><input value="<?php _e( 'Ereignis erstellen', 'groupcalendar' ); ?> &raquo;" id="save" name="save"
			          type="submit"></p>

		</form>

	</div>
	<?php
}


function bp_group_calendar_widget_edit_event( $event_id = false ) {
	global $wpdb, $current_user, $bp, $bgc_locale;

	$url = bp_get_group_permalink( $bp->groups->current_group ) . 'calendar/';

	$group_id = $bp->groups->current_group->id;

	$calendar_capabilities = bp_group_calendar_get_capabilities();

	//don't display widget if no capabilities
	if ( $calendar_capabilities == 'none' ) {
		bp_core_add_message( __( "Du bist nicht berechtigt, Ereignisse zu bearbeiten", 'groupcalendar' ), 'error' );

		return false;
	}

	if ( $event_id ) { //load from DB

		$url .= 'event/' . $event_id . '/';

		$event = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . $wpdb->base_prefix . "bp_groups_calendars WHERE group_id = %d AND id = %d", $group_id, $event_id ) );

		if ( ! $event ) {
			return false;
		}

		//check limited capability users
		if ( $calendar_capabilities == 'limited' && $current_user->ID != $event->user_id ) {
			bp_core_add_message( __( "Du bist nicht berechtigt, diesen Termin zu bearbeiten", 'groupcalendar' ), 'error' );

			return false;
		}

		$event_title = stripslashes( $event->event_title );

		//check for valid date/time
		$tmp_date = strtotime( $event->event_time ) + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
		if ( $tmp_date ) {
			$event_date = date( 'Y-m-d', $tmp_date );

			if ( $bgc_locale['time_format'] == 12 ) {
				$event_hour = date( 'g', $tmp_date );
			} else {
				$event_hour = date( 'G', $tmp_date );
			}

			$event_minute = date( 'i', $tmp_date );
			$event_ampm   = date( 'a', $tmp_date );
		} else {
			//default to current hour for new events
			if ( $bgc_locale['time_format'] == 12 ) {
				$event_hour = date( 'g' );
			} else {
				$event_hour = date( 'G' );
			}
		}

		$event_description = stripslashes( $event->event_description );
		$event_location    = stripslashes( $event->event_location );
		$event_map         = ( $event->event_map == 1 ) ? ' checked="checked"' : '';
		$event_all_day     = checked( $event->event_all_day, '1', false );

		$event_created_by    = bp_core_get_userlink( $event->user_id );
		$event_created       = bgc_date_display( $event->created_stamp, get_option( 'date_format' ) . __( ' \a\t ', 'groupcalendar' ) . get_option( 'time_format' ), $event->event_all_day );
		$event_modified_by   = bp_core_get_userlink( $event->last_edited_id );
		$event_last_modified = bgc_date_display( $event->last_edited_stamp, get_option( 'date_format' ) . __( ' \a\t ', 'groupcalendar' ) . get_option( 'time_format' ) );

		$event_meta = '<span class="event-meta">' . sprintf( __( 'Erstellt von %1$s am %2$s. Zuletzt geändert von %3$s am %4$s.', 'groupcalendar' ), $event_created_by, $event_created, $event_modified_by, $event_last_modified ) . '</span>';

		$delete_url  = $url . 'delete/';
		$delete_url  = wp_nonce_url( $delete_url, 'event-delete-link' );
		$delete_link = '<span><a id="event-delete-link" href="' . $delete_url . '" title="' . __( 'Ereignis löschen', 'groupcalendar' ) . '">' . __( 'Löschen', 'groupcalendar' ) . ' &rarr;</a></span>';

	} else { //load POST variables

		if ( ! isset( $_POST['create-event'] ) ) {
			return false;
		}

		$event_title = esc_attr( strip_tags( trim( stripslashes( $_POST['event-title'] ) ) ) );
		$tmp_date    = $_POST['event-date'] . ' ' . $_POST['event-hour'] . ':' . $_POST['event-minute'] . $_POST['event-ampm'];
		$tmp_date    = strtotime( $tmp_date );

		//check for valid date/time
		if ( $tmp_date && $tmp_date >= current_time( 'timestamp' ) ) {
			$event_date = date( 'Y-m-d', $tmp_date );

			if ( $bgc_locale['time_format'] == 12 ) {
				$event_hour = date( 'g', $tmp_date );
			} else {
				$event_hour = date( 'G', $tmp_date );
			}

			$event_minute = date( 'i', $tmp_date );
			$event_ampm   = date( 'a', $tmp_date );
		} else {
			$event_hour = 7;
		}

		$event_description = stripslashes( wp_filter_post_kses( $_POST['event-desc'] ) );
		$event_location    = esc_attr( strip_tags( trim( stripslashes( $_POST['event-loc'] ) ) ) );
		$event_map         = ( $_POST['event-map'] == 1 ) ? ' checked="checked"' : '';
		$event_all_day     = checked( $_POST['event-all-day'], '1', false );

	}

	?>
	<div class="bp-widget">
		<h4>
			<?php _e( 'Ereignis bearbeiten', 'groupcalendar' ); ?>
			<?php echo $delete_link; ?>
		</h4>

		<form action="<?php echo $url; ?>" name="add-event-form" id="add-event-form" class="standard-form" method="post"
		      enctype="multipart/form-data">
			<label for="event-title"><?php _e( 'Titel', 'groupcalendar' ); ?> *</label>
			<input name="event-title" id="event-title" value="<?php echo $event_title; ?>" type="text">

			<label for="event-date"><?php _e( 'Datum', 'groupcalendar' ); ?> *
				<input name="event-date" id="event-date" value="<?php echo $event_date; ?>" type="text"></label>

			<label for="event-time"><?php _e( 'Uhrzeit', 'groupcalendar' ); ?> *
				<select name="event-hour" id="event-hour">
					<?php
					if ( $bgc_locale['time_format'] == 24 ) {
						$bgc_locale['time_format'] = 23;
					}
					for ( $i = 1; $i <= $bgc_locale['time_format']; $i ++ ) {
						$hour_check = ( $i == $event_hour ) ? ' selected="selected"' : '';
						echo '<option value="' . $i . '"' . $hour_check . '>' . $i . "</option>\n";
					}
					?>
				</select>
				<select name="event-minute" id="event-minute">
					<option value="00"<?php echo ( $event_minute == '00' ) ? ' selected="selected"' : ''; ?>>:00
					</option>
					<option value="15"<?php echo ( $event_minute == '15' ) ? ' selected="selected"' : ''; ?>>:15
					</option>
					<option value="30"<?php echo ( $event_minute == '30' ) ? ' selected="selected"' : ''; ?>>:30
					</option>
					<option value="45"<?php echo ( $event_minute == '45' ) ? ' selected="selected"' : ''; ?>>:45
					</option>
				</select>
				<?php if ( $bgc_locale['time_format'] == 12 ) : ?>
					<select name="event-ampm" id="event-ampm">
						<option value="am"<?php echo ( $event_ampm == 'am' ) ? ' selected="selected"' : ''; ?>>am
						</option>
						<option value="pm"<?php echo ( $event_ampm == 'pm' ) ? ' selected="selected"' : ''; ?>>pm
						</option>
					</select>
				<?php endif; ?>
			</label>

			<label for="event-all-day">
				<?php _e( 'Keine Veranstaltungszeit', 'groupcalendar' ); ?>
				<input type="checkbox" name="event-all-day" id="event-all-day" value="1" <?php echo $event_all_day; ?>>
			</label>

			<label for="event-desc"><?php _e( 'Beschreibung', 'groupcalendar' ); ?></label>
			<?php if ( function_exists( 'wp_editor' ) ) {
				wp_editor( $event_description, 'event-desc', array( 'media_buttons' => false, 'dfw' => false ) );
			} else {
				the_editor( $event_description, 'event-desc', 'event-desc', false );
			}
			?>

			<label for="event-loc"><?php _e( 'Standort', 'groupcalendar' ); ?></label>
			<input name="event-loc" id="event-loc" value="<?php echo $event_location; ?>" type="text">

			<label for="event-map"><?php _e( 'Kartenlink anzeigen?', 'groupcalendar' ); ?>
				<input name="event-map" id="event-map" value="1" type="checkbox"<?php echo $event_map; ?> />
				<small><?php _e( '(Hinweis: Standort muss eine Adresse sein)', 'groupcalendar' ); ?></small>
			</label>

			<?php if ( $event_id ) : ?>
				<input name="event-id" id="event-id" value="<?php echo $event_id; ?>" type="hidden">
			<?php endif; ?>

			<input name="create-event" id="create-event" value="1" type="hidden">
			<input name="group-id" id="group-id" value="<?php echo $bp->groups->current_group->id; ?>" type="hidden">
			<?php wp_nonce_field( 'bp_group_calendar' ); ?>

			<?php echo $event_meta; ?>

			<p><input value="<?php _e( 'Ereignis speichern', 'groupcalendar' ); ?> &raquo;" id="save" name="save" type="submit">
			</p>

		</form>

	</div>
	<?php

	//return true if all is well
	return true;
}


class BP_Group_Calendar_Widget extends WP_Widget {

	function __construct() {
		$widget_ops = array(
			'classname'   => 'bp_group_calendar',
			'description' => __( 'Zeigt bevorstehende öffentliche Gruppenereignisse an.', 'groupcalendar' )
		);
		parent::__construct( 'bp_group_calendar', __( 'Gruppenveranstaltungen', 'groupcalendar' ), $widget_ops );
	}

	function widget( $args, $instance ) {
		global $wpdb, $current_user, $bp;

		extract( $args );

		echo $before_widget;
		$title = $instance['title'];
		if ( ! empty( $title ) ) {
			echo $before_title . apply_filters( 'widget_title', $title ) . $after_title;
		};

		$event_date = gmdate( 'Y-m-d H:i:s', ( strtotime( current_time( 'mysql' ) ) - ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) );
		$events     = $wpdb->get_results( $wpdb->prepare( "SELECT gc.id, gc.user_id, gc.event_title, gc.event_time, gp.name, gc.group_id, gc.event_all_day FROM " . $wpdb->base_prefix . "bp_groups_calendars gc JOIN " . $wpdb->base_prefix . "bp_groups gp ON gc.group_id=gp.id WHERE gc.event_time >= %s AND gp.status = 'public' ORDER BY gc.event_time ASC LIMIT %d", $event_date, (int) $instance['num_events'] ) );

		if ( $events ) {

			echo '<ul class="events-list">';
			//loop through events
			$events_list = '';
			foreach ( $events as $event ) {
				$class = ( $event->user_id == $current_user->ID ) ? ' class="my_event"' : '';
				$events_list .= "\n<li" . $class . ">";
				$group = groups_get_group( array( 'group_id' => $event->group_id ) );
				$url   = bp_get_group_permalink( $group ) . 'calendar/event/' . $event->id . '/';
				$events_list .= stripslashes( $event->name ) . '<br /><a href="' . $url . '" title="' . __( 'Ereignis anzeigen', 'groupcalendar' ) . '">' . bgc_date_display( $event->event_time, false, $event->event_all_day ) . ': ' . stripslashes( $event->event_title ) . '</a>';
				$events_list .= "</li>";
			}
			echo $events_list;
			echo "\n</ul>";

		} else {
			?>
			<div class="widget-error">
				<?php _e( 'Es gibt keine bevorstehenden Gruppenveranstaltungen.', 'groupcalendar' ) ?>
			</div>
		<?php } ?>

		<?php echo $after_widget; ?>
		<?php
	}

	function update( $new_instance, $old_instance ) {
		$instance               = $old_instance;
		$instance['title']      = strip_tags( $new_instance['title'] );
		$instance['num_events'] = strip_tags( $new_instance['num_events'] );

		return $instance;
	}

	function form( $instance ) {
		$instance   = wp_parse_args( (array) $instance, array(
			'title'      => __( 'Kommende Gruppenveranstaltungen', 'groupcalendar' ),
			'num_events' => 10
		) );
		$title      = strip_tags( $instance['title'] );
		$num_events = strip_tags( $instance['num_events'] );
		?>
		<p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Titel:', 'groupcalendar' ) ?> <input
					class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>"
					name="<?php echo $this->get_field_name( 'title' ); ?>" type="text"
					value="<?php echo esc_attr( $title ); ?>"/></label></p>
		<p><label
				for="<?php echo $this->get_field_id( 'num_events' ); ?>"><?php _e( 'Anzahl der Ereignisse:', 'groupcalendar' ) ?>
				<input class="widefat" id="<?php echo $this->get_field_id( 'num_events' ); ?>"
				       name="<?php echo $this->get_field_name( 'num_events' ); ?>" type="text"
				       value="<?php echo esc_attr( $num_events ); ?>" style="width: 30%"/></label></p>
		<?php
	}
}

class BP_Group_Calendar_Widget_Single extends WP_Widget {

	function __construct() {
		$widget_ops = array(
			'classname'   => 'bp_group_calendar_single',
			'description' => __( 'Zeigt anstehende Gruppenereignisse für eine einzelne Gruppe an.', 'groupcalendar' )
		);
		parent::__construct( 'bp_group_calendar_single', __( 'Einzelgruppenveranstaltungen', 'groupcalendar' ), $widget_ops );
	}

	function widget( $args, $instance ) {
		global $wpdb, $current_user, $bp;

		extract( $args );

		echo $before_widget;
		$title = $instance['title'];
		if ( ! empty( $title ) ) {
			echo $before_title . apply_filters( 'widget_title', $title ) . $after_title;
		};

		$event_date = gmdate( 'Y-m-d H:i:s', ( strtotime( current_time( 'mysql' ) ) - ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) );
		$events     = $wpdb->get_results( $wpdb->prepare( "SELECT gc.id, gc.user_id, gc.event_title, gc.event_time, gp.name, gc.group_id, gc.event_all_day FROM " . $wpdb->base_prefix . "bp_groups_calendars gc JOIN " . $wpdb->base_prefix . "bp_groups gp ON gc.group_id=gp.id WHERE gc.event_time >= %s AND gp.id = %d ORDER BY gc.event_time ASC LIMIT %d", $event_date, (int) $instance['group_id'], (int) $instance['num_events'] ) );

		if ( $events ) {

			echo '<ul class="events-list">';
			//loop through events
			$events_list = '';
			foreach ( $events as $event ) {
				$class = ( $event->user_id == $current_user->ID ) ? ' class="my_event"' : '';
				$events_list .= "\n<li" . $class . ">";
				$group = groups_get_group( array( 'group_id' => $event->group_id ) );
				$url   = bp_get_group_permalink( $group ) . 'calendar/event/' . $event->id . '/';
				$events_list .= stripslashes( $event->name ) . '<br /><a href="' . $url . '" title="' . __( 'Ereignis anzeigen', 'groupcalendar' ) . '">' . bgc_date_display( $event->event_time, false, $event->event_all_day ) . ': ' . stripslashes( $event->event_title ) . '</a>';
				$events_list .= "</li>";
			}
			echo $events_list;
			echo "\n</ul>";

		} else {
			?>
			<div class="widget-error">
				<?php _e( 'Es gibt keine bevorstehenden Veranstaltungen für diese Gruppe.', 'groupcalendar' ) ?>
			</div>
		<?php } ?>

		<?php echo $after_widget; ?>
		<?php
	}

	function update( $new_instance, $old_instance ) {
		$instance               = $old_instance;
		$instance['title']      = strip_tags( $new_instance['title'] );
		$instance['num_events'] = strip_tags( $new_instance['num_events'] );
		$instance['group_id']   = (int) $new_instance['group_id'];

		return $instance;
	}

	function form( $instance ) {
		global $wpdb;
		$instance   = wp_parse_args( (array) $instance, array(
			'title'      => __( 'Kommende Gruppenveranstaltungen', 'groupcalendar' ),
			'num_events' => 10
		) );
		$title      = strip_tags( $instance['title'] );
		$num_events = strip_tags( $instance['num_events'] );
		$group_id   = $instance['group_id'];
		?>
		<p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Titel:', 'groupcalendar' ) ?> <input
					class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>"
					name="<?php echo $this->get_field_name( 'title' ); ?>" type="text"
					value="<?php echo esc_attr( $title ); ?>"/></label></p>
		<p><label
				for="<?php echo $this->get_field_id( 'num_events' ); ?>"><?php _e( 'Anzahl der Ereignisse:', 'groupcalendar' ) ?>
				<input class="widefat" id="<?php echo $this->get_field_id( 'num_events' ); ?>"
				       name="<?php echo $this->get_field_name( 'num_events' ); ?>" type="text"
				       value="<?php echo esc_attr( $num_events ); ?>" style="width: 30%"/></label></p>

		<?php
		$groups = $wpdb->get_results( "SELECT id, name FROM {$wpdb->base_prefix}bp_groups ORDER BY name LIMIT 999" ); //we don't want thousands of groups in the dropdown.
		if ( $groups ) {
			echo '<p><label for="' . $this->get_field_id( 'group_id' ) . '">' . __( 'Gruppe:', 'groupcalendar' ) . ' <select class="widefat" id="' . $this->get_field_id( 'group_id' ) . '" name="' . $this->get_field_name( 'group_id' ) . '">';

			foreach ( $groups as $group ) {
				echo '<option value="' . $group->id . '"' . ( ( $group_id == $group->id ) ? ' selected="selected"' : '' ) . '>' . esc_attr( $group->name ) . '</option>';
			}

			echo '</select></label></p>';
		}
	}
}

class BP_Group_Calendar_Widget_User_Groups extends WP_Widget {

	function __construct() {
		$widget_ops = array(
			'classname'   => 'bp_group_calendar_user_groups',
			'description' => __( 'Zeigt bevorstehende Gruppenereignisse für die Gruppen eines angemeldeten Benutzers an.', 'groupcalendar' )
		);
		parent::__construct( 'bp_group_calendar_user_groups', __( 'Gruppenereignisse des Benutzers', 'groupcalendar' ), $widget_ops );
	}

	function widget( $args, $instance ) {
		global $wpdb, $current_user, $bp;

		//only show widget to logged in users
		if ( ! is_user_logged_in() ) {
			return;
		}

		//get the groups the user is part of
		$results = groups_get_user_groups( $current_user->ID );

		//don't show widget if user doesn't have any groups
		if ( $results['total'] == 0 ) {
			return;
		}

		extract( $args );

		echo $before_widget;
		$title = $instance['title'];
		if ( ! empty( $title ) ) {
			echo $before_title . apply_filters( 'widget_title', $title ) . $after_title;
		};

		$group_ids  = implode( ',', $results['groups'] );
		$event_date = gmdate( 'Y-m-d H:i:s', ( strtotime( current_time( 'mysql' ) ) - ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) );
		$events     = $wpdb->get_results( $wpdb->prepare( "SELECT gc.id, gc.user_id, gc.event_title, gc.event_time, gp.name, gc.group_id, gc.event_all_day FROM " . $wpdb->base_prefix . "bp_groups_calendars gc JOIN " . $wpdb->base_prefix . "bp_groups gp ON gc.group_id=gp.id WHERE gc.event_time >= %s AND gp.id IN ($group_ids) ORDER BY gc.event_time ASC LIMIT %d", $event_date, (int) $instance['num_events'] ) );

		if ( $events ) {
			echo '<ul class="events-list">';
			//loop through events
			$events_list = '';
			foreach ( $events as $event ) {
				$class = ( $event->user_id == $current_user->ID ) ? ' class="my_event"' : '';
				$events_list .= "\n<li" . $class . ">";
				$group = groups_get_group( array( 'group_id' => $event->group_id ) );
				$url   = bp_get_group_permalink( $group ) . 'calendar/event/' . $event->id . '/';
				$events_list .= stripslashes( $event->name ) . '<br /><a href="' . $url . '" title="' . __( 'Ereignis anzeigen', 'groupcalendar' ) . '">' . bgc_date_display( $event->event_time, false, $event->event_all_day ) . ': ' . stripslashes( $event->event_title ) . '</a>';
				$events_list .= "</li>";
			}
			echo $events_list;
			echo "\n</ul>";

		} else {
			?>
			<div class="widget-error">
				<?php _e( 'Es gibt keine bevorstehenden Veranstaltungen für Ihre Gruppen.', 'groupcalendar' ) ?>
			</div>
		<?php } ?>

		<?php echo $after_widget; ?>
		<?php
	}

	function update( $new_instance, $old_instance ) {
		$instance               = $old_instance;
		$instance['title']      = strip_tags( $new_instance['title'] );
		$instance['num_events'] = strip_tags( $new_instance['num_events'] );

		return $instance;
	}

	function form( $instance ) {
		$instance   = wp_parse_args( (array) $instance, array(
			'title'      => __( 'Deine bevorstehenden Gruppenveranstaltungen', 'groupcalendar' ),
			'num_events' => 10
		) );
		$title      = strip_tags( $instance['title'] );
		$num_events = strip_tags( $instance['num_events'] );
		?>
		<p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Titel:', 'groupcalendar' ) ?> <input
					class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>"
					name="<?php echo $this->get_field_name( 'title' ); ?>" type="text"
					value="<?php echo esc_attr( $title ); ?>"/></label></p>
		<p><label
				for="<?php echo $this->get_field_id( 'num_events' ); ?>"><?php _e( 'Anzahl der Ereignisse:', 'groupcalendar' ) ?>
				<input class="widefat" id="<?php echo $this->get_field_id( 'num_events' ); ?>"
				       name="<?php echo $this->get_field_name( 'num_events' ); ?>" type="text"
				       value="<?php echo esc_attr( $num_events ); ?>" style="width: 30%"/></label></p>
		<?php
	}
}