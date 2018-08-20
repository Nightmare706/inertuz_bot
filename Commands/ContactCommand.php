<?php

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Conversation;

use Longman\TelegramBot\Commands\SystemCommands\StartCommand;

class ContactCommand extends UserCommand
{
    protected $name = 'contact';
    protected $description = 'Контакты.';
    protected $usage = '/contact';
    protected $version = '1.0.0';

    public function execute()
    {
        if($callback_query = $this->getCallbackQuery()){
            $message = $callback_query->getMessage();
            //todo: check callback query from bot changing from_id to chat_id for conversation
            $user    = $message->getChat();
            //$user    = $message->getFrom();
        }
        else{
            $message = $this->getMessage();
            $user    = $message->getFrom();
        }

        $chat    = $message->getChat();
        $chat_id = $chat->getId();
        $user_id = $user->getId();
        $text    = trim($message->getText(true));
        
        $lang_id = StartCommand::getLanguage($user_id);
		if(strpos($text, 'region_') !== false) {
            
            $region_id = (int)substr($text, mb_strlen('region_'));

            $sendtext = self::getRegionText($region_id, $lang_id);
            $keyboard = StartCommand::getKeyboard($lang_id);
        }
        else{
            $sendtext = self::t($lang_id, 'contact_page_text') . "\n";
            $keyboard = self::getInlineKeyboard($lang_id);
        }
		
		
        
        $data = [
            'chat_id'      => $chat_id,
            'reply_markup' => $keyboard,
            'text'         => $sendtext,
        ];

        return Request::sendMessage($data);
    }

    public static function getInlineKeyboard($lang_id)
    {
        $keyboard_buttons = [];
        $regions = self::getRegions($lang_id);
        foreach ($regions as $value) {
            $keyboard_buttons[] = [new InlineKeyboardButton(
                [
                    'text'          => $value['content'],
                    'callback_data' => 'region_' . $value['id'],
                ]
            )];
        }
        
        
        if(version_compare(PHP_VERSION, '5.6.0', '>=')){
            $keyboard = new InlineKeyboard(...$keyboard_buttons);
        } else {
            $reflect  = new \ReflectionClass('Longman\TelegramBot\Entities\InlineKeyboard');
            $keyboard = $reflect->newInstanceArgs($keyboard_buttons);
        }
        
        
        return $keyboard;
    }

    public static function getRegionText($id, $lang_id)
    {
    	$region = self::getRegion($id, $lang_id);
    	$regionText = strtoupper($region['content']) . "\n";
    	$offices  = self::getOffices($id, $lang_id);
    	if($offices){
    		foreach($offices as $value){
    			$value['content'] = json_decode($value['content'], true);
    			foreach ($value['content'] as $key1 => $value1) {
    				$regionText .= self::t($lang_id, $key1) . ": " . $value1 . "\n";
    			}
    		}
    		$regionText .= "\n";
	    		
    	}
        return $regionText;
    }

    public static function getOffices($id, $lang_id)
    {
    	//$lang_id = 1;
    	$pdo = DB::getPdo();
        $items = [];
        $getItems = $pdo->query("SELECT * FROM " . TB_INFORMATION . " WHERE parent_id = " . (int)$id . " AND language_id = " . (int)$lang_id . " AND type = 'office' ORDER BY sort_number");
        if($getItems->rowCount() > 0){
            $items = $getItems->fetchAll();
        }
        return $items;
    }

    public static function getRegions($lang_id)
    {
    	//$lang_id = 1;
    	$pdo = DB::getPdo();
        $items = [];
        $getItems = $pdo->query("SELECT * FROM " . TB_INFORMATION . " WHERE type = 'region' AND language_id = " . (int)$lang_id . " ORDER BY sort_number");
        if($getItems->rowCount() > 0){
            $items = $getItems->fetchAll();
        }
        // foreach ($items as $key => $value) {
        // 	$items[$key]['content'] = json_decode($value['content'], true);
        // }
        return $items;
    }

    public static function getRegion($id, $lang_id)
    {
    	//$lang_id = 1;
    	$pdo = DB::getPdo();
        $item = [];
        $getItem = $pdo->query("SELECT * FROM " . TB_INFORMATION . " WHERE type = 'region' AND language_id = " . (int)$lang_id . " AND id = " . (int)$id);
        if($getItem->rowCount() > 0){
            $item = $getItem->fetch();
        }
        return $item;
    }
}