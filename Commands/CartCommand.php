<?php

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\DB;

use Longman\TelegramBot\Commands\SystemCommands\StartCommand;

class CartCommand extends UserCommand
{
    protected $name = 'cart';
    protected $description = 'Your cart';
    protected $usage = '/cart';
    protected $version = '1.0.0';
    
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

    public function execute()
    {
		$message = $this->getMessage();
        $user    = $message->getFrom();
        $user_id = $user->getId();
        $chat    = $message->getChat();
        $chat_id = $chat->getId();
        $text    = trim($message->getText(true));

        $lang_id = StartCommand::getLanguage($user_id);

        $pdo = DB::getPdo();
        $products = [];
        $total = 0;

        $keyboard = new Keyboard(
            [self::t($lang_id, 'main_page'), self::t($lang_id, 'catalogue')]
        );

        $responseText = self::t($lang_id, 'cart_is_empty') . ' ' . "\n" . self::t($lang_id, 'look_through_catalogue');

        if($text == 'clean'){
            //clean cart
            $cleanCart = $pdo->prepare('DELETE FROM ' . TB_STORE_CART . ' WHERE user_id = :user_id');
            $cleanCart->bindParam(':user_id', $user_id);
            $cleanCart->execute();
        }
        else{
            //get user cart id
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
                    if(count($products) > 0){
                        $responseText = '';
                        foreach($products as $key => $product){
                            $total += $product['price'] * $product['quantity'];
                            $responseText .= ($key + 1) . '. ' . $product['title'] . '. ' . self::t($lang_id, 'price') . ': ' . number_format($product['price']) . self::t($lang_id, 'price_currency') . '. ' . self::t($lang_id, 'quantity_in_cart') . ': ' . $product['quantity'] . '. ' . self::t($lang_id, 'sum') . ': ' . number_format($product['price'] * $product['quantity']) . self::t($lang_id, 'price_currency') . '.' . "\n\n";
                        }
                        $responseText .= self::t($lang_id, 'total') . ': ' . number_format($total) . self::t($lang_id, 'price_currency');
                        $keyboard = new Keyboard(
                            [self::t($lang_id, 'submit_order'), self::t($lang_id, 'clear_cart')],
                            [self::t($lang_id, 'main_page'), self::t($lang_id, 'catalogue')]
                        );
                            
                    }
                }
            }
        }
            

        $keyboard->setResizeKeyboard(true);
        $data = [
            'chat_id'      => $chat_id,
            'text'         => $responseText,
            'reply_markup' => $keyboard,
        ];

        return Request::sendMessage($data);
    }
}