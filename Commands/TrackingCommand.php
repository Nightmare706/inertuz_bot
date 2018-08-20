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
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\PhotoSize;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\DB;

use Longman\TelegramBot\Commands\SystemCommands\StartCommand;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * User "/survey" command
 *
 * Command that demonstrated the Conversation funtionality in form of a simple survey.
 */
class TrackingCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'tracking';

    /**
     * @var string
     */
    protected $description = 'Tracking';

    /**
     * @var string
     */
    protected $usage = '/tracking';

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
     * api address
     */
    public $apiURL = 'http://api.bts.uz:8080/index.php';

    /**
     * Command execute method
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
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

        //Preparing Response
        $data = [
            'chat_id' => $chat_id,
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

        //cache data from the tracking session if any
        $state = 0;
        if (isset($notes['state'])) {
            $state = $notes['state'];
        }
        if ($text == self::t($lang_id, 'phone_number')) {
            $state = $notes['state'] = 1;
            $notes['type'] = self::t($lang_id, 'phone_number');
            $this->conversation->update();
            $text = '';
        }
        if ($text == self::t($lang_id, 'barcode')) {
            $state = $notes['state'] = 1;
            $notes['type'] = self::t($lang_id, 'barcode');
            $this->conversation->update();
            $text = '';
        }

        /*
        1. Order list: http://api.bts.uz:8080/index.php?r=order/list&phone=+998 98 3054031
        2. Order list by offset and limit: http://api.bts.uz:8080/index.php?r=order/list&phone=+998 98 3054031&limit=5&offset=5
        3. Order detail: http://api.bts.uz:8080/index.php?r=order/detail&barcode=90000019214&phone=+998983054031
        */

        //State machine
        //Entrypoint of the machine state if given by the track
        //Every time a step is achieved the track is updated
        //get order details switch
        switch ($state) {
            case 0:
                if ($text === '' && $text != self::t($lang_id, 'phone_number') && $notes['type'] != self::t($lang_id, 'barcode')) {
                    $notes['state'] = 0;
                    $this->conversation->update();

                    $user = StartCommand::getUser($user_id);
                    if($user['phone'] != ''){
                    	$data['text'] = self::t($lang_id, 'choose_action');
                    	$keyboard = self::getKeyboard($lang_id);
                    }
                    else{
                        $data['text'] .= "\n" . self::t($lang_id, 'share_your_phone_number');
                        $keyboard = StartCommand::getContactKeyboard($lang_id);
                    }
                    $data['reply_markup'] = $keyboard;

                    $result = Request::sendMessage($data);
                    break;
                }

                $notes['type'] = $text;
                $text = '';

            // no break
            case 1:
            	if($notes['type'] == self::t($lang_id, 'phone_number')){

                    $data['text'] = self::t($lang_id, 'tracking_information');
                    $user = StartCommand::getUser($user_id);

                    $data['text'] .= ': ' . $user['phone'] . "\n";

                    //send request
                    try {
						$client = new \GuzzleHttp\Client(['base_uri' => $this->apiURL]);
						$res = $client->request('GET', $this->apiURL . '?r=order/list&phone=' . $user['phone'] . '&limit=20');
                    	// $res = $client->request('GET', '?r=order/list&phone=+998983054031&limit=50');
                    	$body = $res->getBody();
                    	$statusCode = $res->getStatusCode();
                    	$items = json_decode($body);
                        $keyboard = self::getBarcodesKeyboard($items);
                        
					} catch (\GuzzleHttp\Exception\ClientException $e) {
						//$res = $e->getRequest();
						//$statusCode = $res->getStatusCode();
						$statusCode = $e->getResponse()->getStatusCode();
						$keyboard = self::getKeyboard($lang_id);
					}
					if($statusCode == 404){
						$data['text'] .= self::t($lang_id, 'information_not_found');
					}
                    
                    $data['reply_markup'] = $keyboard;

                    $result = Request::sendMessage($data);
                }
                $text = '';

            // no break
            case 2:
                $notes['state'] = 2;
                $this->conversation->update();
                if ($text === '') {
                    $data['text'] = self::t($lang_id, 'enter_barcode');
                    $data['reply_markup'] = self::getKeyboard($lang_id);
                    //$data['reply_markup'] = StartCommand::getKeyboard($lang_id);
                    $result = Request::sendMessage($data);
                    break;
                }
                $notes['barcode'] = $text;

            case 3:
                if(!empty($notes['barcode']) || !empty($text)){
                	if($text !== ''){
                		$notes['barcode'] = $text;
                		$this->conversation->update();
                	}

                	$sendMessage = '*' . self::t($lang_id, 'tracking_information') . ': ' . $notes['barcode'] . '*' . "\n\n";
					$barcodeInfoFound = false;

					try {
						$user = StartCommand::getUser($user_id);

						$client = new \GuzzleHttp\Client(['base_uri' => $this->apiURL]);
						$res = $client->request('GET', '?r=order/detail&barcode=' . $notes['barcode'] . '&phone=' . $user['phone']);
                    	// $res = $client->request('GET', '?r=order/detail&barcode=' . $notes['barcode'] . '&phone=+998983054031');
                    	
                    	$body = $res->getBody();
                    	$statusCode = $res->getStatusCode();

                    	$item = json_decode($body);

                    	if($item){
                    		$barcodeInfoFound = true;
                    		$sendMessage .= self::t($lang_id, 'package') . ': ' . $item->package->name . "\n";
			                $sendMessage .= self::t($lang_id, 'status') . ': ' . $item->status->info . "\n\n";
			                $sendMessage .= self::t($lang_id, 'sender') . ': ' . (!empty($item->senderRegion->name) ? $item->senderRegion->name . ', ' : '') . (!empty($item->senderCity->name) ? $item->senderCity->name . ', ' : '') . (!empty($item->senderAddress) ? $item->senderAddress : '') . "\n";
			                $sendMessage .= $item->senderPhone . "\n\n";
			                $sendMessage .= self::t($lang_id, 'receiver') . ': ' . (!empty($item->receiverRegion->name) ? $item->receiverRegion->name . ', ' : '') . (!empty($item->receiverCity->name) ? $item->receiverCity->name . ', ' : '') . (!empty($item->receiverAddress) ? $item->receiverAddress : '') . "\n";
			                $sendMessage .= $item->receiver . "\n";
			                $sendMessage .= $item->receiverPhone . "\n\n";

			                $sendMessage .= self::t($lang_id, 'sender_date') . ': ' . $item->senderDate . "\n";
			                $sendMessage .= self::t($lang_id, 'receiver_date') . ': ' . $item->receiverDate . "\n\n";
			                $sendMessage .= self::t($lang_id, 'cost') . ': ' . $item->cost . "\n";
			                $sendMessage .= self::t($lang_id, 'weight') . ': ' . $item->weight . "\n\n";
                    	}
                        
					} catch (\GuzzleHttp\Exception\ClientException $e) {
						$statusCode = $e->getResponse()->getStatusCode();
					}

					if(!$barcodeInfoFound){
						$sendMessage .= self::t($lang_id, 'barcode_not_found');
					}

					$data['reply_markup'] = self::getKeyboard($lang_id);
					$data['parse_mode'] = 'Markdown';
					$data['text'] = $sendMessage;

					//reset barcode
					$notes['barcode'] = '';
		            $this->conversation->update();

					$result = Request::sendMessage($data);
                }

        }

        return $result;
    }

    public static function getKeyboard($lang_id)
    {
        $keyboard = (new Keyboard(
            [self::t($lang_id, 'phone_number'), self::t($lang_id, 'barcode')],
            [self::t($lang_id, 'button_main_page')]
        ))
            ->setOneTimeKeyboard(false)
            ->setResizeKeyboard(true)
            ->setSelective(true);

        return $keyboard;
    } 

    public static function getBarcodesKeyboard($items)
    {
        $keyboard_buttons = [];

        foreach ($items as $value) {
            $keyboard_buttons[] = [new InlineKeyboardButton(
                [
                    'text'          => $value->barcode,
                    'callback_data' => 'barcode_' . $value->barcode,
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
}
