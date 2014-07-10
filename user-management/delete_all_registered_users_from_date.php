<?php
/**
 * Deletes all users that registered after some date.
 * The script shold be placed into Elgg ROOT and ran with CLI.
 * Provide timestamp to be used when deleting users as parameter.
 */

$timezone = 'Europe/Tallinn';

if (!(php_sapi_name() === 'cli')) {
    exit("This script is only allowed to run in CLI mode.\n");
}

// Setting timzone as CLI script needs that
date_default_timezone_set($timezone);

if (!($argv && is_array($argv) && sizeof($argv) > 1)) {
    exit("Timestamp value not provided. Timestamp should be passed as a parameter to the script.\n");
} else {
    $timestamp = $argv[1];
    echo sprintf("All useds wo registered after %s will be deleted.\n", date("c", $timestamp));
}

echo "Take a look at the date you provided and type 'yes' if you want to proceed.\n";
$yes_line = fgets(STDIN);
if (trim($yes_line) !== 'yes') {
    exit("Execution aborted.\n");
}

/**
 * Defines
 *
 * $batch size is configurable
 */
$batch_size = 1000;
$deleted_count = 0;
$undeleted_count = 0;
$undeleted_users = array();

/**
 * Recursively deletes users in batches.
 * Uses global variabled defined within the script.
 */
function __delete_users_batch() {
    global $options;
    global $count;
    global $deleted_count;
    global $undeleted_count;
    global $undeleted_users;

    $users  = elgg_get_entities($options);

    if ($users) {
        foreach ($users as $user) {
            if ($user->delete()) {
                $deleted_count++;
            } else {
                $undeleted_count++;
                $undeleted_users[$user->username] = $user->name;
            }
        }

        if ($count > ($deleted_count + $undeleted_count)) {
            __delete_users_batch();
        }
    }
}

// Load engine
require_once(dirname(__FILE__) . "/engine/start.php");

$ia = elgg_set_ignore_access(TRUE);

$options = array(
    'type' => 'user',
    'wheres' => array(sprintf("e.time_created > %d", $timestamp)),
    'limit' => $batch_size,
    'offset' => 0,
    'count' => TRUE,
);
$count = elgg_get_entities($options);

if (!$count) {
    elgg_set_ignore_access($ia);

    exit("There are users to delete that would suit the provided timestamp condition.\n");
}

$options['count'] = FALSE;

__delete_users_batch();

elgg_set_ignore_access($ia);

echo sprintf("Deleted %d of %d found suitable users.\n", $deleted_count, $count);
if ($undeleted_count > 0) {
    echo sprintf("%d users could not be deleted for some reason:\n", $undeleted_count);
    foreach ($undeleted_users as $und_username => $und_name) {
        echo sprintf("%s : %s\n", $und_username, $und_name);
    }
}
exit;

