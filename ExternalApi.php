<?php

namespace common\components\external;

abstract class ExternalApi
{
    // Массив ошибок
    public $errors = [];

    // Контейнер для хранения экземпляров классов наследников
    protected static $_instances = [];

    // Конструктор (закрыт для исключения прямого создания инстанса)
    protected function __construct() {}

    // Получение инстанса
    public final static function getInstance() {
        // Вызываемый класс
        $class = get_called_class();
        // Если экземпляр класса еще не создан
        if (!isset(self::$_instances[$class])) {
            self::$_instances[$class] = new $class();
        }
        //
        return self::$_instances[$class];
    }

    // Отправка данных по визитам
    abstract public function sendData($data);
}