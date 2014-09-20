<?php
/**
 * This file contains class::ConfigurationCategory
 * @package Runalyze\Parameter
 */
/**
 * Configuration category
 * @author Hannes Christiansen
 * @package Runalyze\Configuration
 */
abstract class ConfigurationCategory {
	/**
	 * Handles
	 * @var ConfigurationHandle[]
	 */
	private $Handles;

	/**
	 * User id
	 * @todo use instead a user object
	 * @var int
	 */
	private $UserID = null;

	/**
	 * Constructor
	 * 
	 * To load values from database, make sure to call
	 * <code>$Category->setUserID($id);</code>
	 * 
	 * Otherwise this object will only contain default values
	 * 
	 * @todo require database as parameter
	 */
	public function __construct() {
		$this->createHandles();
		$this->registerOnchangeEvents();
	}

	/**
	 * Set user ID
	 * @param int $id
	 */
	final public function setUserID($id) {
		if ($id !== $this->UserID) {
			$this->UserID = $id;
			$this->loadValues();
		}
	}

	/**
	 * Has user ID?
	 * @return bool
	 */
	private function hasUserID() {
		return is_int($this->UserID);
	}

	/**
	 * User ID
	 * @return int
	 */
	private function userID() {
		return (int)$this->UserID;
	}

	/**
	 * Keys
	 * @return array
	 */
	final public function keys() {
		return array_keys($this->Handles);
	}

	/**
	 * Internal key
	 * @return string
	 */
	abstract protected function key();

	/**
	 * Create values
	 */
	abstract protected function createHandles();

	/**
	 * Register onchange events
	 */
	protected function registerOnchangeEvents() {}

	/**
	 * Fieldset
	 * @return ConfigurationFieldset
	 */
	public function Fieldset() {
		return null;
	}

	/**
	 * Add handle
	 * @param ConfigurationHandle $Handle
	 */
	final protected function addHandle(ConfigurationHandle $Handle) {
		$this->Handles[$Handle->key()] = $Handle;
	}

	/**
	 * Add handle
	 * @param string $key
	 * @param Parameter $Parameter
	 */
	final protected function createHandle($key, Parameter $Parameter) {
		$this->Handles[$key] = new ConfigurationHandle($key, $Parameter);
	}

	/**
	 * Get value
	 * @param string $key
	 * @return mixed
	 * @throws InvalidArgumentException
	 */
	final protected function get($key) {
		return $this->object($key)->value();
	}

	/**
	 * Get value object
	 * @param string $key
	 * @return Parameter
	 * @throws InvalidArgumentException
	 */
	final protected function object($key) {
		if (isset($this->Handles[$key])) {
			return $this->Handles[$key]->object();
		} else {
			throw new InvalidArgumentException('Asked for unknown value key "'.$key.'" in configuration category.');
		}
	}

	/**
	 * Get handle object
	 * @param string $key
	 * @return ConfigurationHandle
	 * @throws InvalidArgumentException
	 */
	final protected function handle($key) {
		if (isset($this->Handles[$key])) {
			return $this->Handles[$key];
		} else {
			throw new InvalidArgumentException('Asked for unknown value key "'.$key.'" in configuration category.');
		}
	}

	/**
	 * Update all values from post
	 */
	final public function updateFromPost() {
		foreach ($this->Handles as $Handle) {
			$this->updateValueFromPost($Handle);
		}
	}

	/**
	 * Update value
	 * @param ConfigurationHandle $Handle
	 */
	final protected function updateValue(ConfigurationHandle $Handle) {
		if ($this->hasUserID() && !SharedLinker::isOnSharedPage()) {
			$where = '`accountid`='.$this->userID();
			$where .= ' AND `key`='.DB::getInstance()->escape($Handle->key());

			DB::getInstance()->updateWhere('conf', $where, 'value', $Handle->object()->valueAsString());
		}
	}

	/**
	 * Update value from post
	 * @param ConfigurationHandle $Handle
	 */
	private function updateValueFromPost(ConfigurationHandle $Handle) {
		$key = $Handle->key();

		if (isset($_POST[$key]) || isset($_POST[$key.'_sent'])) {
			$value = $Handle->value();

			if ($Handle->object() instanceof ParameterBool) {
				$Handle->object()->set( isset($_POST[$key]) );
			} else {
				$Handle->object()->setFromString($_POST[$key]);
			}

			if ($value != $Handle->value()) {
				$this->updateValue($Handle);
				$Handle->processOnchangeEvents();
			}
		}
	}

	/**
	 * Load values
	 */
	private function loadValues() {
		$KeysInDatabase = array();
		$Values = $this->fetchValues();

		foreach ($Values as $Value) {
			$KeysInDatabase[] = $Value['key'];

			if (isset($this->Handles[$Value['key']])) {
				$this->Handles[$Value['key']]->object()->setFromString($Value['value']);
			}
		}

		if (!FrontendShared::$IS_SHOWN && $this->hasUserID()) {
			$this->correctDatabaseFor($KeysInDatabase);
		}
	}

	/**
	 * Fetch values
	 * @return array
	 */
	private function fetchValues() {
		$Data = DB::getInstance()->query('SELECT `key`,`value` FROM '.PREFIX.'conf WHERE `accountid`="'.$this->userID().'" AND `category`="'.$this->key().'"')->fetchAll();

		return $Data;
	}

	/**
	 * Correct database
	 * @param array $KeysInDatabase
	 */
	private function correctDatabaseFor(array $KeysInDatabase) {
		$WantedKeys = array_keys($this->Handles);
		$UnusedKeys = array_diff($KeysInDatabase, $WantedKeys);
		$MissingKeys = array_diff($WantedKeys, $KeysInDatabase);

		foreach ($UnusedKeys as $Key) {
			$this->deleteKeyFromDatabase($Key);
		}

		foreach ($MissingKeys as $Key) {
			$this->insertKeyToDatabase($Key);
		}
	}

	/**
	 * Delete key from database
	 * @param string $Key
	 */
	private function deleteKeyFromDatabase($Key) {
		DB::getInstance()->exec('DELETE FROM '.PREFIX.'conf WHERE `accountid`="'.$this->userID().'" AND `category`="'.$this->key().'" AND `key`="'.$Key.'"');
	}

	/**
	 * Insert key to database
	 * @param string $Key
	 */
	private function insertKeyToDatabase($Key) {
		DB::getInstance()->insert('conf',
			array('key', 'value', 'category', 'accountid'),
			array(
				$this->Handles[$Key]->key(),
				$this->Handles[$Key]->object()->valueAsString(),
				$this->key(),
				$this->userID()
			)
		);
	}
}