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
class MailCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'mail';

    /**
     * @var string
     */
    protected $description = 'Почта';

    /**
     * @var string
     */
    protected $usage = '/mail';

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

        $type = '';
        //set language
        if( $text == 'international'){
            $type = 'international';
            $text = '';
        }
        elseif($text == 'local'){
            $type = 'local';
            $text = '';
        }


        //Preparing Response
        $data = [
            'chat_id' => $chat_id,
            'text'    => self::t($lang_id, 'choose_action'),
        ];

        //Conversation start
        $this->conversation = new Conversation($user_id, $chat_id, $this->getName());

        $notes = &$this->conversation->notes;
        !is_array($notes) && $notes = [];

        $result = Request::emptyResponse();
        if ($text === self::t($lang_id, 'button_back')) {
            if($notes['state'] > 0){
                $notes['state']--;
            }
            $text = '';
        }
        if($text == self::t($lang_id, 'button_cancel')) {
            $data['text'] = self::t($lang_id, 'courier_call_cancelled');
            $data['reply_markup'] = self::getKeyboard($notes['type'], $lang_id);

            $notes = array();
            $notes['state'] = 0;
            $this->conversation->update();
            return Request::sendMessage($data);
        }

        //cache data from the tracking session if any
        $state = 0;
        if (isset($notes['state'])) {
            $state = $notes['state'];
        }

        //State machine
        //Entrypoint of the machine state if given by the track
        //Every time a step is achieved the track is updated
        //get order details switch
        switch ($state) {
            case 0:
                $notes['state'] = 0;
                if($type != ''){
                    $notes['type'] = $type;
                }
                if(empty($notes['type'])){
                    $notes['type'] = 'local';
                }
                
                $this->conversation->update();
                if($text == self::t($lang_id, 'button_call_courier') || $text == self::t($lang_id, 'button_prices_' . $notes['type']) || $text == self::t($lang_id, 'button_get_contract')){
                    $notes['last_command'] = $text;
                }
                else{
                    $data['reply_markup'] = self::getKeyboard($notes['type'], $lang_id);
                    break;
                }
                $text = '';

            // no break
            case 1:

                $notes['state'] = 1;
                $this->conversation->update();

                if($notes['last_command'] == self::t($lang_id, 'button_call_courier')){
                    if($text == ''){
                        $data['text'] = self::t($lang_id, 'enter_your_name_and_phone');
                        $buttonText = $user->getFirstName(); 
                        $user = StartCommand::getUser($user_id);
                        if($user['phone'] != ''){
                            $buttonText .= ' ' . $user['phone'];
                        }   
                        $data['reply_markup'] = (new Keyboard(
                            [$buttonText],
                            [self::t($lang_id, 'button_back'), self::t($lang_id, 'button_main_page')]
                        ))
                            ->setOneTimeKeyboard(false)
                            ->setResizeKeyboard(true)
                            ->setSelective(true);
                        break;
                    }
                    else {
                        $notes['name'] = $text;
                        $text = '';
                    }
                }
                elseif($notes['last_command'] == self::t($lang_id, 'button_get_contract')){
                    $data['text'] = self::t($lang_id, 'file_has_been_sent');
                    $data['reply_markup'] = self::getKeyboard($notes['type'], $lang_id);
                    // $data['reply_markup'] = (new Keyboard(
                    //     [self::t($lang_id, 'button_back'), self::t($lang_id, 'button_main_page')]
                    // ))
                    //     ->setOneTimeKeyboard(false)
                    //     ->setResizeKeyboard(true)
                    //     ->setSelective(true);

                    //get file id
                    $docfile = 'https://zipwolf.com/btsuzbot/files/BTS-contract-' . $notes['type'] . '-1.zip';
                    $pdo = DB::getPdo();
                    $getFileId = $pdo->prepare('SELECT file_id FROM ' . TB_FILE . ' WHERE url = :url');
                    $getFileId->execute(array(':url' => $docfile));
                    $sendBefore = false;
                    if($getFileId->rowCount() > 0){
                        $sendBefore = true;
                        $fileId = $getFileId->fetch();
                        $docfile = $fileId['file_id'];
                    }

                    $filedata = [
                        'chat_id' => $chat_id,
                        'document' => $docfile
                    ];
                    $fileResponse = Request::sendDocument($filedata);

                    //add file to db
                    if(!$sendBefore) {
                        if ($fileResponse->isOk()) {
                            $new_file_id = $fileResponse->getResult()->getDocument()->getFileId();
                            $insertFileId = $pdo->prepare('INSERT INTO ' . TB_FILE . ' (file_id, url) VALUES (:file_id, :url)');
                            $insertFileId->execute(array(':file_id' => $new_file_id, ':url' => $docfile));
                        }
                    }

                    $notes['last_command'] = null;
                    $notes['state'] = 0;
                    $this->conversation->update();

                    break;
                }
                elseif($notes['last_command'] == self::t($lang_id, 'button_prices_' . $notes['type'])){
                    $data['text'] = self::t($lang_id, 'info_has_been_sent');
                    $data['reply_markup'] = self::getKeyboard($notes['type'], $lang_id);
                    // $data['reply_markup'] = (new Keyboard(
                    //     [self::t($lang_id, 'button_back'), self::t($lang_id, 'button_main_page')]
                    // ))
                    //     ->setOneTimeKeyboard(false)
                    //     ->setResizeKeyboard(true)
                    //     ->setSelective(true);
                        
                    //get file id
                    $docfile = 'https://zipwolf.com/btsuzbot/files/price-' . $notes['type'] . '-2.jpg';
                    $pdo = DB::getPdo();
                    $getFileId = $pdo->prepare('SELECT file_id FROM ' . TB_FILE . ' WHERE url = :url');
                    $getFileId->execute(array(':url' => $docfile));
                    $sendBefore = false;
                    if($getFileId->rowCount() > 0){
                        $sendBefore = true;
                        $fileId = $getFileId->fetch();
                        $docfile = $fileId['file_id'];
                    }

                    $filedata = [
                        'chat_id' => $chat_id,
                        'photo' => $docfile
                    ];
                    $fileResponse = Request::sendPhoto($filedata);


                    //add file to db
                    if(!$sendBefore) {
                        if ($fileResponse->isOk()) {
                            $new_file_id = $fileResponse->getResult()->getPhoto()[0]->getFileId();
                            $insertFileId = $pdo->prepare('INSERT INTO ' . TB_FILE . ' (file_id, url) VALUES (:file_id, :url)');
                            $insertFileId->execute(array(':file_id' => $new_file_id, ':url' => $docfile));
                        }
                    }

                    $notes['last_command'] = null;
                    $notes['state'] = 0;
                    $this->conversation->update();

                    break;
                }
                else{
                    //error, go to menu
                    $data['reply_markup'] = self::getKeyboard($notes['type'], $lang_id);

                    $notes['state'] = 0;
                    $this->conversation->update();
                    $this->conversation->stop();
                    break;
                }
            //no break;
            case 2:
                if ($text === '' && $message->getLocation() === null) {
                    $notes['state'] = 2;
                    $this->conversation->update();

                    $keyboardArray = [(new KeyboardButton(self::t($lang_id, 'send_location')))->setRequestLocation(true)];
                    $keyboardArray2 = [self::t($lang_id, 'button_back'), self::t($lang_id, 'button_main_page')];
                    $user = StartCommand::getUser($user_id);
                    $data['reply_markup'] = (new Keyboard(
                        $keyboardArray,
                        $keyboardArray2
                    ))
                        ->setOneTimeKeyboard(false)
                        ->setResizeKeyboard(true)
                        ->setSelective(true);

                    $data['text'] = self::t($lang_id, 'enter_your_address_or_send_location');
                    break;
                }
                
                if($message->getLocation() !== null){
                    $notes['address'] = '';
                    $notes['latitude']  = $message->getLocation()->getLatitude();
                    $notes['longitude'] = $message->getLocation()->getLongitude();
                }
                else{
                    $notes['address'] = $text;
                }
                $text = '';
            //no break;
            case 3:
                if ($text === '') {
                    $notes['state'] = 3;
                    $this->conversation->update();

                    $data['reply_markup'] = (new Keyboard(
                        [self::t($lang_id, 'button_back'), self::t($lang_id, 'button_main_page')]
                    ))
                        ->setOneTimeKeyboard(false)
                        ->setResizeKeyboard(true)
                        ->setSelective(true);

                    $data['text'] = self::t($lang_id, 'enter_short_message');
                    break;
                }
                
                $notes['message'] = $text;
                $text = '';
            // no break
            case 4:
                if ($text === '') {
                    $notes['state'] = 4;
                    $this->conversation->update();

                    $message = self::t($lang_id, 'check_message_and_confirm') . "\n";
                    $message .= self::t($lang_id, 'courier_call') . ' (' . self::t($lang_id, $notes['type'] . '_mail') . ')' . "\n";
                    $message .= self::t($lang_id, 'your_name') . ': ' . $notes['name'] . "\n";
                    if(isset($notes['latitude']) && isset($notes['longitude'])){
                        $message .= self::t($lang_id, 'address') . ': ' . self::t($lang_id, 'location') . "\n";
                        Request::sendLocation([
                            'chat_id' => $chat_id,
                            'latitude' => $notes['latitude'],
                            'longitude' => $notes['longitude'],
                        ]);
                    }
                    else{
                        $message .= self::t($lang_id, 'address') . ': ' . $notes['address'] . "\n";
                    }
                    $message .= self::t($lang_id, 'message') . ': ' . $notes['message'];

                    $data['reply_markup'] = (new Keyboard(
                        [self::t($lang_id, 'button_confirm'), self::t($lang_id, 'button_cancel')],
                        [self::t($lang_id, 'button_back'), self::t($lang_id, 'button_main_page')]
                    ))
                        ->setOneTimeKeyboard(false)
                        ->setResizeKeyboard(true)
                        ->setSelective(true);

                    $data['text'] = $message;
                    break;
                }
                elseif($text == self::t($lang_id, 'button_confirm')) {
                    
                    $pdo = DB::getPdo();


                    //send message to manager
                    $manager_id = $this->getConfig('store_manager_id');
                    
                    $getChatId = $pdo->prepare('SELECT chat_id FROM ' . TB_USER_CHAT . ' WHERE user_id = :user_id AND chat_id > 0');
                    
                    $manager_message = self::t($lang_id, 'new_courier_call') . ' (' . self::t($lang_id, $notes['type'] . '_mail') . ')' . "\n";
                    $manager_message .= self::t($lang_id, 'contacter_name') . ': ' . $notes['name'] . "\n";
                    if(isset($notes['latitude']) && isset($notes['longitude'])){
                        $manager_message .= self::t($lang_id, 'address') . ': ' . self::t($lang_id, 'location') . '[' . $notes['latitude'] . ',' . $notes['longitude'] . ']' . "\n";
                    }
                    else{
                        $manager_message .= self::t($lang_id, 'address') . ': ' . $notes['address'] . "\n";
                    }
                    $manager_message .= self::t($lang_id, 'message') . ': ' . $notes['message'];

                    $sendLocation = false;
                    if(isset($notes['latitude']) && isset($notes['longitude'])){
                        $sendLocation = true;
                    }

                    foreach($manager_id as $value){
                        $getChatId->bindParam(':user_id', $value);
                        $getChatId->execute();
                        if($getChatId->rowCount() > 0){
                            $manager_chat_id = $getChatId->fetch()['chat_id'];

                            $manag_result = Request::sendMessage([
                                'chat_id' => $manager_chat_id,
                                'text' => $manager_message
                            ]);

                            //send location to managers
                            if($sendLocation){
                                Request::sendLocation([
                                    'chat_id' => $manager_chat_id,
                                    'latitude' => $notes['latitude'],
                                    'longitude' => $notes['longitude'],
                                ]);
                            }
                        }
                    }
                    

                    //send email to sales
                    $mail = new PHPMailer(true);                              // Passing `true` enables exceptions
                    $sales_email = $this->getConfig('sales_email');
                    $robot_email = $this->getConfig('robot_email');
                    $sitename = $this->getConfig('sitename');
                    try {
                        $mail->CharSet = 'utf-8';
                        //Server settings
                        $mail->SMTPDebug = 2;                                 // Enable verbose debug output
                        $mail->isMail();
                        // $mail->isSMTP();                                      // Set mailer to use SMTP
                        // $mail->Host = 'smtp1.example.com;smtp2.example.com';  // Specify main and backup SMTP servers
                        // $mail->SMTPAuth = true;                               // Enable SMTP authentication
                        // $mail->Username = 'user@example.com';                 // SMTP username
                        // $mail->Password = 'secret';                           // SMTP password
                        // $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
                        // $mail->Port = 587;                                    // TCP port to connect to

                        //Recipients
                        $mail->setFrom($robot_email, $sitename);
                        $mail->addAddress($sales_email);     // Add a recipient

                        //Content
                        $mail->isHTML(true);                                  // Set email format to HTML
                        $mail->Subject = $sitename . ' Вызов курьера';
                        $mail->Body    = $manager_message;
                        $mail->AltBody = $manager_message;

                        $mail->send();
                        //echo 'Message has been sent';
                    } catch (Exception $e) {
                        //echo 'Message could not be sent.';
                        //echo 'Mailer Error: ' . $mail->ErrorInfo;
                    }

                    //confirmed, go to menu
                    $data['text'] = self::t($lang_id, 'courier_call_accepted');
                    $data['reply_markup'] = self::getKeyboard($notes['type'], $lang_id);

                    $notes['state'] = 0;
                    $this->conversation->update();
                    break;
                    
                }
        }

        $result = Request::sendMessage($data);

        return $result;
    }

    public static function getKeyboard($type, $lang_id)
    {
        $keyboard = new Keyboard(
            [self::t($lang_id, 'button_call_courier'), self::t($lang_id, 'button_prices_' . $type)],
            [self::t($lang_id, 'button_get_contract'), self::t($lang_id, 'button_main_page')]
        );

        $keyboard
            ->setResizeKeyboard(true)
            ->setOneTimeKeyboard(false)
            ->setSelective(false);
        return $keyboard;
    }
}