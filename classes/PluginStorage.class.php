<?php

/*
 * Common class for plugins to store data
 */

define(PS_ASSOC, 0x01);
define(PS_UPDATE, 0x02);

class PluginStorage {
	
	static private $db = null;
	
	private $tablename;
	private $pluginname;
	private $range_id;
	
	private $data;
	
	function __construct($pluginname, $tablename, $range_id = null) {
		
		if (PluginStorage::$db == null) {
			PluginStorage::$db = DBManager::get();
		}
		
		$this->pluginname = $pluginname;
		$this->tablename = $tablename;
		$this->range_id = $range_id;
		
		$query = '';
		
		if ($this->range_id != null) {
			$query = PluginStorage::$db->prepare('SELECT `id`, `key`, `value` FROM ' . $this->tablename . ' WHERE pluginname = ? AND range_id = ?');
			$query->execute(array($this->pluginname, $this->range_id));
		}
		else {
			$query = PluginStorage::$db->prepare('SELECT `id`, `key`, `value`, `range_id` FROM ' . $this->tablename . ' WHERE pluginname = ?');
			$query->execute(array($this->pluginname));
		}

		$this->data = $query->fetchAll();

		if ($this->data == null)
			$this->data = array();	
	}
	
	private function dataIdx($key, $id = null, $startFrom = 0) {
		
		for ($i = $startFrom; $i < count($this->data); $i++) {
			
			if (isset($this->data[$i]['_flag'])) {
				if ($this->data[$i]['_flag'] == 'delete')
					continue;
			}
			
			if ($id != null && $key != null) {
				if ($this->data[$i]['id'] == $id && $this->data[$i]['key'] == $key)
					return $i;
			}
			else if ($id != null) {
				if ($this->data[$i]['id'] == $id)
					return $i;
			}
			else if ($key != null) {
				if ($this->data[$i]['key'] == $key)
					return $i;
			}
		}

		return -1;
	}
	
	private function validateData($key, $id, $exception = false) {
		
		if (!is_array($id)) {
			$id = array($id);
		}
		
		foreach ($id as $entry) {
			if ($this->dataIdx($key, $entry) == -1) {
				if (!$exception)
					return false;
				else 
					throw new Exception('PluginStorage validateData: could not find data with ID ' . $entry . ' and key ' . $key . '!');
			}
		}
		
		return true;
	}
	
	public function set($key, $value, $id = null, $flags = 0x00)  {

		$result = array();
		
		if (!is_string($key)) {
			throw new Exception('PluginStorage set: $key must be a string!');
		}
		
		if ($value != null && !is_array($value)) {
			$value = array($value);
		}

		if ($id != null && !is_array($id)) {
			$id = array($id);
		}

		if ($flags & PS_UPDATE) {
			
			// search & replace behaviour

			if ($value === null) {
					
				// delete
				
				if ($id == null) {
					
					$idx = 0;
						
					while (($idx = $this->dataIdx($key, null, $idx)) != -1) {

						$this->data[$idx]['_flag'] = 'delete';

						$idx++;
					}
				}
				else {
					
					foreach ($id as $entry) {
						
						$idx = $this->dataIdx($key, $entry);
						
						if ($idx == -1) {
							throw new Exception('PluginStorage set: could not find data with ID ' . $entry . '!');
						}
						
						$this->data[$idx]['_flag'] = 'delete';
					}
				}
			}
			else {

				if ($id == null) {
					$idx = $this->dataIdx($key);
					
					if ($idx == -1) {
						throw new Exception('PluginStorage set: could not find data with key ' . $key . '!');
					}
					
					$id = array($this->data[$idx]['id']);
				}
				
				if ($flags & PS_ASSOC && !is_array($value[0])) {
					$value = array($value);
				}
				
				if (count($id) != count($value)) {
					throw new Exception('PluginStorage set: $id must be of the same length as $value when given arrays!');
				}
					
				$i = 0;
					
				foreach ($id as $entry) {

					$idx = $this->dataIdx($key, $entry);

					if ($idx == -1) {
						throw new Exception('PluginStorage set: could not find data with ID ' . $entry . ' and key ' . $key . '!');
					}

					if ($flags & PS_ASSOC) {
						if (!is_array($value[$i])) {
							throw new Exception('PluginStorage set: when specifying PS_ASSOC, $value must contain arrays of IDs only!');
						}
						
						$this->validateData(null, $value[$i], true);
						
						$value[$i] = implode(",", $value[$i]);
					}
					
					if ($this->data[$idx]['value'] != $value[$i]) {
						$this->data[$idx]['value'] = $value[$i];
						$this->data[$idx]['_flag'] = 'update';
					}

					$i++;
				}
			}
	
		}
		else {
			
			if ($value != null) {

				if ($flags & PS_ASSOC && !is_array($value[0])) {
					$value = array($value);
				}
				
				foreach ($value as $entry) {

					if ($flags & PS_ASSOC) {
						if (!is_array($entry)) {
							throw new Exception('PluginStorage set: when specifying PS_ASSOC, $value must contain arrays of IDs only!');
						}
						
						$this->validateData(null, $entry, true);
						
						$entry = implode(",", $entry);
					}
					
					$query = '';
		
					if ($this->range_id != null) {
						$query = PluginStorage::$db->prepare('INSERT INTO ' . $this->tablename . ' (`key`, `value`, `range_id`, `pluginname`) VALUES (?, ?, ?, ?)');
						$query->execute(array($key, $entry, $this->range_id, $this->pluginname));
					}
					else {
						$query = PluginStorage::$db->prepare('INSERT INTO ' . $this->tablename . ' (`key`, `value`, `pluginname`) VALUES (?, ?, ?)');
						$query->execute(array($key, $entry, $this->pluginname));
					}

					$new_id = PluginStorage::$db->lastInsertId();
					$new_data = array('id' => $new_id, 'key' => $key, 'value' => $entry);
					
					array_push($this->data, $new_data);
					array_push($result, $new_id);
				}
			}
			else {
				if ($this->range_id != null) {
					$query = PluginStorage::$db->prepare('INSERT INTO ' . $this->tablename . ' (`key`, `range_id`, `pluginname`) VALUES (?, ?, ?)');
					$query->execute(array($key, $this->range_id, $this->pluginname));
				}
				else {
					$query = PluginStorage::$db->prepare('INSERT INTO ' . $this->tablename . ' (`key`, `pluginname`) VALUES (?, ?)');
					$query->execute(array($key, $this->pluginname));
				}
				
				$new_id = PluginStorage::$db->lastInsertId();
				$new_data = array('id' => $new_id, 'key' => $key, 'value' => null);
					
				array_push($this->data, $new_data);
				array_push($result, $new_id);
			}
			
		}
		
		return $result;
	}
	
	public function get($key, $id = null, $flags = 0x00) {
		
		$result = array();
		
		if (!is_string($key)) {
			throw new Exception('PluginStorage get: $key must be a string!');
		}

		if ($id == null) {
				
			$idx = 0;

			while (($idx = $this->dataIdx($key, null, $idx)) != -1) {

				$get_data = $this->data[$idx];
				
				if ($flags & PS_ASSOC) {
					
					// retrieve associations
					
					$assoc_id = array();
					
					if (trim($get_data['value']) != "") {
						$assoc_id = explode(",", $get_data['value']);
					}
					
					$this->validateData(null, $assoc_id, true);
					
					$assoc_data = array();
					
					foreach ($assoc_id as $entry) {
						array_push($assoc_data, $this->data[$this->dataIdx(null, $entry)]);
					}
					
					$get_data['value'] = $assoc_data;
				}

				array_push($result, $get_data);
				
				$idx++;
			}
		}
		else {
			
			if (!is_array($id)) {
				$id = array($id);
			}
			
			foreach ($id as $entry) {
				
				$idx = $this->dataIdx($key, $entry);
				
				if ($idx == -1) {
					throw new Exception('PluginStorage get: could not find data with ID ' . $entry . '!');
				}
				
				$get_data = $this->data[$idx];
				
				if ($flags & PS_ASSOC) {
					$assoc_id = array();
					
					if (trim($get_data['value']) != "") {
						$assoc_id = explode(",", $get_data['value']);
					}
					
					$this->validateData(null, $assoc_id, true);
					
					$assoc_data = array();
					
					foreach ($assoc_id as $entry2) {
						array_push($assoc_data, $this->data[$this->dataIdx(null, $entry2)]);
					}
					
					$get_data['value'] = $assoc_data;
				}
				
				array_push($result, $get_data);
			}
		}
		
		return $result;
	}
	
	public function save() {
		
		$new_data = array();
		
		foreach ($this->data as $entry) {
			
			if (isset($entry['_flag'])) {
				
				if ($entry['_flag'] == 'update') {

					$query = PluginStorage::$db->prepare('UPDATE ' . $this->tablename . ' SET value = ? WHERE id = ?');
					$query->execute(array($entry['value'], $entry['id']));

					unset($entry['_flag']);
				}
				else if ($entry['_flag'] == 'delete') {

					$query = PluginStorage::$db->prepare('DELETE FROM ' . $this->tablename . ' WHERE id = ?');
					$query->execute(array($entry['id']));
					
					continue;
				}
			}
			
			array_push($new_data, $entry);
		}
		
		$this->data = $new_data;
	}
	
	public function getRangeId() {
		return $this->range_id;
	}
	
	public function getPluginname() {
		return $this->pluginname;
	}
	
	public function getTablename() {
		return $this->tablename;
	}
}


?>