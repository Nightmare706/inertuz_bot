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
use Longman\TelegramBot\Entities\KeyboardButton;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\DB;

/**
 * Start command
 *
 * Gets executed when a user first starts using the bot.
 */

class StartCommand extends SystemCommand
{

    /**
     * @var array
     * 1 - ru
     * 2 - uz
     */
    public static $language_ids = [1, 2];
    /**
     * @var string
     */
    protected $name = 'start';

    /**
     * @var string
     */
    protected $description = 'Start command';

    /**
     * @var string
     */
    protected $usage = '/start';

    /**
     * @var string
     */
    protected $version = '1.1.0';

    /**
     * @var bool
     */
    protected $private_only = true;

    /**
     * Command execute method
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function execute()
    {
        $message = $this->getMessage();
        $chat_id = $message->getChat()->getId();
        $user_id = $message->getFrom()->getId();
        $text    = trim($message->getText(true));


        //set language
        if(stripos($text, 'set_language_') === 0){
            $get_lang_id = explode('_', $text);
            if(isset($get_lang_id[2])){
                self::setLanguage($user_id, $get_lang_id[2]);
            }
        }

        //set contact
        $contactSet = false;
        if(stripos($text, 'set_contact') === 0){
            if($message->getContact() !== null){
                $phoneNumber = $message->getContact()->getPhoneNumber();
                $contactSet = self::setContact($user_id, $phoneNumber);
            }
        }

        $default_lang_id = 1;
        $user = self::getUser($user_id);
        $lang_id = (!empty($user['language_id'])) ? $user['language_id'] : 0;
        
        
        //выбрать язык если не установлен
        if($lang_id == 0 || stripos($text, 'choose_language') === 0){
            $keyboard = self::getLanguageKeyboard($default_lang_id);
            $data = [
                'chat_id'       => $chat_id,
                'text'          => self::t($default_lang_id, 'choose_language'),
                'reply_markup'  => $keyboard,
            ];
            return Request::sendMessage($data);
        }

        //если нету номера телефона - спросить
        if($user['phone'] == '' || stripos($text, 'change_phone_number') === 0){
            $keyboard = self::getContactKeyboard($lang_id);
            $data = [
                'chat_id' => $chat_id,
                'text'    => self::t($lang_id, 'send_your_contacts'),
                'reply_markup' => $keyboard,
            ];
            return Request::sendMessage($data);
        }

        $responseText = '';
        if($contactSet){
            $responseText .= self::t($lang_id, 'phone_number_saved') . "\n";
        }
        //все в порядке - показываем главную страницу
        $responseText   .= self::t($lang_id, 'choose_action');
        $keyboard       = self::getKeyboard($lang_id);       

        $data = [
            'chat_id' => $chat_id,
            'text'    => $responseText,
            'reply_markup' => $keyboard,
        ];

        return Request::sendMessage($data);
    }

    //возвращает пользователя
    public static function getUser($user_id) {
        $user = array();
        $pdo = DB::getPdo();
        $getUser = $pdo->prepare('SELECT * FROM ' . TB_USER . ' WHERE id = :user_id');
        $getUser->bindParam(':user_id', $user_id);
        $getUser->execute();
        if($getUser->rowCount() > 0){
            $user = $getUser->fetch();
        }
        return $user;
    }

    //возвращает id языка, если не найден возвращает 0
    public static function getLanguage($user_id) {
        $lang_id = 0;
        $pdo = DB::getPdo();
        $getLang = $pdo->prepare('SELECT language_id FROM ' . TB_USER . ' WHERE id = :user_id');
        $getLang->bindParam(':user_id', $user_id);
        $getLang->execute();
        if($getLang->rowCount() > 0){
            $lang = $getLang->fetch();
            $lang_id = $lang['language_id'];
            if(!in_array($lang_id, self::$language_ids)){
                $lang_id = 0;
            }
        }
        return $lang_id;
    }

    //обновляет запись в таблице user, возвращает id языка
    public static function setLanguage($user_id, $lang_id) {
        $pdo = DB::getPdo();

        if(!in_array($lang_id, self::$language_ids)){
            $lang_id = 0;
        }
        else{
            $setLang = $pdo->prepare('UPDATE ' . TB_USER . ' SET language_id = :language_id WHERE id = :user_id');
            $setLang->bindParam(':user_id', $user_id);
            $setLang->bindParam(':language_id', $lang_id);
            $setLang->execute();
        }
        return $lang_id;
    }

    //обновляет запись в таблице user, возвращает результат
    public static function setContact($user_id, $phoneNumber) {
        $pdo = DB::getPdo();
        $phoneNumber = preg_replace('#[^\+0-9]#', '', $phoneNumber);
        if(substr($phoneNumber, 0, 1) != '+'){
            $phoneNumber = '+' . $phoneNumber;
        }
        $setContact = $pdo->prepare('UPDATE ' . TB_USER . ' SET phone = :phone WHERE id = :user_id');
        $setContact->bindParam(':user_id', $user_id);
        $setContact->bindParam(':phone', $phoneNumber);
        $result = $setContact->execute();

        return $result;
    }

    public static function getKeyboard($lang_id = 0) {

        $keyboard = new Keyboard(
            [self::t($lang_id, 'button_products'), self::t($lang_id, 'button_cart')],
            [self::t($lang_id, 'button_about'), self::t($lang_id, 'button_others')]
        );


        $keyboard
            ->setResizeKeyboard(true)
            ->setOneTimeKeyboard(false)
            ->setSelective(false);
        return $keyboard;
    }

    public static function getLanguageKeyboard($lang_id) {

        $keyboard = new Keyboard(
            [self::t($lang_id, 'set_uzbek'), self::t($lang_id, 'set_russian')]
        );
        $keyboard
            ->setResizeKeyboard(true)
            ->setOneTimeKeyboard(false)
            ->setSelective(false);
        return $keyboard;
    }

    public static function getContactKeyboard($lang_id) {

        $keyboard = (new Keyboard(
                [(new KeyboardButton(self::t($lang_id, 'send_my_number')))->setRequestContact(true)]
            ))
            ->setResizeKeyboard(true)
            ->setOneTimeKeyboard(false)
            ->setSelective(false);
        return $keyboard;
    }

    public static function getOthersKeyboard($lang_id) {

        $keyboard = new Keyboard(
            [self::t($lang_id, 'button_change_language'), self::t($lang_id, 'button_change_phone')],
            [self::t($lang_id, 'button_news'), self::t($lang_id, 'button_view_contacts')],
            [self::t($lang_id, 'button_main_page')]
        );

        $keyboard
            ->setResizeKeyboard(true)
            ->setOneTimeKeyboard(false)
            ->setSelective(false);
        return $keyboard;
    }
}
