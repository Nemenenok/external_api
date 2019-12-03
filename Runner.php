<?php

namespace common\components\external;

class Runner {

    // Проект
    private $project;
    // Массив данных для передачи во внешнее АПИ
    private $data;

    /**
     * Конструктор.
     * Сохранение настроек и данных
     *
     * @param string $project
     * @param array $data
     */
    public function __construct( string $project, array $data) {
        $this->project = $project;
        $this->data = $data;
    }

    /**
     * Подключение внешнего АПИ
     * отправка данных и получение результата
     *
     * @return array [errors => массив ошибок, response => результат запроса внешнего АПИ]
     */
    public function run(): array {

        // Массив ошибок
        $errors = [];

        // Массив данных ответа
        $response = [];

        // Название класса внешнего API
        $ApiClassName = __NAMESPACE__ . "\\" . $this->project . '_api';

        // Если класс существует
        if (class_exists($ApiClassName)) {

            // Получение экземпляра класса
            $client = $ApiClassName::getInstance();

            // Если получены ошибки
            if($client->errors){
                \Yii::info("Sync error. Connection refuse: ". $client->errors[0] ?? '', 'api');
                $errors['Connection error'] = $client->errors[0] ?? '';
            } else {
                // Если не получен результат
                if(!($response = $client->sendData($this->data))) {
                    \Yii::info("Sync error. Connection post error: ". json_encode($client->errors), 'api');
                    $errors['Connection post error'] = json_encode($client->errors);
                }
            }

        } else {
            $message = 'External API class not found: '. $ApiClassName;
            \Yii::info("Sync error. API: ". $message, 'api');
            $errors['Code error'] = $message;
        }

        return compact('errors', 'response');
    }
}