<?php
/**
 * This file is part of the TelegramBot package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\KeyboardButton;
use Longman\TelegramBot\Entities\PhotoSize;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\DB;

use Longman\TelegramBot\Commands\SystemCommands\StartCommand;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * User "/settings" command
 *
 * Command that changes user settings
 */
class SettingsCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'settings';

    /**
     * @var string
     */
    protected $description = 'Настройки пользователя';

    /**
     * @var string
     */
    protected $usage = '/settings';

    /**
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * @var bool
     */
    protected $need_mysql = true;

    /**
     * @var bool
     */
    protected $private_only = true;

    /**
     * Conversation Object
     *
     * @var \Longman\TelegramBot\Conversation
     */
    protected $conversation;

    /**
     * Command execute method
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function execute()
    {
        $message = $this->getMessage();


        $chat    = $message->getChat();
        $user    = $message->getFrom();
        $text    = trim($message->getText(true));
        $chat_id = $chat->getId();
        $user_id = $user->getId();

        $lang_id = StartCommand::getLanguage($user_id);

        //Preparing Response
        $data = [
            'chat_id' => $chat_id,
        ];

        //Conversation start
        $this->conversation = new Conversation($user_id, $chat_id, $this->getName());

        $notes = &$this->conversation->notes;
        !is_array($notes) && $notes = [];

        $result = Request::emptyResponse();
        if ($text === self::t($lang_id, 'button_back') && $notes['state'] > 0) {
            $notes['state']--;
            $text = '';
        }

        //cache data from the tracking session if any
        $state = 0;
        if (isset($notes['state'])) {
            //$state = $notes['state'];
        }

        //State machine
        //Entrypoint of the machine state if given by the track
        //Every time a step is achieved the track is updated
        //get order details switch
        switch ($state) {
            case 0:
                if ( ($text === '') || ($text !== self::t($lang_id, 'button_change_language') && $text !== self::t($lang_id, 'button_change_phone')) ) {
                    $notes['state'] = 0;
                    $this->conversation->update();

                    $data['text'] = self::t($lang_id, 'choose_action');
                    $data['reply_markup'] = (new Keyboard(
                        [self::t($lang_id, 'button_change_language'), self::t($lang_id, 'button_change_phone')],
                        [self::t($lang_id, 'button_main_page')]
                    ))
                        ->setOneTimeKeyboard(false)
                        ->setResizeKeyboard(true)
                        ->setSelective(true);

                    $result = Request::sendMessage($data);
                    break;
                }

                $notes['last_command'] = $text;

                $text = '';

            // no break
            case 1:

                $notes['state'] = 1;
                $this->conversation->update();

                if($notes['last_command'] == self::t($lang_id, 'button_change_language')){
                    $data['text'] = self::t($lang_id, 'choose_language');
                    $data['reply_markup'] = StartCommand::getLanguageKeyboard($lang_id);
                }
                elseif($notes['last_command'] == self::t($lang_id, 'button_change_phone')){
                    $data['text'] = self::t($lang_id, 'choose_language');
                    $data['reply_markup'] = StartCommand::getContactKeyboard($lang_id);
                }
                $result = Request::sendMessage($data);

                $notes['state'] = 0;
                $this->conversation->update();

                $this->conversation->stop();

                break;

        }

        return $result;
    }
}
