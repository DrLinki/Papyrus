<?php
namespace App;
use DateTime;
use PDO;
use PDOException;
use ReflectionClass;
use stdClass;
use App\Inc\Form;
use Config\Conf;

class Repository{

	static  array $connexions = [];
	private string $conf = 'default';
	public string|bool $table = false;
	private PDO $db;
	public string $primaryKey = 'id';
	public int|string $id;
	private array $errors = [];
	public Form $form;
	public array $extras = [];
	public array $relatives = [];
	public array $validate = [];
	
	/**
	 * Constructor of the class that initializes the table.
	 * Connects to the database and retrieves configuration parameters.
	 * @return void This method returns nothing.
	 **/
	public function __construct(){
		// If the table property is not set, initialize it with the class name in lowercase
		if($this->table === false){
			$this->table = strtolower(substr(basename(str_replace('\\', '/', get_class($this))), 0, -10));
		}

		// Preload the Entity associated to the Repository
    	require_once SRC . DS . 'Entity' . DS . ucfirst($this->table) . '.php';

		// Determines connection configuration based on IP address
		if($_SERVER['REMOTE_ADDR'] == '127.0.0.1' || $_SERVER['REMOTE_ADDR'] == '::1') {
			$this->conf = 'local';
		}
		
		$conf = Conf::$databases[$this->conf];
		
		// Checks if a database connection already exists for this configuration
		if(isset(Repository::$connexions[$this->conf])){
			$this->db = Repository::$connexions[$this->conf];
			return;
		}
		
		try {
			// Creates a new PDO connection to the database
			$pdo = new PDO(
				'mysql:host=' . $conf['host'] . ';dbname=' . $conf['database'] . ';',
				$conf['login'],
				$conf['password'],
				array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8')
			);
			
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
			Repository::$connexions[$this->conf] = $pdo;
			$this->db = $pdo;
			
		} catch(PDOException $e) {
			// Handle database connection exceptions
			if(Conf::$debug >= 1) {
				die($e->getMessage());
			} else {
				die('Unable to connect to database');
			}
		}
	}

	/**
	 * Starts a transaction in the database.
	 * @return void This method returns nothing.
	 **/
	public function beginTransaction(): void {
		$this->db->beginTransaction();
	}

	/**
	 * Validates a current transaction in the database.
	 * @return void This method returns nothing.
	 **/
	public function commit(): void {
		$this->db->commit();
	}

	/**
	 * Cancels a transaction in progress in the database.
	 * @return void This method returns nothing.
	 **/
	public function rollBack(): void {
		$this->db->rollBack();
	}

	/**
	 * Performs a SELECT query on the table and returns the results.
	 * @param array|null $req (optional) Array of query parameters, including 'fields', 'conditions', 'sort', 'limit', 'extras', 'relations', and 'call'.
	 * @return array Query results as an array of objects, or false if the query fails.
	 **/
	public function find(array $req = null): array {
		$sql = 'SELECT ';
		$sql .= isset($req['fields']) ? (is_array($req['fields']) ? implode(', ', $req['fields']) : $req['fields']) : '*';

		$forbidden_alias = [
			'Character',
			'Order',
			'Group',
			'Rank'
		];
		$tableAlias = in_array(get_class($this), $forbidden_alias) ? '' : ' as ' . strtolower(substr(basename(str_replace('\\', '/', get_class($this))), 0, -10));
		$sql .= ' FROM ' . Conf::$databases[$this->conf]['prefix'] . $this->table . $tableAlias . ' ';

		// Construction of the condition
		if (isset($req['conditions'])) {
			$sql .= 'WHERE ';
			if (!is_array($req['conditions'])) {
				$sql .= $req['conditions'];
			} else {
				$cond = array_map(function($k, $v) {
					return is_null($v) ? "$k IS NULL" : "$k=" . (is_numeric($v) ? $v : $this->db->quote($v));
				}, array_keys($req['conditions']), $req['conditions']);
				$sql .= implode(' AND ', $cond);
			}
		}
		if (isset($req['sort'])) {
			$sql .= ' ORDER BY ' . $req['sort'] . ' ';
		}
		if (isset($req['limit'])) {
			$sql .= 'LIMIT ' . $req['limit'];
		}

		$pre = $this->db->prepare($sql);
		$pre->execute();
		$res = $pre->fetchAll(PDO::FETCH_OBJ);

		if (!empty($res)) {
			foreach ($res as &$row) {
				unset($row->password);
			}
		}

		// Retrieving the fields present in the “Extra” table
		if (!empty($res) && !empty($req['extras'])) {
			foreach ($res as &$row) {
				if (isset($row->id)) {
					$this->findExtras($row);
				}
			}
		}

		// Retrieving parents and children of entities
		if (!empty($res) && !empty($req['relations']) && isset($this->relatives) && !empty($this->relatives)) {
			$relations = is_array($req['relations']) ? $req['relations'] : explode(',', $req['relations']);
			foreach ($res as &$row) {
				if (isset($row->id)) {
					$this->findRelatives($row, $relations);
				}
			}
		}

		// Executing functions called entities
		if (!empty($res) && isset($req['call'])) {
			$this->call($req['call'], $res, $this->table);
		}

		return $res;
	}

	/**
	 * Performs specific functions on the query results.
	 * @param string|array $call Functions to call on the results, in string or array form.
	 * @param array|object $res Query results on which functions will be executed.
	 * @param string $table Name of the table to which the functions belong.
	 * @return void This method returns nothing.
	 **/
	public function call(string|array $call, array|object $res, string $table): void {
		if (is_array($call)) {
			$functions = $call;
		} elseif (strpos($call, ',')) {
			$functions = explode(',', $call);
		} else {
			$functions = [$call];
		}
		foreach ($functions as &$function) {
			$function = trim($function);
			if (method_exists($this->table, $function)) {
				if (is_array($res)) {
					foreach ($res as &$row) {
						$this->$function($row);
					}
				} else {
					$this->$function($res);
				}
			}
		}
	}

	/**
	 * Finds and returns the first result matching the query.
	 * @param array|null $req Query parameters.
	 * @return object|null First result matching the query, or null if no results are found.
	 **/
	public function findFirst(array|null $req = null): object|bool {
		return current($this->find($req));
	}

	/**
	 * Finds and returns the total number of records matching the given conditions.
	 * @param array|string|null $conditions (optional) Conditions to filter records.
	 * @return int The total number of records matching the conditions.
	 **/
	public function findCount(array|string|null $conditions = null): int {
		$res = $this->findFirst([
			'fields'     => 'COUNT(' . $this->primaryKey . ') as count',
			'conditions' => $conditions
		]);

		return $res->count;
	}

	/**
	 * Finds and returns records at specified levels.
	 * @param array|null $req Query parameters, including 'conditions', 'sort' and 'limit'.
	 * @param int|null $parent_id The ID of the parent to search for.
	 * @return array The list of records found including levels.
	 **/
	public function findByLevels(?array $req = null, ?int $parent_id = null): array {
		$rqt = $req ?? [];
		if (isset($rqt['conditions'])) {
			if (is_array($rqt['conditions'])) {
				$rqt['conditions']['parent_id'] = $parent_id;
			} elseif ($parent_id !== null) {
				$rqt['conditions'] .= " AND parent_id = $parent_id";
			} else {
				$rqt['conditions'] .= " AND parent_id IS NULL";
			}
		} else {
			$rqt['conditions'] = ['parent_id' => $parent_id];
		}

		$res = $this->find($rqt);
		$cfn = $this->getPluralTableName();
		foreach ($res as $k => $v) {
			$res[$k]->{$cfn} = $this->findByLevels($req, $res[$k]->id);
		}

		return $res;
	}

	/**
	 * Finds and returns a record based on its parents.
	 * @param int|null $id The ID of the record to search for.
	 * @param array|null $req Query parameters, including 'conditions', 'sort' and 'limit'.
	 * @param string $field The name of the field to store the parents.
	 * @return object|null The record found or null if not found.
	 **/
	public function findByParents(?int $id = null, ?array $req = null, string $field = 'parent'): ?object {
		if (empty($req)) {
			$req = ['conditions' => ['id' => $id]];
		} elseif (isset($req['conditions']) && !empty($req['conditions'])) {
			if (is_array($req['conditions'])) {
				$req['conditions']['id'] = $id;
			} else {
				$req['conditions'] .= " id = $id";
			}
		}

		$res = $this->findFirst($req);

		if (isset($res->parent_id) && !empty($res->parent_id)) {
			$res->{$field} = $this->findByParents($res->parent_id, $req, $field);
		}

		return $res;
	}

	/**
	 * Finds and returns related records, sorted in descending or ascending order.
	 * @param int|null $id The ID of the record to search for.
	 * @param array|null $req Query parameters, including 'conditions', 'sort' and 'limit'.
	 * @param string $field The name of the field to store the parents.
	 * @param bool $revert Indicates whether results should be returned in descending order (true by default).
	 * @return array Parented records sorted.
	 **/
	public function findByParentsSorted(?int $id = null, ?array $req = null, string $field = 'parent', bool $revert = true): array {
		$rows = $this->findByParents($id, $req, $field);
		$arr = [];
		while (isset($rows) && !empty($rows)) {
			$arr[] = $rows;
			if (isset($arr[count($arr) - 1]->{$field}) && !empty($arr[count($arr) - 1]->{$field})) {
				$parent = $arr[count($arr) - 1]->{$field};
			} else {
				$parent = null;
			}
			unset($arr[count($arr) - 1]->{$field});
			$rows = $parent;
		}
		if (!$revert) {
			$arr_desc = [];
			for ($i = count($arr) - 1; $i >= 0; $i--) {
				$arr_desc[] = $arr[$i];
			}
			return $arr_desc;
		}
		return $arr;
	}

	/**
	 * Saves data to the database.
	 * @param object $data The data to record.
	 * @return bool Returns true if the save is successful, otherwise false.
	 **/
	public function save(object $data): bool {
		$key = $this->primaryKey;
		$fields = [];
		$relatives = [];
		$children = [];
		$d = [];
		$sql = '';

		foreach ($data as $k => $v) {
			if ($this->column_exists($k) && $this->column_nullable($k) && empty($v) && $v != 0 && $v !== false) {
				$data->$k = null;
			}
		}
		foreach ($data as $k => $v) {
			// Allocation of Extras
			if ($k == 'extra' && is_array($v)) {
				$extras = $data->extra;
				unset($data->extra);
			// Allocation of Relatives
			} elseif (isset($this->extras) && in_array($k, $this->extras)) {
				$extras[$k] = $v;
				unset($data->{$k});
			} elseif (is_object($v) || is_array($v)) {
				$relatives[$k] = $v;
				unset($data->{$k});
			} elseif ($k != $this->primaryKey) {
				$fields[] = "$k=:$k";
				$d[":$k"] = $v;
			} elseif (!empty($v)) {
				$d[":$k"] = $v;
				if ($k == 'member_id_insert' || $k == 'member_id_update') {
					$member_id = $v;
				}
			}
		}
		if (isset($data->$key) && !empty($data->$key)) {
			$sql = 'UPDATE ' . Conf::$databases[$this->conf]['prefix'] . $this->table . ' SET ' . implode(',', $fields) . ' WHERE ' . $key . '=:' . $key;
			$this->id = $data->$key;
			$action = 'update';
		} else {
			$sql = 'INSERT INTO ' . Conf::$databases[$this->conf]['prefix'] . $this->table . ' SET ' . implode(',', $fields);
			$action = 'insert';
		}

		$pre = $this->db->prepare($sql);
		$pre->execute($d);
		
		if ($action == 'insert') {
			$this->id = $this->db->lastInsertId();
		}
		
		// Saving Extras
		if (isset($extras) && !empty($extras)) {
			$this->saveExtras($extras, isset($member_id) ? $member_id : null);
			$data->extra = $extras;
		}
		// Backup | reassignment of Relatives
		if (!empty($relatives)) {
			foreach ($relatives as $k => $v) {
				$data->{$k} = $v;
			}
		}
		
		return true;
	}

	/**
	 * Deletes an entry from the database based on its identifier.
	 * @param int $id The identifier of the entry to delete.
	 * @return void This method returns nothing.
	 **/
	public function delete(int $id): void {
		$sql = 'DELETE FROM ' . Conf::$databases[$this->conf]['prefix'] . $this->table . ' WHERE ' . $this->primaryKey . ' = :id';
		$pre = $this->db->prepare($sql);
		$pre->execute([':id' => $id]);
	}

	/**
	 * Validates data based on validation rules defined in the $validate property.
	 * @param stdClass $data The data to validate.
	 * @return bool true if the data is valid, otherwise false with errors in the $errors property.
	 **/
	public function validates(stdClass $data): bool {
		$errors = [];

		if (isset($this->validate)) {
			foreach ($this->validate as $field => $rules) {
				if (isset($rules['rule'])) {
					$value = $data->$field ?? null;

					// Retrieving rule-based validation limits
					$limits = $this->getRegExLimits($rules['rule']);
					$rules += [
						'min' => $limits['min'] ?? null,
						'max' => $limits['max'] ?? null,
					];

					// Field validation
					if (substr($rules['rule'], -3) === '{1}' && (empty($value) || $value === null)) {
						$errors[$field] = 'The <u>' . $field . '</u> field must be filled in.';
					} elseif (isset($value)) {
						if (isset($rules['min']) && $rules['min'] > strlen($value)) {
							$errors[$field] = 'The <u>' . $field . '</u> field must contain at least <strong>' . $rules['min'] . '</strong> characters.';
						} elseif (isset($rules['max']) && $rules['max'] < strlen($value)) {
							$errors[$field] = 'The <u>' . $field . '</u> field must contain a maximum of <strong>' . $rules['max'] . '</strong> characters.';
						} elseif (isset($rules['rule']) && !preg_match('/^' . $rules['rule'] . '$/', $value)) {
							$errors[$field] = $rules['message'] ?? 'The <u>' . $field . '</u> field is invalid.';
						}
					}
				}
			}

			$this->errors = $errors;

			return empty($errors);
		} else {
			return true;
		}
	}

	/**
	 * Gets the bounds of a regular expression.
	 * @param string $regex The regular expression.
	 * @return array|null The min and max limits.
	 **/
	public function getRegExLimits(string $regex): ?array {
		// Regular expressions for corrections
		$corrections = [
			"([\)])([a-zA-Z0-9]+)([\(])",
			"([a-zA-Z0-9]+)([\(])",
			"([\)])([a-zA-Z0-9]+)"
		];

		// Fixing regular expressions
		$regex = preg_replace("/\[(.*?)\]/i", '(1)', $regex);
		foreach ($corrections as $correction) {
			preg_match_all("/" . $correction . "/i", $regex, $matches);
			if (!empty($matches[count($matches) - 2])) {
				foreach ($matches[count($matches) - 2] as $text) {
					$replacement = '((1)';
					for ($i = 1; $i < strlen(trim($text)); $i++) {
						$replacement .= '(1)';
					}
					$replacement .= ')';
					$regex = str_replace($text, '(' . $replacement . ')', $regex);
				}
			}
		}

		// Replacing specific symbols with numbers of repetitions
		$regex = str_replace('?', '{0,1}', $regex);
		$regex = str_replace('+', '{1,}', $regex);
		$regex = str_replace('*', '{0,}', $regex);
		$regex = str_replace('){', ')*{', $regex);
		$regex = str_replace(')(', ')+(', $regex);

		// Retrieving min and max values
		preg_match_all("/\{(.*?)\}/i", $regex, $matches);
		if (!empty($matches[1])) {
			$min = [];
			$max = [];

			foreach ($matches[1] as $match) {
				if (strpos($match, ',') !== false) {
					$lengths = explode(',', $match);
					$min[] = !empty($lengths[0]) ? $lengths[0] : 0;
					$max[] = !empty($lengths[1]) ? $lengths[1] : 1000000;
				} else {
					$min[] = $match;
					$max[] = $match;
				}
			}

			// Operations to evaluate min and max values
			foreach (['min', 'max'] as $length) {
				$operation = str_replace('{', '', str_replace('}', '', preg_replace($matches[0], ${$length}, $regex)));
				foreach ($corrections as $correction) {
					preg_match_all("/" . $correction . "/i", $operation, $replacements);
					if (!empty($replacements[1])) {
						foreach ($replacements[1] as $replacement) {
							$operation = str_replace($replacement, '(' . strlen(trim($replacement)) . ')', $operation);
						}
					}
					$operation = str_replace(')(', ')+(', $operation);
				}
				// Evaluation of the operation to obtain the value
				if (empty(preg_replace("/[0-9]/i", '', $operation))) {
					eval('$' . $length . ' = (' . $operation . ');');
				}
			}

			// Deleting the max value if it is equal to 1000000
			if (isset($max) && $max >= 1000000) {
				unset($max);
			}

			return ['min' => isset($min) ? $min : null, 'max' => isset($max) ? $max : null];
		}

		return null;
	}

	/**
	 * Retrieves additional data (extras).
	 * @param object $res The result object to add the extras to.
	 * @return void
	 **/
	public function findExtras(object $res): void {
		$sql = "SELECT field_key, field_value FROM " . Conf::$databases[$this->conf]['prefix'] . "extra WHERE class_name = ? AND class_row_id = ?";
		$pre = $this->db->prepare($sql);
		$pre->execute([$this->table, $res->id]);
		$extras = $pre->fetchAll(PDO::FETCH_OBJ);

		if (!empty($extras)) {
			foreach ($extras as &$extra) {
				$res->{$extra->field_key} = $extra->field_value;
			}
		}
	}

	/**
	 * Saves additional data (extras).
	 * @param array $extras An associative array containing the extras to save.
	 * @param int|null $member_id The ID of the associated member, or null if there is no member.
	 * @return void This method returns nothing.
	 **/
	private function saveExtras(array $extras, ?int $member_id = null): void {
		$date = date('Y-m-d H:i:s');
		
		foreach ($extras as $k => $v) {
			if (!empty($v)) {
				$extra = new stdClass;
				$extra->class_name = $this->table;
				$extra->class_row_id = $this->id;
				$extra->field_key = $k;
				$extra->field_value = $v;
				
				$sql = "SELECT * FROM " . Conf::$databases[$this->conf]['prefix'] . "extra WHERE class_name = ? AND class_row_id = ? AND field_key = ?";
				$pre = $this->db->prepare($sql);
				$pre->execute([$extra->class_name, $extra->class_row_id, $k]);
				$row = current($pre->fetchAll(PDO::FETCH_OBJ));
				
				$fields = [];
				$d = [];

				foreach ($extra as $extra_k => $extra_v) {
					$fields[] = "$extra_k=:$extra_k";
					$d[":$extra_k"] = $extra_v;
				}
				
				if (empty($row)) {
					$action = 'insert';
					$sql = "INSERT INTO " . Conf::$databases[$this->conf]['prefix'] . "extra ";
				} else {
					$action = 'update';
					$sql = "UPDATE " . Conf::$databases[$this->conf]['prefix'] . "extra ";
				}
				
				$fields[] = "date_$action=:date_$action";
				$d[":date_$action"] = $date;
				if ($member_id) {
					$fields[] = "member_id_$action=:member_id_$action";
					$d[":member_id_$action"] = $member_id;
				}
				
				$sql .= " SET " . implode(', ', $fields);
				if (!empty($row)) {
					$sql .= " WHERE id = ?";
					$d[':id'] = $row->id;
				}
				
				$pre = $this->db->prepare($sql);
				$pre->execute($d);
				
				unset($extra);
			}
		}
	}

	public function findRelatives2($res, $relations){
		if(!empty($relations)){
			foreach($relations as &$relation){
				$relation = trim($relation);
				
				if(isset($this->relatives[$relation])){
					$fields = isset($this->relatives[$relation]['fields'])?$this->relatives[$relation]['fields']:'*';
					$sql = 'SELECT '.$fields.' FROM '.Conf::$databases[$this->conf]['prefix'].$this->relatives[$relation]['class'].' ';
					
					if(in_array($this->relatives[$relation]['relation'][0], array('parents', 'children')) && is_array($this->relatives[$relation]['relation'][1])){
						$sql.= 'table1 ';
						$sql.= 'INNER JOIN '.Conf::$databases[$this->conf]['prefix'].$this->relatives[$relation]['relation'][1][0].' table2 ON table1.id = table2.'.$this->relatives[$relation]['relation'][1][1].' AND table2.'.$this->relatives[$relation]['relation'][1][2].' = '.$res->id.' ';
					}
					$sql.= 'WHERE ';
					if(isset($this->relatives[$relation]['conditions'])){
						
						if(!is_array($this->relatives[$relation]['conditions'])){
							$sql.= $this->relatives[$relation]['conditions'];
						}else{
							$cond = array();
							foreach($this->relatives[$relation]['conditions'] as $k=>$v){
								if(is_null($v)){
									$cond[] = "$k IS NULL";
								}else{
									if(!is_numeric($v)){
										$v = $this->db->quote($v);
									}
									$cond[] = "$k=$v";
								}
							}
							$sql.= implode(' AND ', $cond);
						}
						$sql.= ' AND ';
					}
					
					if($this->relatives[$relation]['relation'][0] == 'parent' || $this->relatives[$relation]['relation'][0] == 'parents'){


						if(!is_array($this->relatives[$relation]['relation'][1])){
							if(!empty($res->{$this->relatives[$relation]['relation'][1]}))
								$sql.= 'id = '.$res->{$this->relatives[$relation]['relation'][1]};
							else
								$emptyField = true;
						}else{
							if(!empty($res->id))
								$sql.= 'id IN(SELECT '.$this->relatives[$relation]['relation'][1][2].' FROM '.Conf::$databases[$this->conf]['prefix'].$this->relatives[$relation]['relation'][1][0].' WHERE '.$this->relatives[$relation]['relation'][1][1].' = '.$res->id.')';
							else
								$emptyField = true;
						}
					}elseif($this->relatives[$relation]['relation'][0] == 'child' || $this->relatives[$relation]['relation'][0] == 'children'){
						if(!is_array($this->relatives[$relation]['relation'][1]))
							$sql.= $this->relatives[$relation]['relation'][1].' = '.$res->id;
						else
							$sql.= 'id IN(SELECT '.$this->relatives[$relation]['relation'][1][1].' FROM '.Conf::$databases[$this->conf]['prefix'].$this->relatives[$relation]['relation'][1][0].' WHERE '.$this->relatives[$relation]['relation'][1][2].' = '.$res->id.')';
					}
					$sql.= ' GROUP BY id';

					

					if(!isset($emptyField)){
						$pre = $this->db->prepare($sql);
						$pre->execute();
						$relatives = $pre->fetchAll(PDO::FETCH_OBJ);

					}else{
						unset($emptyField);
						unset($relatives);
					}
					if(!empty($relatives)){
						if(($this->relatives[$relation]['relation'][0] == 'child' || $this->relatives[$relation]['relation'][0] == 'parent') && !is_array($this->relatives[$relation]['relation'][1]) && count($relatives) == 1)
							$res->{$relation} = $relatives[0];
						else
							$res->{$relation} = $relatives;
						
						
						if(isset($this->relatives[$relation]['call']) && !empty($this->relatives[$relation]['call'])){
							if(!is_array($this->relatives[$relation]['relation'][1]))
								$this->call($this->relatives[$relation]['call'], $res->{$relation}, $this->relatives[$relation]['relation'][1]);
							else
								$this->call($this->relatives[$relation]['call'], $res->{$relation}, $this->relatives[$relation]['relation'][1][0]);
						}
					}
				}
			}
		}
	}

	/**
	 * Finds relative entities based on specified relationships.
	 * @param mixed $res The entity to find relationships for.
	 * @param array $relations An array containing the relationships to search for.
	 * @return void This method returns nothing.
	 **/
	public function findRelatives($res, $relations) {
		if (!empty($relations)) {
			foreach ($relations as &$relation) {
				$relation = trim($relation);
				
				if (isset($this->relatives[$relation])) {
					$fields = isset($this->relatives[$relation]['fields']) ? $this->relatives[$relation]['fields'] : '*';
					$sql = 'SELECT ' . $fields . ' FROM ' . Conf::$databases[$this->conf]['prefix'] . $this->relatives[$relation]['class'] . ' ';
					
					if (in_array($this->relatives[$relation]['relation'][0], array('parents', 'children')) && is_array($this->relatives[$relation]['relation'][1])) {
						$sql .= 'INNER JOIN ' . Conf::$databases[$this->conf]['prefix'] . $this->relatives[$relation]['relation'][1][0] . ' table2 ON table1.id = table2.' . $this->relatives[$relation]['relation'][1][1] . ' AND table2.' . $this->relatives[$relation]['relation'][1][2] . ' = ' . $res->id . ' ';
					}
					$sql .= 'WHERE ';
					if (isset($this->relatives[$relation]['conditions'])) {
						if (!is_array($this->relatives[$relation]['conditions'])) {
							$sql .= $this->relatives[$relation]['conditions'];
						} else {
							$cond = array();
							foreach ($this->relatives[$relation]['conditions'] as $k => $v) {
								if (is_null($v)) {
									$cond[] = "$k IS NULL";
								} else {
									if (!is_numeric($v)) {
										$v = $this->db->quote($v);
									}
									$cond[] = "$k=$v";
								}
							}
							$sql .= implode(' AND ', $cond);
						}
						$sql .= ' AND ';
					}
					
					if ($this->relatives[$relation]['relation'][0] == 'parent' || $this->relatives[$relation]['relation'][0] == 'parents') {
						if (!is_array($this->relatives[$relation]['relation'][1])) {
							if (!empty($res->{$this->relatives[$relation]['relation'][1]})) {
								$sql .= 'id = ' . $res->{$this->relatives[$relation]['relation'][1]};
							} else {
								$emptyField = true;
							}
						} else {
							if (!empty($res->id)) {
								$sql .= 'id IN(SELECT ' . $this->relatives[$relation]['relation'][1][2] . ' FROM ' . Conf::$databases[$this->conf]['prefix'] . $this->relatives[$relation]['relation'][1][0] . ' WHERE ' . $this->relatives[$relation]['relation'][1][1] . ' = ' . $res->id . ')';
							} else {
								$emptyField = true;
							}
						}
					} elseif ($this->relatives[$relation]['relation'][0] == 'child' || $this->relatives[$relation]['relation'][0] == 'children') {
						if (!is_array($this->relatives[$relation]['relation'][1])) {
							$sql .= $this->relatives[$relation]['relation'][1] . ' = ' . $res->id;
						} else {
							$sql .= 'id IN(SELECT ' . $this->relatives[$relation]['relation'][1][1] . ' FROM ' . Conf::$databases[$this->conf]['prefix'] . $this->relatives[$relation]['relation'][1][0] . ' WHERE ' . $this->relatives[$relation]['relation'][1][2] . ' = ' . $res->id . ')';
						}
					}
					$sql .= ' GROUP BY id';

					if (!isset($emptyField)) {
						$pre = $this->db->prepare($sql);
						$pre->execute();
						$relatives = $pre->fetchAll(PDO::FETCH_OBJ);
					} else {
						unset($emptyField);
						unset($relatives);
					}
					
					if (!empty($relatives)) {
						if (($this->relatives[$relation]['relation'][0] == 'child' || $this->relatives[$relation]['relation'][0] == 'parent') && !is_array($this->relatives[$relation]['relation'][1]) && count($relatives) == 1) {
							$res->{$relation} = $relatives[0];
						} else {
							$res->{$relation} = $relatives;
						}
						
						if(isset($this->relatives[$relation]['call']) && !empty($this->relatives[$relation]['call'])){
							if(!is_array($this->relatives[$relation]['relation'][1]))
								$this->call($this->relatives[$relation]['call'], $res->{$relation}, $this->relatives[$relation]['relation'][1]);
							else
								$this->call($this->relatives[$relation]['call'], $res->{$relation}, $this->relatives[$relation]['relation'][1][0]);
						}
					}
				}
			}
		}
	}

	/**
	 * Gets the class constants.
	 * @return array Class constants.
	 **/
	private function getConstants() {
		$reflectionClass = new ReflectionClass($this);
		return $reflectionClass->getConstants();
	}

	/**
	 * Adds a view to a specific element.
	 * @param string $table The name of the table linked to the element.
	 * @param int $id The element ID.
	 * @param array $user Information about the user who made the view.
	 * @return void This method returns nothing.
	 **/
	public function addView($table, $id, $user) {
		if (!$this->table_exists($table . "_views")) {
			return;
		}
	
		$dt = new DateTime("now");
		$dt->modify("-30 minutes");
		$date = $dt->format("Y-m-d H:i:s");
	
		$sql = 'SELECT * FROM ' . Conf::$databases[$this->conf]['prefix'] . $this->table . "_views ";
		$sql .= "WHERE ".$table."_id = :id AND date >= :date";
		$params = [':id' => $id, ':date' => $date];
	
		if (isset($user["member"]) && !empty($user["member"])) {
			$sql .= " AND member_id = :member_id";
			$params[':member_id'] = $user["member"]->id;
		}
	
		if (isset($user["guest"]) && !empty($user["guest"])) {
			$sql .= " AND guest_id = :guest_id";
			$params[':guest_id'] = $user["guest"]->id;
		}
	
		$pre = $this->db->prepare($sql);
		$pre->execute($params);
		$res = $pre->fetchAll(PDO::FETCH_OBJ);
	
		if (empty($res)) {
			$sql = 'INSERT INTO ' . Conf::$databases[$this->conf]['prefix'] . $this->table . "_views SET " . $table . "_id = :id, date = :view_date";
			$params = [':id' => $id, ':view_date' => date("Y-m-d H:i:s")];
	
			if (isset($user["member"]) && !empty($user["member"])) {
				$sql .= ", member_id = :member_id";
				$params[':member_id'] = $user["member"]->id;
			}
	
			if (isset($user["guest"]) && !empty($user["guest"])) {
				$sql .= ", guest_id = :guest_id";
				$params[':guest_id'] = $user["guest"]->id;
			}
	
			$pre = $this->db->prepare($sql);
			$pre->execute($params);
		}
	
		if ($this->column_exists("views_total", $table)) {
			$res = $this->findFirst([
				"fields" => ["id", "views_total"],
				"conditions" => ["id" => $id]
			]);
	
			if (!empty($res)) {
				$res->views_total++;
				$this->save($res);
			}
		}
	}

	/**
	 * Gets the plural table name using standard English inflection rules.
	 * @return string The plural table name.
	 **/
	private function getPluralTableName(): string {
		$lastChar = substr($this->table, -1);
		$lastTwoChars = substr($this->table, -2);
	
		if (in_array($lastChar, ['s', 'z']) && in_array(substr($lastTwoChars, 0, 1), ['a', 'e', 'i', 'o', 'u'])) {
			return $this->table . $lastChar . 'es';
		} elseif (in_array($lastChar, ['o', 's', 'x', 'z']) || in_array($lastTwoChars, ['sh', 'ch'])) {
			return $this->table . 'es';
		} elseif ($lastChar === 'y' && !in_array(substr($lastTwoChars, 0, 1), ['a', 'e', 'i', 'o', 'u'])) {
			return substr($this->table, 0, -1) . 'ies';
		} else {
			return $this->table . 's';
		}
	}

	/**
	 * Checks if a table exists in the database.
	 * @param string $table The name of the table to check.
	 * @return bool Returns true if the table exists, otherwise false.
	 **/
	private function table_exists(string $table): bool {
		$sql = "SELECT COUNT(*) AS count FROM information_schema.TABLES WHERE TABLE_SCHEMA = '".Conf::$databases[$this->conf]['database']."' AND TABLE_TYPE = 'BASE TABLE' AND TABLE_NAME = '".Conf::$databases[$this->conf]['prefix'].$table."'";
		$pre = $this->db->prepare($sql);
		$pre->execute();
		$res = current($pre->fetchAll(PDO::FETCH_OBJ));
		return ($res->count > 0);
	}

	/**
	 * Checks if a column exists in a specific table in the database.
	 * @param string $column The name of the column to check.
	 * @param string|null $table The name of the table. If not specified, uses the current table.
	 * @return bool Returns true if the column exists, otherwise false.
	 **/
	private function column_exists(string $column, ?string $table = null): bool {
		if (!$table) {
			$table = $this->table;
		}
		$sql = "SHOW COLUMNS FROM ".Conf::$databases[$this->conf]['prefix'].$table." LIKE '".$column."'";
		$pre = $this->db->prepare($sql);
		$pre->execute();
		$res = $pre->fetchAll(PDO::FETCH_OBJ);
		return (count($res) > 0);
	}

	/**
	 * Checks if a column in a specific table in the database is nullable.
	 * @param string $column The name of the column to check.
	 * @param string|null $table The name of the table. If not specified, uses the current table.
	 * @return bool Returns true if the column is nullable, otherwise false.
	 **/
	public function column_nullable(string $column, ?string $table = null): bool {
		if (!$table) {
			$table = $this->table;
		}
		$tableName = Conf::$databases[$this->conf]['prefix'].$table;
		
		$sql = "SELECT COL.IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS COL WHERE COL.TABLE_NAME = '$tableName' AND COL.COLUMN_NAME = '$column'";
		$pre = $this->db->prepare($sql);
		$pre->execute();
		
		$res = current($pre->fetchAll(PDO::FETCH_OBJ));
		return ($res->IS_NULLABLE == "YES");
	}

}
?>