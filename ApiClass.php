<?php

namespace InterIntellect\TelegramBot;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException;

/**
* 
*/
class Api
{

	public $config;
	public $client;
	public $apiKey;
	public $db;
	public $db_prefix;
	public $input;

	public $tokenLimit = 0;
	public $actions = array(
        'sendMessage',
		'sendPhoto',
	);

	public function __construct($config = array())
	{
		$this->config = $config;
		$this->client = new Client(['base_uri' => 'https://api.telegram.org/bot' . $this->config['bot_api_key'] . '/']);
		$this->apiKey = $this->config['bot_api_key'];

		$dsn = 'mysql:dbname=' . $this->config['db']['database'] . ';host=' . $this->config['db']['host'];
		$user = $this->config['db']['user'];
		$password = $this->config['db']['password'];
		try {
		    $this->db = new \PDO($dsn, $user, $password);
		    $this->db_prefix = $this->config['db']['prefix'];
		} catch (\PDOException $e) {
		    echo 'Подключение не удалось: ' . $e->getMessage();
		}
	}

	public function handle()
    {

        $this->input = (!empty($_POST)) ? $_POST : $_GET;
        foreach ($this->input as $key => $value) {
            $this->input[$key] = rawurldecode($value);
        }

        //check post/get
        if (!is_array($this->input)) {
            $this->error('invalid post');
        }

        //check post/get is not empty
        if (empty($this->input)) {
            $this->error('post is empty');
        }

        //check action is not empty
        if(empty($this->input['action'])){
        	$this->error('action is empty');
        }

        //check action exists
        if(!in_array($this->input['action'], $this->actions)){
        	$this->error('action is not valid');
        }

        //check token is not empty
        if(empty($this->input['token'])){
        	$this->error('token is empty');
        }

        //check token exists
        $checkTokenSth = $this->db->prepare("SELECT * FROM " . $this->db_prefix . "api_token WHERE token = ?");
        $checkTokenSth->execute([$this->input['token']]);
        if($checkTokenSth->rowCount() == 0){
        	$this->error('token is not valid');
        }

        //check token limit
        if($checkTokenSth->rowCount() > 0){
        	$token = $checkTokenSth->fetch();
        	if($this->tokenLimit > 0 && $token['counter'] >= $this->tokenLimit){
        		$this->error('token has reached limit: ' . $this->tokenLimit . ' requests/month');
        	}
        	$token['counter']++;
        	$updateTokenSth = $this->db->prepare("UPDATE " . $this->db_prefix . "api_token SET counter = ? WHERE token = ?");
        	$updateTokenSth->execute([$token['counter'], $token['token']]);
        }

        $action =$this->input['action'];

        return $this->$action();
	}

    public function sendMessage()
    {

        $data = array();
        $data['success'] = false;

        //get chat_id
        $chat_id = $this->getPrivateChatId();
        
        //check message
        if(empty($this->input['message'])){
            $this->error('message is empty');
        }
        $message = (string)$this->input['message'];

        try{
            $response = $this->client->request('POST', 'sendMessage', [
                'form_params' => [
                    'chat_id' => $chat_id,
                    'text' => $message,
                ]
            ]);
            $telegramResponse = $response->getBody();
            $telegramResponse = json_decode($telegramResponse);
            if($telegramResponse->ok){
                $data['success'] = true;
            }
        }
        catch (ClientException $e){
            echo Psr7\str($e->getRequest());
            echo '<br>';
            echo '<br>';
            echo Psr7\str($e->getResponse());
        }
            
        $this->json($data);
    }

	public function sendPhoto()
    {

        $data = array();
        $data['success'] = false;

        //get chat_id
        $chat_id = $this->getPrivateChatId();

        //check photo
        if(empty($this->input['photo'])){
            $this->error('photo is empty');
        }
        $photo = (string)$this->input['photo'];
        $newFile = true;

        //check file exists (url send before)
        $checkFile = $this->checkFile($photo);
        if($checkFile != ''){
            $newFile = false;
            $photo = $checkFile;
        }
        
        //check caption
        $caption = '';
        if(!empty($this->input['caption'])){
            $caption = substr($this->input['caption'], 0, 200);
        }

        try{
            $response = $this->client->request('POST', 'sendPhoto', [
                'form_params' => [
                    'chat_id' => $chat_id,
                    'photo' => $photo,
                    'caption' => $caption,
                ]
            ]);
            $telegramResponse = $response->getBody();
            $telegramResponse = json_decode($telegramResponse);
            if($telegramResponse->ok){
                $data['success'] = true;
            }
            if(!empty($telegramResponse->result->photo)){
                $sendFile = end($telegramResponse->result->photo);
                //$data['file_id'] = $sendFile->file_id;
                $current_file_id = $sendFile->file_id;
                if($newFile && substr($photo, 0, 4) == 'http'){
                    $insertFileSth = $this->db->prepare("INSERT INTO " . $this->db_prefix . "file (id, file_id, url) VALUES ('', ?, ?)");
                    $insertFileSth->execute([$current_file_id, $photo]);
                }
            }
        }
        catch (ClientException $e){
            //$error = $e->getResponse()->getBody();
            //$error = json_decode($error);
            //$this->error($error->description);
            $this->error('wrong photo url or file_id - ' . $photo);
        }
        $this->json($data);
        
    		
	}

    private function checkFile($url)
    {
        $file_id = '';
        $getFileSth = $this->db->prepare("SELECT file_id FROM " . $this->db_prefix . "file WHERE url = ?");
        $getFileSth->execute([$url]);
        if($getFileSth->rowCount() > 0){
            $file = $getFileSth->fetch();
            $file_id = $file['file_id'];
        }
        return $file_id;
    }

    private function getPrivateChatId()
    {
        $user_id = $this->getUserId();
        $getChatIdSth = $this->db->prepare("SELECT c.id FROM " . $this->db_prefix . "user_chat uc LEFT JOIN " . $this->db_prefix . "chat c ON uc.chat_id = c.id WHERE c.type = 'private' AND uc.user_id = ?");
        $getChatIdSth->execute([$user_id]);
        if($getChatIdSth->rowCount() == 0){
            $this->error('chat not found');
        }
        $chat = $getChatIdSth->fetch();
        return $chat['id'];
    }

    private function getUserId()
    {
        if(empty($this->input['phone'])){
            $this->error('phone is empty');
        }
        $checkPhoneSth = $this->db->prepare("SELECT id FROM " . $this->db_prefix . "user WHERE phone = ?");
        $checkPhoneSth->execute([$this->input['phone']]);
        if($checkPhoneSth->rowCount() == 0){
            $this->error('user not found');
        }
        $user = $checkPhoneSth->fetch();
        return $user['id'];
    }

    private function error($message = 'error')
    {
        
        $response = array(
            'success' => false,
            'message' => $message,
        );

        header("Content-Type: application/json;charset=utf-8");
        $json = json_encode($response);
        if ($json === false) {
            $json = json_encode(array("jsonError", json_last_error_msg()));
            if ($json === false) {
                $json = '{"jsonError": "unknown"}';
            }
            http_response_code(500);
        }
        echo $json;
        exit;
    }

	private function json($data = array())
    {
		header("Content-Type: application/json;charset=utf-8");
        $json = json_encode($data);
        if ($json === false) {
            $json = json_encode(array("jsonError", json_last_error_msg()));
            if ($json === false) {
                $json = '{"jsonError": "unknown"}';
            }
            http_response_code(500);
        }
        echo $json;
        exit;
	}
}