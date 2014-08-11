<?php

class restLogClass extends ObjectModel
{
    /** @var integer rest id */
    public $id;

    /** @var integer rest app id */
    public $id_rest_app;

    /** @var string rest method */
    public $method;

    /** @var string rest datetime */
    public $date;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'rest_log',
        'primary' => 'id_rest_log',
        'fields' => array(
            'id_rest_app' =>		    array('type' => self::TYPE_INT, 'validate' => 'isunsignedInt', 'required' => true),
            'method' =>				    array('type' => self::TYPE_STRING, 'required' => true),
            'date' =>				    array('type' => self::TYPE_DATE, 'validate' => 'isDateFormat', 'required' => true)
        ),
        'associations' => array(
            'rest_app' =>				array('type' => self::HAS_MANY, 'field' => 'id_rest_app', 'object' => 'restAppClass', 'association' => 'rest_app'),
        ),
    );

    public function copyFromPost()
    {
        foreach ($_POST AS $key => $value) {

            if (array_key_exists($key, $this)
                && $key != 'id_' . $this->table) {

                $this->{$key} = $value;
            }
        }
    }
}
