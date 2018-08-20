<?php

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Request;

use Longman\TelegramBot\Commands\SystemCommands\StartCommand;

class InvoiceCommand extends UserCommand
{
    protected $name = 'contact';
    protected $description = 'Контакты.';
    protected $usage = '/contact';
    protected $version = '1.0.0';

    public function execute()
    {
        $message = $this->getMessage();
        $chat_id = $message->getChat()->getId();
        $user_id = $message->getFrom()->getId();
        $text    = trim($message->getText(true));
        
        $lang_id = StartCommand::getLanguage($user_id);
		
		$text = self::t($lang_id, 'invoice') . "\n";
        $text .= 'Invoice Test' . "\n";
		
        
        $data = [
            'chat_id'      => $this->getMessage()->getChat()->getId(),
            'text'         => $text,
        ];

        return Request::sendMessage($data);
    }
}