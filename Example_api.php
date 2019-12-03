<?php

namespace common\components\external;

use common\models\Settings;
use yii\base\Object;
use yii\httpclient\Client;

class Example_api extends ExternalApi
{
    // Параметры авторизации
    private $token;
    private $token_issued;
    private $token_type;
    private $host = 'https://example.com';
    private $auth_credentials = [];
    private $endpoints = [];

    // Формат запроса
    private $request_format = \yii\httpclient\Client::FORMAT_JSON;
    // Название поля настроек
    private $setting_name;

    // Время жизни токена в секундах
    protected $token_expiration = 900;
    protected $headers;

    // Массив ошибок
    public $errors = [];

    /**
     * Конструктор.
     * Загрузка настроек, авторизация во внешнем сервисе
     *
     */
    public function __construct()
    {
        // Название поля настроек АПИ
        $this->setting_name = __CLASS__ . '_settings';

        // Загрузка настроек
        if($settings = Settings::getByName($this->setting_name, true)){
            $this->token = $settings['token'] ?? null;
            $this->token_issued = $settings['token_issued'] ?? null;
            $this->token_type = $settings['token_type'] ?? null;

            if(!empty($settings['auth_credentials'])){
                foreach ($this->auth_credentials as $k => $ac){
                    if(!empty($settings['auth_credentials'][$k])){
                        $this->auth_credentials[$k] = $settings['auth_credentials'][$k];
                    }
                }
            }

            if(!empty($settings['host'])){
                $this->host = $settings['host'];
            }

            if(!empty($settings['endpoints'])){
                foreach($settings['endpoints'] as $k => $endpoint){
                    $this->endpoints[$k] = $endpoint;
                }
            }
        }

        // Проверка токена / Авторизация
        if(!$this->checkToken()){
            $this->auth();
        }
    }

    /**
     * Формирование массива данных заголовков запроса
     *
     * @return array
     */
    protected function headers(): array {
        return [
            'Content-Type: application/json',
            'accept: application/json'
        ];
    }

    /**
     * Авторизация
     *
     * @return bool
     */
    protected function auth(): bool {

        //Отправка запроса авторизации
        $response = json_decode($this->post('auth', [], $this->auth_credentials, [], 'POST'), true);

        // Сохранение / Сброс токена
        if (!empty($response['access_token']) && !empty($response['issued_at']) && !empty($response['token_type'])){
            $this->saveToken($response['access_token'], $response['issued_at'],  $response['token_type']);
        } else {
            $this->dropToken();
            $this->errors[] = json_encode($response);
            return false;
        }
        return true;
    }

    /**
     * Проверка токена
     *
     * @return bool
     */
    protected function checkToken(): bool {
        if(!empty($this->token) && !empty($this->token_issued) && !empty($this->token_type)){
            $time = time() * 1000;
            $lifetime = $this->token_expiration * 1000;
            if(($time - $this->token_issued) < $lifetime){
                return true;
            }
        }
        return false;
    }


    /**
     * Сохранение токена
     *
     * @param string $token
     * @param string $issued_at
     * @param string $token_type
     * @return bool
     */
    protected function saveToken(string $token, string $issued_at, string $token_type): bool {
        $settings = Settings::getByName($this->setting_name, true);
        $this->token = $token;
        $this->token_issued = $issued_at;
        if($settings !== null){
            $settings['token'] = $token;
            $settings['token_issued'] = $issued_at;
            $settings['token_type'] = $token_type;
            Settings::setByName($this->setting_name, json_encode($settings, JSON_UNESCAPED_SLASHES));
        }else{
            Settings::add($this->setting_name, json_encode([
                'host' => $this->host,
                'auth_credentials' => $this->auth_credentials,
                'endpoints' => $this->endpoints,
                'token' => $token,
                'token_issued' => $issued_at,
                'token_type' => $token_type], JSON_UNESCAPED_SLASHES), $this->setting_name);
        }
        return true;
    }

    /**
     * Сброс токена
     *
     * @return bool
     */
    protected function dropToken(): bool {
        $settings = Settings::getByName($this->setting_name, true);
        $this->token = '';
        $this->token_issued = '';
        if($settings !== null){
            $settings['token'] = '';
            $settings['token_issued'] = '';
            $settings['token_type'] = '';
            Settings::setByName($this->setting_name, json_encode($settings, JSON_UNESCAPED_SLASHES));
        }
        return true;
    }

    /**
     * Добавление токена в массив данных заголовка запроса
     *
     * @param array $headers
     * @return array
     */
    private function addAuthorization(array $headers): array {
        $headers['authorization'] = $this->token_type.' '.$this->token;
        return $headers;
    }

    /**
     * Отправка запроса
     *
     * @param string $endpoint
     * @param array $data
     * @param array $query_params
     * @param array $headers
     * @param string $request_method
     * @return bool
     */
    private function post(string $endpoint, array $data = [], array $query_params = [], array $headers = [], string $request_method = 'GET') {
        $client = new Client();

        if(empty($this->host) || $this->host == ''){
            return false;
        }

        if(empty($this->endpoints[$endpoint])){
            return false;
        }

        $headers = array_merge($this->headers(), $headers);
        if( $this->token ) {
            $headers = $this->addAuthorization($headers);
        }

        $url = $this->endpoints[$endpoint];
        if($query_params){
            $query_string = '?'.urldecode(http_build_query($query_params));
        }

        try {
            $response = $client->createRequest()
                ->setMethod($request_method)
                ->setOptions(['timeout' => 60])
                ->setFormat($this->request_format)
                ->setUrl($this->host.$url.($query_string ?? ''))
                ->addHeaders($headers)
                ->setData($data)
                ->send();

        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
            return false;
        }
        return $response->content;
    }

    /**
     * Получение данных из внешнего АПИ и возврат результата
     * 
     * @param $data
     * @return bool|mixed
     */
    public function sendData($data){
        // Если переданы данные для отправки
        if($data){
            // Получение данных
            $response = $this->post('post', $data, [], [], 'POST');
            // Возврат результата
            return json_decode($response, true);
        }
        return false;
    }
}