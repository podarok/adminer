<?php
namespace Adminer;

// PDO can be used in several database drivers
if (extension_loaded('pdo')) {
	abstract class PdoDb extends SqlDb {
		/** @var \PDO */ protected $pdo;

		/** Connect to server using DSN
		* @param string $dsn
		* @param string $username
		* @param string $password
		* @param mixed[] $options
		* @return void
		*/
		function dsn($dsn, $username, $password, $options = array()) {
			$options[\PDO::ATTR_ERRMODE] = \PDO::ERRMODE_SILENT;
			$options[\PDO::ATTR_STATEMENT_CLASS] = array('Adminer\PdoResult');
			try {
				$this->pdo = new \PDO($dsn, $username, $password, $options);
			} catch (\Exception $ex) {
				auth_error(h($ex->getMessage()));
			}
			$this->server_info = @$this->pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
		}

		function quote($string) {
			return $this->pdo->quote($string);
		}

		function query($query, $unbuffered = false) {
			/** @var Result|bool */
			$result = $this->pdo->query($query);
			$this->error = "";
			if (!$result) {
				list(, $this->errno, $this->error) = $this->pdo->errorInfo();
				if (!$this->error) {
					$this->error = lang('Unknown error.');
				}
				return false;
			}
			$this->store_result($result);
			return $result;
		}

		function store_result($result = null) {
			if (!$result) {
				$result = $this->multi;
				if (!$result) {
					return false;
				}
			}
			if ($result->columnCount()) {
				$result->num_rows = $result->rowCount(); // is not guaranteed to work with all drivers
				return $result;
			}
			$this->affected_rows = $result->rowCount();
			return true;
		}

		function next_result() {
			/** @var PdoResult|bool */
			$result = $this->multi;
			if (!is_object($result)) {
				return false;
			}
			$result->_offset = 0;
			return @$result->nextRowset(); // @ - PDO_PgSQL doesn't support it
		}
	}

	class PdoResult extends \PDOStatement {
		public $_offset = 0, $num_rows;

		function fetch_assoc() {
			return $this->fetch(\PDO::FETCH_ASSOC);
		}

		function fetch_row() {
			return $this->fetch(\PDO::FETCH_NUM);
		}

		function fetch_field() {
			$row = (object) $this->getColumnMeta($this->_offset++);
			$type = $row->pdo_type;
			$row->type = ($type == \PDO::PARAM_INT ? 0 : 15);
			$row->charsetnr = ($type == \PDO::PARAM_LOB || (isset($row->flags) && in_array("blob", (array) $row->flags)) ? 63 : 0);
			return $row;
		}

		function seek($offset) {
			for ($i=0; $i < $offset; $i++) {
				$this->fetch();
			}
		}
	}
}
