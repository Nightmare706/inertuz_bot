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

class ProductCommand extends UserCommand
{

    protected $name = 'product';
    protected $description = 'Список товаров';
    protected $usage = '/product <product>';
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

    public static function getProducts() {
        $pdo = DB::getPdo();
        $products = [];
        $getProducts = $pdo->query('SELECT * FROM ' . TB_STORE_PRODUCT);
        if($getProducts->rowCount() > 0){
            $products = $getProducts->fetchAll();
        }
        return $products;
    }

    public static function getProduct($product_id = 0) {
        $pdo = DB::getPdo();
        $product = [];

        $getProduct = $pdo->prepare('SELECT * FROM ' . TB_STORE_PRODUCT . ' WHERE id = :id');
        $getProduct->bindParam(':id', $product_id);
        $getProduct->execute();
        if($getProduct->rowCount() > 0){
            $product = $getProduct->fetch();
            $product_catalogue = BASEPATH . '/catalogue/products/' . $product['id'];
            if(is_dir($product_catalogue)){
                if ($image_list = array_slice(scandir($product_catalogue), 2)) {
                    shuffle($image_list);
                    $product['image'] = $product_catalogue . '/' . $image_list[0];
                }
            }
        }
        return $product;
    }

    public static function showProduct($message, $product_id = 0, $lang_id = 0) {
        if(!$product_id || !$message){
            return false;
        }
        $chat    = $message->getChat();
        $user    = $message->getFrom();
        $text    = trim($message->getText(true));
        $chat_id = $chat->getId();
        $user_id = $user->getId();

        $product = self::getProduct($product_id);
        if ( (isset($product['image_file_id']) && $product['image_file_id'] != false) || (isset($product['image']) && $product['image'] != false) ) {
            $caption = '';

            $product['description'] = json_decode($product['description'], true);


            $title = $product['title'];
            $description = $product['description'][$lang_id];
            $price = self::t($lang_id, 'price') . ": " . number_format($product['price']) . self::t($lang_id, 'price_currency_quantity');

            

            /*$allowedLength = 200;
            $titleLength = mb_strlen($title);
            $priceLength = mb_strlen($price);

            if($titleLength > ($allowedLength - $priceLength) ){
                $leftLength1 = $allowedLength - $priceLength - 5;
                $title = mb_substr($title, 0, $leftLength1) . "...\n\n";
                $titleLength = mb_strlen($title);
            }
            $caption .= $title;
            $captionLength = $titleLength + $priceLength;
            if($captionLength < $allowedLength){
                $leftLength2 = $allowedLength - 2 - $captionLength;
                $descriptionLength = mb_strlen($description);
                if($leftLength2 < $descriptionLength){
                    $withAddDotsLength = $leftLength2 - 3;
                    if($withAddDotsLength > 0){
                        $description = mb_substr($description, 0, $withAddDotsLength) . '...';
                    }
                    else{
                        $description = '';
                    }
                }
                $caption .= $description . "\n\n";
            }
            $caption .= $price;*/

            
            $keyboard = self::getKeyboard($lang_id);
            
            $imageSentBefore = $product['image_file_id'];

            if($imageSentBefore){
                $product_photo = $product['image_file_id'];
            }
            else{
                $product_photo = Request::encodeFile($product['image']);
            }
            

            $data = [
                'chat_id' => $chat_id,
                'caption' => $title,
                'disable_notification' => true,
                'photo' => $product_photo,
                'reply_markup' => $keyboard
            ];
            $photoResponse = Request::sendPhoto($data);

            $messageText = '';

            $messageText .= '<b>' . $title . '</b>' . "\n\n";
            if($description){
                $messageText .= $description . "\n\n";
            }
            
            $messageText .= $price . "\n\n";
            //$messageText .= '<a href="' . $product['url'] . '">Подробнее</a>';

            $data = [
                'chat_id' => $chat_id,
                'text' => $messageText,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
                'reply_markup' => $keyboard
            ];
            $response = Request::sendMessage($data);

            //add image to db
            if(!$imageSentBefore) {
                if ($photoResponse->isOk()) {

                    $photos = $photoResponse->getResult()->getPhoto();
                    if(is_array($photos) && count($photos) > 0){
                        $photo = array_pop($photos);
                        if(is_object($photo)){
                            $file_id = $photo->getFileId();
                            self::saveImage($product['id'], $file_id);
                        }
                    }
                }
            }
            
            return $response;
        }
        return false;
    }

    public static function saveImage($product_id, $file_id) {
        $pdo = DB::getPdo();
        $getProduct = $pdo->prepare('UPDATE ' . TB_STORE_PRODUCT . ' SET image_file_id = :image_file_id WHERE id = :id');
        $getProduct->bindParam(':id', $product_id);
        $getProduct->bindParam(':image_file_id', $file_id);
        $getProduct->execute();
    }
	
	public static function getInlineKeyboard(){
		$keyboard_buttons = [];
        $products = self::getProducts();
        foreach ($products as $value) {
            $keyboard_buttons[] = [new InlineKeyboardButton(
				[
					'text'          => $value['title'],
					'callback_data' => 'product_' . $value['id'],
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

    public static function getKeyboard($lang_id) {

        $keyboard = new Keyboard(
            [self::t($lang_id, 'add_to_cart'), self::t($lang_id, 'catalogue')]
        );
        $keyboard->setResizeKeyboard(true)->setOneTimeKeyboard(true)->setSelective(false);
        return $keyboard;
    }

    private function addToCart($user_id, $product_id, $add_quantity = 1){
        $pdo = DB::getPdo();
        $products = [];
        //get user cart id
        $getCartId = $pdo->prepare('SELECT * FROM ' . TB_STORE_CART . ' WHERE user_id = :user_id');
        $getCartId->bindParam(':user_id', $user_id);
        $getCartId->execute();

        if($getCartId->rowCount() > 0){
            $cart = $getCartId->fetch();
            $cart_id = $cart['id'];
        }
        else{
            $insertCart = $pdo->prepare('INSERT INTO ' . TB_STORE_CART . ' (user_id) VALUES (:user_id)');
            $insertCart->bindParam(':user_id', $user_id);
            $insertCart->execute();
            $cart_id = $pdo->lastInsertId();
        }

        //check item exists in cart
        $getCartItem = $pdo->prepare('SELECT * FROM ' . TB_STORE_CART_ITEM . ' WHERE cart_id = :cart_id AND product_id = :product_id');
        $getCartItem->bindParam(':cart_id', $cart_id);
        $getCartItem->bindParam(':product_id', $product_id);
        $getCartItem->execute();

        //updating cart item
        if($getCartItem->rowCount() > 0){
            $cartItem = $getCartItem->fetch();
            $updateCartItem = $pdo->prepare('UPDATE ' . TB_STORE_CART_ITEM . ' SET quantity = :quantity WHERE id = :id');
            $updateCartItem->bindParam(':id', $cartItem['id']);
            $quantity = (int)$cartItem['quantity'] + $add_quantity;
            $updateCartItem->bindParam(':quantity', $quantity);
            $updateCartItem->execute();
        }
        //inserting new item to cart
        else{
            
            $insertCartItem = $pdo->prepare('INSERT INTO ' . TB_STORE_CART_ITEM . ' (cart_id, product_id, quantity) VALUES (:cart_id, :product_id, :quantity)');
            $insertCartItem->bindParam(':cart_id', $cart_id);
            $insertCartItem->bindParam(':product_id', $product_id);
            $insertCartItem->bindParam(':quantity', $add_quantity);
            $insertCartItem->execute();
        }

    }

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

        $product_id = $text;

        $this->conversation = new Conversation($user_id, $chat_id, $this->getName());

        $notes = &$this->conversation->notes;
        !is_array($notes) && $notes = [];

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
                $this->conversation->update();

                //если цифра и state 0 показываем товар
                if ( is_numeric($text) ) {
                    $product_id = (int)$text;
                    $result = self::showProduct($message, $product_id, $lang_id);
                    $notes['product_id'] = $product_id;
                    $this->conversation->update();
                    break;
                } 
                //добавляем в корзину - этап 1
                elseif (isset($notes['product_id']) && $text === self::t($lang_id, 'add_to_cart')) {

                    

                    $text = '';
                }
                //показываем товары
                else {
                    

                    $data = [
                        'chat_id'      => $chat_id,
                        'text'         => self::t($lang_id, 'our_products'),
                        'reply_markup' => StartCommand::getKeyboard($lang_id),
                    ];
                    Request::sendMessage($data);

                    $data = [
                        'chat_id'      => $chat_id,
                        'text'         => self::t($lang_id, 'choose_product'),
                        'reply_markup' => self::getInlineKeyboard(),
                    ];
                    $result = Request::sendMessage($data);
                    break;
                }

            // no break
            case 1:
                
                
                if ( $text === '' || !is_numeric($text) ) {
                    $notes['state'] = 1;
                    $this->conversation->update();

                    $keyboard = new Keyboard(
                        ['10', '25', '50'],
                        ['100', '250', '500']
                    );
                    $keyboard
                        ->setResizeKeyboard(true)
                        ->setOneTimeKeyboard(false)
                        ->setSelective(false);

                    $data = [
                        'text'          => self::t($lang_id, 'choose_or_write_qty'),
                        'chat_id'       => $chat_id,
                        'reply_markup'  => $keyboard,
                    ];

                    $result = Request::sendMessage($data);

                }
                //добавляем в корзину - этап 2
                elseif ( isset($notes['product_id']) && is_numeric($text) ) {

                    $quantity = (int)$text;
                    $this->addToCart($user_id, $notes['product_id'], $quantity);
                    
                    $data = [
                        'text'          => self::t($lang_id, 'product_added_to_cart'),
                        'chat_id'       => $chat_id,
                        'reply_markup'  => StartCommand::getKeyboard($lang_id),
                    ];

                    $notes = [];
                    $this->conversation->update();

                    $this->conversation->stop();
                    $result = Request::sendMessage($data);

                } 
        }

        return $result;

    }
}