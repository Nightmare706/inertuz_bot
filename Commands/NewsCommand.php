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

class NewsCommand extends UserCommand
{
    protected $name = 'news';
    protected $description = 'Новости';
    protected $usage = '/news';
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
		
		// if(strpos($text, 'newsid_') !== false) {
  //           $newsid = (int)substr($text, mb_strlen('newsid_'));
  //           $sendMessage = self::getNews($newsid, $lang_id);
  //       }
        

        $sendMessage = self::t($lang_id, 'no_news');
		$keyboard = StartCommand::getOthersKeyboard($lang_id);
		
        
        $data = [
            'chat_id'      => $chat_id,
            'reply_markup' => $keyboard,
            'text'         => $sendMessage,
        ];

        return Request::sendMessage($data);
    }
}
