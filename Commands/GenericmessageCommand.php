<?php
/**
 * This file is part of the TelegramBot package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Request;

use Longman\TelegramBot\Commands\SystemCommands\StartCommand;
use Longman\TelegramBot\Commands\UserCommands\ProductCommand;
use Longman\TelegramBot\Commands\UserCommands\CartCommand;
use Longman\TelegramBot\Commands\UserCommands\OrderCommand;
use Longman\TelegramBot\Commands\UserCommands\DrugstoresCommand;
use Longman\TelegramBot\Commands\UserCommands\FreeConsultingCommand;
use Longman\TelegramBot\Commands\UserCommands\PromotionCommand;
use Longman\TelegramBot\Commands\UserCommands\ContactCommand;
use Longman\TelegramBot\Commands\UserCommands\SettingsCommand;
use Longman\TelegramBot\Commands\UserCommands\OthersCommand;
use Longman\TelegramBot\Commands\UserCommands\FeedbackCommand;
use Longman\TelegramBot\Commands\UserCommands\TrackingCommand;
use Longman\TelegramBot\Commands\UserCommands\MailCommand;
use Longman\TelegramBot\Commands\UserCommands\NewsCommand;

/**
 * Generic message command
 *
 * Gets executed when any type of message is sent.
 */
class GenericmessageCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'genericmessage';

    /**
     * @var string
     */
    protected $description = 'Handle generic message';

    /**
     * @var string
     */
    protected $version = '1.1.0';

    /**
     * @var bool
     */
    protected $need_mysql = true;

    /**
     * Command execute method if MySQL is required but not available
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function executeNoDb()
    {
        // Do nothing
        return Request::emptyResponse();
    }

    /**
     * Command execute method
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function execute()
    {
        $message    = $this->getMessage();
        $message_id = $message->getMessageId();
        $chat_id    = $message->getChat()->getId();
        $text       = trim($message->getText(true));
		$from       = $message->getFrom();
        $user_id    = $from->getId();

        $lang_id = StartCommand::getLanguage($user_id);
        
        $update = json_decode($this->update->toJson(), true);
        

        if($text === self::t($lang_id, 'button_main_page')){
            $update['message']['text'] = '/start';
            return (new StartCommand($this->telegram, new Update($update)))->preExecute();
        }
        elseif($text === self::t($lang_id, 'set_russian')){
            $update['message']['text'] = '/start set_language_1';
            return (new StartCommand($this->telegram, new Update($update)))->preExecute();
        }
        elseif($text === self::t($lang_id, 'set_uzbek')){
            $update['message']['text'] = '/start set_language_2';
            return (new StartCommand($this->telegram, new Update($update)))->preExecute();
        }
        elseif($text === self::t($lang_id, 'button_change_language')){
            $update['message']['text'] = '/start choose_language';
            return (new StartCommand($this->telegram, new Update($update)))->preExecute();
        }
        elseif($text === self::t($lang_id, 'button_change_phone')){
            $update['message']['text'] = '/start change_phone_number';
            return (new StartCommand($this->telegram, new Update($update)))->preExecute();
        }
        elseif($text === self::t($lang_id, 'catalogue')){
            $update['message']['text'] = '/product';
            return (new ProductCommand($this->telegram, new Update($update)))->preExecute();
        }
        elseif($text === self::t($lang_id, 'button_settings')){
            $update['message']['text'] = '/settings';
            return (new SettingsCommand($this->telegram, new Update($update)))->preExecute();
        }
        elseif($text === self::t($lang_id, 'button_others')){
            $update['message']['text'] = '/others';
            return (new OthersCommand($this->telegram, new Update($update)))->preExecute();
        }
        elseif($text === self::t($lang_id, 'button_view_contacts')){
            $update['message']['text'] = '/others contacts';
            return (new OthersCommand($this->telegram, new Update($update)))->preExecute();
        }
        elseif($text === self::t($lang_id, 'cart')){
            $update['message']['text'] = '/cart';
            return (new CartCommand($this->telegram, new Update($update)))->preExecute();
        }
        elseif($text === self::t($lang_id, 'clear_cart')){
            $update['message']['text'] = '/cart clean';
            return (new CartCommand($this->telegram, new Update($update)))->preExecute();
        }
        elseif($text === self::t($lang_id, 'submit_order')){
            $update['message']['text'] = '/order';
            return (new OrderCommand($this->telegram, new Update($update)))->preExecute();
        }
        elseif($text === self::t($lang_id, 'orders')){
            $update['message']['text'] = '/order view_order_history';
            return (new OrderCommand($this->telegram, new Update($update)))->preExecute();
        }
        elseif($text === self::t($lang_id, 'promotion')){
            $update['message']['text'] = '/promotion';
            return (new PromotionCommand($this->telegram, new Update($update)))->preExecute();
        }
        elseif($text === self::t($lang_id, 'button_contacts')){
            $update['message']['text'] = '/contact';
            return (new ContactCommand($this->telegram, new Update($update)))->preExecute();
        }
        elseif($text === self::t($lang_id, 'button_feedback')){
            $update['message']['text'] = '/feedback';
            return (new FeedbackCommand($this->telegram, new Update($update)))->preExecute();
        }
        elseif($text === self::t($lang_id, 'button_tracking')){
            $update['message']['text'] = '/tracking';
            return (new TrackingCommand($this->telegram, new Update($update)))->preExecute();
        }
        elseif($text === self::t($lang_id, 'button_local_mail')){
            $update['message']['text'] = '/mail local';
            return (new MailCommand($this->telegram, new Update($update)))->preExecute();
        }
        elseif($text === self::t($lang_id, 'button_international_mail')){
            $update['message']['text'] = '/mail international';
            return (new MailCommand($this->telegram, new Update($update)))->preExecute();
        }
        elseif($text === self::t($lang_id, 'button_news')){
            $update['message']['text'] = '/news';
            return (new NewsCommand($this->telegram, new Update($update)))->preExecute();
        }

        //save contacts
        if($message->getContact() != null){
            $update['message']['text'] = '/start set_contact';
            return (new StartCommand($this->telegram, new Update($update)))->preExecute();
        }

        //If a conversation is busy, execute the conversation command after handling the message
        $conversation = new Conversation(
            $this->getMessage()->getFrom()->getId(),
            $this->getMessage()->getChat()->getId()
        );

        //Fetch conversation command if it exists and execute it
        if ($conversation->exists() && ($command = $conversation->getCommand())) {
            return $this->telegram->executeCommand($command);
        }

        return Request::emptyResponse();

    }
    // public function execute()
    // {
    //     //If a conversation is busy, execute the conversation command after handling the message
    //     $conversation = new Conversation(
    //         $this->getMessage()->getFrom()->getId(),
    //         $this->getMessage()->getChat()->getId()
    //     );

    //     //Fetch conversation command if it exists and execute it
    //     if ($conversation->exists() && ($command = $conversation->getCommand())) {
    //         return $this->telegram->executeCommand($command);
    //     }

    //     return Request::emptyResponse();
    // }
}
