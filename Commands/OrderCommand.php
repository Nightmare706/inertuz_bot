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
 * User "/survey" command
 *
 * Command that demonstrated the Conversation funtionality in form of a simple survey.
 */
class OrderCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'order';

    /**
     * @var string
     */
    protected $description = 'Список заказов';

    /**
     * @var string
     */
    protected $usage = '/order';

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


    public function getOrderItems($user_id) {
        $products = [];
        $pdo = DB::getPdo();
        $getCartId = $pdo->prepare('SELECT * FROM ' . TB_STORE_CART . ' WHERE user_id = :user_id');
        $getCartId->bindParam(':user_id', $user_id);
        $getCartId->execute();
        if($getCartId->rowCount() > 0){
            $cart = $getCartId->fetch();
            $cart_id = $cart['id'];
            //check item exists in cart
            $getCartItems = $pdo->prepare('SELECT * FROM ' . TB_STORE_CART_ITEM . ' WHERE cart_id = :cart_id');
            $getCartItems->bindParam(':cart_id', $cart_id);
            $getCartItems->execute();
            if($getCartItems->rowCount() > 0){
                $cartItems = $getCartItems->fetchAll();
                $getProduct = $pdo->prepare('SELECT * FROM ' . TB_STORE_PRODUCT . ' WHERE id = :id');
                foreach($cartItems as $key => $value){
                    $getProduct->bindParam(':id', $value['product_id']);
                    $getProduct->execute();
                    if($getProduct->rowCount() > 0){
                        $cartProduct = $getProduct->fetch();
                        $cartProduct['quantity'] = $value['quantity'];
                        $products[] = $cartProduct;
                    }
                }
            }
        }
        return $products;
    }
	//self::t($lang_id, 
    public function getOrdersHistoryText($user_id, $lang_id = 0) {
        $orderText = '';
        $pdo = DB::getPdo();
        $getOrder = $pdo->prepare('SELECT * FROM ' . TB_STORE_ORDER . ' WHERE user_id = :user_id ORDER BY id DESC LIMIT 5');
        $getOrder->bindParam(':user_id', $user_id);
        $getOrder->execute();
        
        if($getOrder->rowCount() > 0){
            $orders = $getOrder->fetchAll();
            foreach($orders as $value){
                $orderText .= "*" . self::t($lang_id, 'order') . " №" . $value['id'] . "*\n";
                
                $getOrderItems = $pdo->prepare('SELECT * FROM ' . TB_STORE_ORDER_ITEM . ' WHERE order_id = :order_id');
                $getOrderItems->bindParam(':order_id', $value['id']);
                $getOrderItems->execute();
                if($getOrderItems->rowCount() > 0){
                    $orderItems = $getOrderItems->fetchAll();
                    foreach($orderItems as $value1){
                        $orderText .= "\t" . $value1['title'] . ' - ' . $value1['quantity'] . self::t($lang_id, 'kg') . "\n";
                    }   
                }
                $orderText .= self::t($lang_id, 'order_status') . ": " . ( ($value['status'] == 1) ? self::t($lang_id, 'pending')  :  self::t($lang_id, 'completed') ) . "\n";
                $orderText .= self::t($lang_id, 'sum') . ": " . number_format($value['total']) . self::t($lang_id, 'price_currency') . "\n";

                $orderText .= self::t($lang_id, 'receiver_name') . ": " . $value['receiver_name'] . "\n";
                $orderText .= self::t($lang_id, 'shipping_method') . ": " . $value['shipping_method'] . "\n";
                $orderText .= self::t($lang_id, 'payment_method') . ": " . $value['payment_method'] . "\n";
                if($value['address'] != '' && !is_array(json_decode($value['address']))){
                    $orderText .= self::t($lang_id, 'address') . ": " . $value['address'] . "\n";
                }

                $orderText .= "\n\n";
            }
                
        }
        else{
            $orderText = self::t($lang_id, 'order_history_empty');
        }
        return $orderText;
    }

    public function getOrderTotal($user_id){
        $total = 0;
        $products = $this->getOrderItems($user_id);
        if(is_array($products) && count($products) > 0){
            foreach($products as $key => $product){
                $total += $product['price'] * $product['quantity'];
            }
        }
        return $total;
    }

    public function getOrderText($user_id, $lang_id = 0) {

        $text = '';
        $total = 0;
        
        $products = $this->getOrderItems($user_id);
        if(is_array($products) && count($products) > 0){
            foreach($products as $key => $product){
                $total += $product['price'] * $product['quantity'];
                $text .= ($key + 1) . '. ' . $product['title'] . '. ' . self::t($lang_id, 'price') . ': ' . number_format($product['price']) . self::t($lang_id, 'price_currency') . '. ' . self::t($lang_id, 'quantity_in_cart') . ': ' . $product['quantity'] . '. ' . self::t($lang_id, 'sum') . ': ' . number_format($product['price'] * $product['quantity']) . self::t($lang_id, 'price_currency') . '.' . "\n";
            }
            $text .= self::t($lang_id, 'total') . ': ' . number_format($total) . self::t($lang_id, 'price_currency') . "\n";
        }
        
        return $text;
    }

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

        if($text == 'view_order_history'){
            $getOrderHistoryText = $this->getOrdersHistoryText($user_id, $lang_id);
            $orderHistoryText = self::t($lang_id, 'your_orders_list') . ':' . "\n\n" . $getOrderHistoryText;

            $data['text'] = $orderHistoryText;
            $data['parse_mode'] = 'Markdown';
            $data['reply_markup'] = StartCommand::getKeyboard($lang_id);
            $result = Request::sendMessage($data);
            return $resultPPP;
        }

        $result = Request::emptyResponse();

        if ($text === self::t($lang_id, 'back') && $notes['state'] > 0) {
            $notes['state']--;
            if($notes['state'] == 2) $notes['state'] = 1;
            $text = '';
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
                if ($text === '') {
                    $notes['state'] = 0;
                    $this->conversation->update();

                    $data['text'] = self::t($lang_id, 'confirm_or_write_your_name') . "\n" . '*' . $user->getFirstName() . '*';
                    $data['parse_mode'] = 'Markdown';
                    $data['reply_markup'] = (new Keyboard(
                        [self::t($lang_id, 'confirm'), self::t($lang_id, 'back')]
                    ))
                        ->setOneTimeKeyboard(false)
                        ->setResizeKeyboard(true)
                        ->setSelective(true);

                    $result = Request::sendMessage($data);
                    break;
                }

                //отмена заказ
                if($text == self::t($lang_id, 'back')){
                    $notes['state'] = 0;
                    $this->conversation->update();
                    $this->conversation->stop();

                    $data['text'] = self::t($lang_id, 'order_cancelled');
                    $data['reply_markup'] = StartCommand::getKeyboard($lang_id);
                    $result = Request::sendMessage($data);
                    break;
                }

                if($text == self::t($lang_id, 'confirm')){
                    $notes['name'] = $user->getFirstName();
                }
                else{
                    $notes['name'] = $text;
                }
                $text = '';

            // no break
            case 1:
                if ($text === '') {
                    $notes['state'] = 1;
                    $this->conversation->update();

                    $data['text']         = self::t($lang_id, 'shipping_method');
                    $data['reply_markup'] = (new Keyboard(
                        [self::t($lang_id, 'shipping_method_self_pickup'), self::t($lang_id, 'shipping_method_to_address'), self::t($lang_id, 'back')]
                    ))
                        ->setOneTimeKeyboard(false)
                        ->setResizeKeyboard(true)
                        ->setSelective(true);

                    $result = Request::sendMessage($data);
                    break;
                }

                $notes['shipping'] = $text;
                $text              = '';

            // no break
            case 2:
                if($notes['shipping'] != self::t($lang_id, 'shipping_method_self_pickup')){
                    if ($text === '' && $message->getLocation() === null) {
                        $notes['state'] = 2;
                        $this->conversation->update();

                        $data['reply_markup'] = (new Keyboard(
                            [(new KeyboardButton(self::t($lang_id, 'send_location')))->setRequestLocation(true), self::t($lang_id, 'back')]
                        ))
                            ->setOneTimeKeyboard(false)
                            ->setResizeKeyboard(true)
                            ->setSelective(true);

                        $data['text'] = self::t($lang_id, 'enter_your_address_or_send_location');

                        $result = Request::sendMessage($data);
                        break;
                    }

                    if($message->getLocation() !== null){
                        $notes['latitude']  = $message->getLocation()->getLatitude();
                        $notes['longitude'] = $message->getLocation()->getLongitude();
                    }
                    else{
                        $notes['address'] = $text;
                    }
                    $text = '';
                }            
            // no break

            case 3:
                if ($text === '') {
                    $notes['state'] = 3;
                    $this->conversation->update();

                    $data['text']         = self::t($lang_id, 'choose_payment_method');
                    $data['reply_markup'] = (new Keyboard(
                        [self::t($lang_id, 'payment_method_cash'), self::t($lang_id, 'payment_method_online')],
                        [self::t($lang_id, 'payment_method_transfer'), self::t($lang_id, 'back')]
                    ))
                        ->setOneTimeKeyboard(false)
                        ->setResizeKeyboard(true)
                        ->setSelective(true);

                    $result = Request::sendMessage($data);
                    break;
                }

                $notes['payment'] = $text;
                $text              = '';

            // no break
            case 4:
                if ($text === '' && $message->getContact() === null) {
                    $notes['state'] = 4;
                    $this->conversation->update();

                    $data['reply_markup'] = (new Keyboard(
                        [(new KeyboardButton(self::t($lang_id, 'send_my_number')))->setRequestContact(true), self::t($lang_id, 'back')]
                    ))
                        ->setOneTimeKeyboard(false)
                        ->setResizeKeyboard(true)
                        ->setSelective(true);

                    $data['text'] = self::t($lang_id, 'enter_phone_number_or_send');

                    $result = Request::sendMessage($data);
                    break;
                }

                if($message->getContact() !== null){
                    $notes['phone_number'] = $message->getContact()->getPhoneNumber();
                }
                else{
                    $notes['phone_number'] = $text;
                }
                $text = '';
                

            // no break
            case 5:
                if ($text === '') {
                    $notes['state'] = 5;
                    $this->conversation->update();

                    $sendLocation = false;

                    $orderDetails = self::t($lang_id, 'confirm_order') . "\n\n";
                    $orderDetails .= self::t($lang_id, 'your_name') . ': ' . $notes['name'] . "\n";
                    $orderDetails .= self::t($lang_id, 'your_phone_number') . ': ' . $notes['phone_number'] . "\n";
                    $items = $this->getOrderText($user_id, $lang_id);
                    $orderDetails .= self::t($lang_id, 'products') . ': ' . "\n" . $items;
                    $orderDetails .= self::t($lang_id, 'payment_method') . ': ' . $notes['payment'] . "\n";
                    $orderDetails .= self::t($lang_id, 'shipping_method') . ': ' . $notes['shipping'] . "\n";
                    if($notes['shipping'] !== self::t($lang_id, 'shipping_method_self_pickup')){
                        $orderDetails .= self::t($lang_id, 'your_address') . ': ';
                        if(isset($notes['address']) && $notes['address'] != ''){
                            $orderDetails .= $notes['address'];
                        }
                        if(isset($notes['latitude']) && isset($notes['longitude'])){
                            $sendLocation = true;
                        }
                    }




                    $data['reply_markup'] = (new Keyboard(
                        [self::t($lang_id, 'confirm'), self::t($lang_id, 'back')]
                    ))
                        ->setOneTimeKeyboard(false)
                        ->setResizeKeyboard(true)
                        ->setSelective(true);

                    $data['text'] = $orderDetails;
                    $result = Request::sendMessage($data);

                    if($sendLocation){
                        Request::sendLocation([
                            'chat_id' => $chat_id,
                            'latitude' => $notes['latitude'],
                            'longitude' => $notes['longitude'],
                        ]);
                    }
                    break;
                }
                elseif($text == self::t($lang_id, 'confirm')) {
                    $pdo = DB::getPdo();
                    $total = $this->getOrderTotal($user_id);
                    $orderDate = time();
                    $address = '';
                    if(isset($notes['latitude']) && isset($notes['longitude'])){
                        $address = json_encode(['latitude' => $notes['latitude'], 'longitude' => $notes['longitude']]);
                    }
                    if(isset($notes['address'])){
                        $address = $notes['address'];
                    }

                    $shipping_method = $notes['shipping'];
                    $payment_method = $notes['payment'];
                    $receiver_name = $notes['name'];

                    //insert order
                    $insertOrder = $pdo->prepare('INSERT INTO ' . TB_STORE_ORDER . ' (user_id, date_created, total, phone, address, shipping_method, payment_method, receiver_name, status) VALUES (:user_id, :date_created, :total, :phone, :address, :shipping_method, :payment_method, :receiver_name, 1)');
                    $insertOrder->bindParam(':user_id', $user_id);
                    $insertOrder->bindParam(':date_created', $orderDate);
                    $insertOrder->bindParam(':total', $total);
                    $insertOrder->bindParam(':phone', $notes['phone_number']);
                    $insertOrder->bindParam(':address', $address);
                    $insertOrder->bindParam(':shipping_method', $shipping_method);
                    $insertOrder->bindParam(':payment_method', $payment_method);
                    $insertOrder->bindParam(':receiver_name', $receiver_name);

                    $insertCheck = $insertOrder->execute();

                    if($insertCheck){
                        $order_id = $pdo->lastInsertId();
                        //insert order items
                        $products = $this->getOrderItems($user_id);
                        $insertOrderItem = $pdo->prepare('INSERT INTO ' . TB_STORE_ORDER_ITEM . ' (order_id, product_id, title, price, quantity) VALUES (:order_id, :product_id, :title, :price, :quantity)');
                        foreach($products as $product){
                            $insertOrderItem->bindParam(':order_id', $order_id);
                            $insertOrderItem->bindParam(':product_id', $product['id']);
                            $insertOrderItem->bindParam(':title', $product['title']);
                            $insertOrderItem->bindParam(':price', $product['price']);
                            $insertOrderItem->bindParam(':quantity', $product['quantity']);

                            $insertOrderItem->execute();
                        }

                        //send message to manager
                        $manager_id = $this->getConfig('store_manager_id');
						
                        $getChatId = $pdo->prepare('SELECT chat_id FROM ' . TB_USER_CHAT . ' WHERE user_id = :user_id');
                        
                        $orderDetails = $this->getOrderText($user_id, $lang_id);
                        $manager_message = self::t($lang_id, 'new_order_from') . ' ' . $notes['name'] . "\n" . self::t($lang_id, 'contact_phone') . ': ' . $notes['phone_number'] . "\n" . self::t($lang_id, 'shipping_method') . ': ' . $notes['shipping'] . "\n" . self::t($lang_id, 'payment_method') . ': ' . $notes['payment'] . "\n" . self::t($lang_id, 'order_details') . ': ' . "\n" . $orderDetails;
                        $sendLocation = false;
                        if($notes['shipping'] !== self::t($lang_id, 'shipping_method_self_pickup')){
                            $manager_message .= self::t($lang_id, 'address') . ': ';
                            if(isset($notes['address']) && $notes['address'] != ''){
                                $manager_message .= $notes['address'];
                            }
                            if(isset($notes['latitude']) && isset($notes['longitude'])){
                                $sendLocation = true;
                            }
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
                            $mail->Subject = $sitename . ' Order';
                            $mail->Body    = $manager_message;
                            $mail->AltBody = $manager_message;

                            $mail->send();
                            echo 'Message has been sent';
                        } catch (Exception $e) {
                            echo 'Message could not be sent.';
                            echo 'Mailer Error: ' . $mail->ErrorInfo;
                        }
                            

                        //clean cart
                        $deleteCart = $pdo->prepare('DELETE FROM ' . TB_STORE_CART . ' WHERE user_id = :user_id');
                        $deleteCart->bindParam(':user_id', $user_id);
                        $deleteCart->execute();

                        $data['text'] = self::t($lang_id, 'order_accepted');
                        $data['reply_markup'] = StartCommand::getKeyboard($lang_id);
                        $result = Request::sendMessage($data);
                    }

                }
                if($text == self::t($lang_id, 'cancel')) {
                    $notes['state'] = 0;
                    $this->conversation->update();
                    $this->conversation->stop();

                    $data['text'] = self::t($lang_id, 'order_cancelled');
                    $data['reply_markup'] = StartCommand::getKeyboard($lang_id);
                    $result = Request::sendMessage($data);
                    
                }

        }

        return $result;
    }
}
