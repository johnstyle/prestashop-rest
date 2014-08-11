<?php

class restAppClass extends ObjectModel
{
	/** @var integer rest id*/
	public $id;

    /** @var integer rest shop id */
    public $id_shop;

	/** @var string rest name */
	public $name;

	/** @var string rest public key */
	public $public_key;

    /** @var string rest private key */
    public $private_key;

    /** @var string rest token key */
    public $token_key;

    /** @var string rest active */
    public $active;

    /** @var string rest last connection */
    public $last_connection;

	/**
	 * @see ObjectModel::$definition
	 */
	public static $definition = array(
		'table' => 'rest_app',
		'primary' => 'id_rest_app',
		'fields' => array(
			'id_shop' =>				array('type' => self::TYPE_INT, 'validate' => 'isunsignedInt', 'required' => true),
            'name' =>				    array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true),
            'public_key' =>				array('type' => self::TYPE_STRING, 'required' => true),
            'private_key' =>	        array('type' => self::TYPE_STRING, 'required' => true),
            'token_key' =>	            array('type' => self::TYPE_STRING),
            'active' =>	                array('type' => self::TYPE_BOOL, 'validate' => 'isBool', 'required' => true),
            'last_connection' =>	    array('type' => self::TYPE_DATE, 'validate' => 'isDateFormat')
		)
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

    public function setLog($method)
    {
        $log = new \restLogClass();
        $log->id_rest_app = $this->id;
        $log->method = $method;
        $log->date = date('Y-m-d H:i:s');

        return $log->save();
    }
}
