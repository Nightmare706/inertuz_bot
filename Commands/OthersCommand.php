<?php

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Request;

use Longman\TelegramBot\Commands\SystemCommands\StartCommand;

class OthersCommand extends UserCommand
{
    protected $name = 'others';
    protected $description = 'Прочее.';
    protected $usage = '/others';
    protected $version = '1.0.0';

    public function execute()
    {
        $message = $this->getMessage();
        $chat_id = $message->getChat()->getId();
        $user_id = $message->getFrom()->getId();
        $text    = trim($message->getText(true));
        
        $lang_id = StartCommand::getLanguage($user_id);

        $keyboard = StartCommand::getOthersKeyboard($lang_id);
		
        if($text == 'contacts'){
            $sendtext = '207-08-09' . "\n";
            $sendtext .= 'info@bts.uz' . "\n";
            $sendtext .= 'www.bts.uz';
        }
        else{
            $sendtext = self::t($lang_id, 'choose_action');
        }
		
		
        
        $data = [
            'chat_id'      => $chat_id,
            'reply_markup' => $keyboard,
            'text'         => $sendtext,
        ];

        return Request::sendMessage($data);
    }
}