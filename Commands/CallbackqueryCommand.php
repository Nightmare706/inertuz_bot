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
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Commands\UserCommands\ProductCommand;
use Longman\TelegramBot\Commands\UserCommands\ContactCommand;
use Longman\TelegramBot\Commands\UserCommands\TrackingCommand;

/**
 * Callback query command
 *
 * This command handles all callback queries sent via inline keyboard buttons.
 *
 * @see InlinekeyboardCommand.php
 */
class CallbackqueryCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'callbackquery';

    /**
     * @var string
     */
    protected $description = 'Reply to callback query';

    /**
     * @var string
     */
    protected $version = '1.1.1';

    /**
     * Command execute method
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function execute()
    {
        
		$update         	= $this->getUpdate();
        $callback_query    	= $this->getCallbackQuery();
        $callback_query_id 	= $callback_query->getId();
        $callback_data     	= $callback_query->getData();
		
        $message = $callback_query->getMessage();
        $message_id = $message->getMessageId();
        $chat_id = $message->getChat()->getId();

		
		// Only do something for the 'product_' selection.
        if (strpos($callback_data, 'product_') !== false) {
            
            $product_id = (int)substr($callback_data, mb_strlen('product_'));
            $update = $update->getRawData();
            $update['callback_query']['message']['text'] = '/product ' . $product_id;
            return (new ProductCommand($this->telegram, new Update($update)))->preExecute();
        }
        elseif (strpos($callback_data, 'region_') !== false) {
            
            $region_id = (int)substr($callback_data, mb_strlen('region_'));
            if(is_numeric($region_id)){
                $update = $update->getRawData();
                $update['callback_query']['message']['text'] = '/contact ' . $callback_data;
                return (new ContactCommand($this->telegram, new Update($update)))->preExecute();
            }
        }
        elseif (strpos($callback_data, 'barcode_') !== false) {
			
			$barcode = substr($callback_data, mb_strlen('barcode_'));
            $update = $update->getRawData();
            $update['callback_query']['message']['text'] = $barcode;
            return (new TrackingCommand($this->telegram, new Update($update)))->preExecute();
		}
		
		
		return Request::emptyResponse();
		
        // $data = [
            // 'callback_query_id' => $callback_query_id,
            // 'text'              => 'Hello World!',
            // 'show_alert'        => $callback_data === 'thumb up',
            // 'cache_time'        => 5,
        // ];
		
		// return Request::answerCallbackQuery($data);
		
		

        
    }
}
