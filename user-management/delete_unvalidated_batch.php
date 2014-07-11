<?php
/**
 * This script will delete all unvalidated users with batches.
 * The script will run as long as needed.
 * Usage: place into Elgg ROOT, login as admin and access from browser.
 * REQUIRED Elgg version: 1.8
 * REQUIRED active plugins: uservalidationbyemail
 */

// Require engine
require_once(dirname(__FILE__) . "/engine/start.php");

set_time_limit(0); // Remove max execution time limit

if ( !elgg_is_admin_logged_in() ) {
	die('Administrator privileges required!');
}

// Defines and holders
$batch_size = 1000;
$deleted = 0;
$not_deleted = 0;

// Getting/setting ignore_access and show_hidden_entities
$ia = elgg_set_ignore_access(TRUE);
$hidden_entities = access_get_show_hidden_status();
access_show_hidden_entities(TRUE);

$options = array(
	'type' => 'user',
	'wheres' => uservalidationbyemail_get_unvalidated_users_sql_where(),
	'limit' => 0,
	'offset' => 0,
	'count' => TRUE,
);

$count = elgg_get_entities($options);

if ( !$count ) {
	die('No unvalidaed users to delete!');
}

// Cycling through a number of batches needed to delete all unvalidated users
foreach ( range(1, intval($count / $batch_size) + 1, 1) as $i ) {
	$options['count'] = FALSE;
	$options['limit'] = $batch_size;

	$users = elgg_get_entities($options);

	if ( $users ) {
		foreach ( $users as $user ) {
			if ( !$user instanceof ElggUser ) {
				$not_deleted += 1;
                unset($user); // Free memory
				continue;
			}
			
			$is_validated = elgg_get_user_validation_status($user->getGUID());
			if ($is_validated !== FALSE || !$user->delete()) {
				$not_deleted += 1;
				unset($user); // Free memory
				continue;
			}

			$deleted += 1;
			unset($user); // Free memory
		}
	}
	unset($users); // Free memory
}

// Setting ignore_access and show_hidden_entities to default
access_show_hidden_entities($hidden_entities);
elgg_set_ignore_access($ia);

echo sprintf("Deleted %d unvalidated user out of %d\n", $deleted, $count);
if ( $not_deleted ) {
	echo sprintf("Could no delete %d unvalidated users", $not_deleted);
}

