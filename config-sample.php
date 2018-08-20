<?php

defined('BASEPATH') OR exit('No direct script access allowed');

return [
    'sitename' => 'Bts.uz Bot',
    'timezone' => 'Asia/Tashkent',
    'db' => [
        'host' 		=> 'localhost',
        'user'      => 'root',
        'password'  => '',
        'database' 	=> 'dbname',
        'prefix' 	=> 'tgbot_',
    ],		
    'bot_username' => '', //telegram bot username
    'bot_api_key' => '', //telegram bot api key
    'commands_paths' => [
        BASEPATH . '/Commands',
    ],
    'admin_users' => [
        11120017,
    ],
    'store_manager_id' => [
        11120017,
    ],
    'sales_email' => 'info@domain.uz',
    'robot_email' => 'no-reply@domain.uz',
    'botan_token' => '',
];