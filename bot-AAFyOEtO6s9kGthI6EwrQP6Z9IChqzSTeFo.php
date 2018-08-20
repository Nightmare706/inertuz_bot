<?php

/**
 * Bts.uz Bot 1.0.0
 *
 * Bts.uz telegram bot
 *
 * Copyright (c) 2017, U Y
 *
 *
*/

define('ENVIRONMENT', 'dev'); // dev, prod

if(ENVIRONMENT == 'dev'){
	ini_set('display_errors', 1);
	error_reporting(E_ALL);
}
else{
	ini_set('display_errors', 0);
	error_reporting(0);
}

$serverName = 'http' . ((isset($_SERVER['HTTPS'])) ? 's' : '') . '://' . $_SERVER['SERVER_NAME'];
define('BASEPATH', __DIR__);
//define('BASEURL', $serverName . '/btsuzbot');
define('BASEURL', $serverName);

require BASEPATH . '/vendor/autoload.php';

$config = require BASEPATH . '/config.php';

mb_internal_encoding("UTF-8");
date_default_timezone_set($config['timezone']);

$bot_api_key  = $config['bot_api_key'];
$bot_username  = $config['bot_username'];

// Define all paths for your custom commands in this array (leave as empty array if not used)
$commands_paths = $config['commands_paths'];
// Define all IDs of admin users in this array (leave as empty array if not used)
$admin_users = $config['admin_users'];
// Enter your MySQL database credentials
$mysql_credentials = $config['db'];

$botan_token = $config['botan_token'];

try {
    // Create Telegram API object
    $telegram = new Longman\TelegramBot\Telegram($bot_api_key, $bot_username);
    // Add commands paths containing your custom commands
    $telegram->addCommandsPaths($commands_paths);
    // Enable admin users
    $telegram->enableAdmins($admin_users);

    


    // Enable MySQL
    $telegram->enableMySql($mysql_credentials, $config['db']['prefix']);
    // Logging (Error, Debug and Raw Updates)
    Longman\TelegramBot\TelegramLog::initErrorLog(BASEPATH . "/logs/{$bot_username}_error.log");
    //Longman\TelegramBot\TelegramLog::initDebugLog(BASEPATH . "/logs/{$bot_username}_debug.log");
    //Longman\TelegramBot\TelegramLog::initUpdateLog(BASEPATH . "/logs/{$bot_username}_update.log");
    // If you are using a custom Monolog instance for logging, use this instead of the above
    //Longman\TelegramBot\TelegramLog::initialize($your_external_monolog_instance);
    // Set custom Upload and Download paths
    $telegram->setDownloadPath(BASEPATH . '/Download');
    $telegram->setUploadPath(BASEPATH . '/Upload');
    // Here you can set some command specific parameters
    // e.g. Google geocode/timezone api key for /date command
    //$telegram->setCommandConfig('date', ['google_api_key' => 'your_google_api_key_here']);
    $telegram->setCommandConfig('weather', ['owm_api_key' => '389dd893796f5b46e8ad03fcfbbd7b46']);
    $telegram->setCommandConfig('order', ['store_manager_id' => $config['store_manager_id'], 'sales_email' => $config['sales_email'], 'robot_email' => $config['robot_email'], 'sitename' => $config['sitename']]);
    $telegram->setCommandConfig('feedback', ['store_manager_id' => $config['store_manager_id'], 'sales_email' => $config['sales_email'], 'robot_email' => $config['robot_email'], 'sitename' => $config['sitename']]);
    $telegram->setCommandConfig('mail', ['store_manager_id' => $config['store_manager_id'], 'sales_email' => $config['sales_email'], 'robot_email' => $config['robot_email'], 'sitename' => $config['sitename']]);
    // Botan.io integration
    //$telegram->enableBotan($botan_token);
    // Requests Limiter (tries to prevent reaching Telegram API limits)
    $telegram->enableLimiter();
    // Handle telegram webhook request
    $telegram->handle();
    
} catch (Longman\TelegramBot\Exception\TelegramException $e) {
    // Silence is golden!
    //echo $e;
    // Log telegram errors
    Longman\TelegramBot\TelegramLog::error($e);
} catch (Longman\TelegramBot\Exception\TelegramLogException $e) {
    // Silence is golden!
    // Uncomment this to catch log initialisation errors
    //echo $e;
    Longman\TelegramBot\TelegramLog::error($e);
}

