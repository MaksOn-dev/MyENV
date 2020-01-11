<?php

namespace Maks\EnvParser;

class MyENV
{
    /**
     * Символ, с которого начинается комментарий
     * @var string
     */
    public static $optionsComment = "#";

    /**
     * Оператор присвоения ключа и значения
     * @var string
     */
    public static $optionsKeyValueDelimiter = '=';

    /**
     * Определяет, будет ли происходить изменение типов значений
     * @var bool
     */
    public static $optionsChangeValuesType = true;

     /**
     * Названия найденных окружений
     * @var array
     */
    private static $environmentsNames = array();

    /**
     * массив значений окружений вида
     * ['env1' => ['key1' => 'value', ... 'keyN' => 'value'], 'env2' => ...]
     * @var array
     */
    private static $environments;


    private function __construct(){}

    /**
     * Загружает окружения и извлекает значения данных окружений
     */
    public static function load()
    {
        self::$environmentsNames = self::getEnvironments();

        self::$environments = self::parseKeysValues(self::$environmentsNames);
    }

    /**
     * Возвращает массив со значениями окружения
     * @param $env
     * @return array
     */
    public static function get($env)
    {
        $envName = self::getNormalizedEnvName($env);

        if(self::checkEnv($envName)){
            return self::$environments[$envName];
        }
        return ;
    }

    public static function getAllEnv()
    {
        return self::$environmentsNames;
    }

    /**
     * Возвращает названия найденных окружений
     * @return array
     */
    private static function getEnvironments()
    {
        $arr = glob("./*.env");
        return array_map(function($item){
            $substr = substr($item, 2);
            return strtolower($substr);
        }, $arr);
    }

    /**
     * Возвращает строки содержащие ключ => значение
     * @param $env
     * @return array
     */
    private static function getLines($env)
    {
        $content = file_get_contents($env);

        $lines = explode("\n", str_replace(array("\r\n", "\n\r", "\r"), "\n", $content));

        $lines = self::getCleanLines($lines);

        return $lines;
    }

    /**
     * Возвращает массив строк, очищенных от пустых строк и от строк с комментариями
     * @param $strings
     * @return array
     */
    private static function getCleanLines($lines)
    {
        $lines = array_diff($lines, array(''));

        $lines = array_filter($lines, function($item){
            $item = trim($item);
            return !(($item[0]) === self::$optionsComment) && (strpos($item, self::$optionsKeyValueDelimiter));
        });

        return $lines;
    }

    /**
     * Извлекает пары ключ => значение из окружений
     */
    private static function parseKeysValues($environmentsNames)
    {
        $environments = array();
        foreach($environmentsNames as $env){
            $lines = self::getLines($env);
            $resultKeysValues = array();

            foreach($lines as $line){
                $pos = strpos($line, self::$optionsKeyValueDelimiter);

                if(!$pos) continue;

                $keyValue = explode(self::$optionsKeyValueDelimiter, $line);

                if(count($keyValue) > 2) $keyValue = array_merge(array($keyValue[0]), array($keyValue[count($keyValue)-1]));

                $key = self::getKey($keyValue[0]);
                $value = self::getValue($keyValue[1]);

                $resultKeysValues[$key] = $value;
            }
            $environments[$env] = $resultKeysValues;
        }
        return $environments;
    }

    /**
     * Возвращает ключ из строки
     * @param $str
     * @return mixed
     */
    private static function getKey($str)
    {
        $str = trim($str);
        $arr = explode(' ', $str);
        $key = $arr[count($arr)-1];
        return $key;
    }

    /**
     * Возвращает значение из строки
     * @param $str
     * @return array|bool|false|float|int|string|null
     */
    private static function getValue($str)
    {
        $str = trim($str);
        if(self::$optionsComment) $str = self::getValueWithoutComment($str);

        if(self::$optionsChangeValuesType) $str = self::getChangedType($str);

        return $str;
    }

    /**
     * Изменяет тип строки если это возможно
     * @param $str
     * @return array|bool|false|float|int|string|null
     */
    private static function getChangedType($str)
    {
        switch (true) {
            case (string)(int)$str == $str:
                return (int)$str;
            case (string)(float)$str == $str:
                return (float)$str;
            case $str == "true":
                return true;
            case $str == "false":
                return false;
            case $str == "NULL":
                return NULL;
            case $str[0] == "[":
                $str = substr($str, 1, strripos($str,']')-1);
                return explode(',', $str);
        }
        return $str;
    }

    /**
     * Проверяет, закрыты ли кавычки
     * @param $str
     * @return bool
     */
    private static function isCloseQuotes($str)
    {
        $quotes = substr_count($str, "'") + substr_count($str, '"') + substr_count($str, "`");

        return (bool) ($quotes % 2);
    }

    /**
     * Возвращает значение без комментария
     * @param $str
     * @return false|string
     */
    private static function getValueWithoutComment($str)
    {
        $commentCharPos = strripos($str, ' '.self::$optionsComment);

        if(!$commentCharPos){
            $result = $str;
        }else{
            $valueBeforeComment = substr($str, 0, $commentCharPos);

            $result = !self::isCloseQuotes($valueBeforeComment) ? substr($str, 0, $commentCharPos) : $str;
        }

        return $result;
    }

    /**
     * Проверяет существование переменного окружения с указанным именем
     * @param $env
     * @return bool
     */
    private static function checkEnv($env)
    {
        return boolval(in_array($env, self::$environmentsNames));
    }

    /**
     * Приводит название окружения к нормализованному виду
     * @param $name
     * @return string
     */
    private static function getNormalizedEnvName($name)
    {
        $name = strtolower($name);
        if(!strpos($name, '.env')) $name =  $name . '.env';
//        echo $name;
        return $name;
    }
}