<?php
/**
* @author Marcin Laber, marcin@laber.pl
*
* Sybase driver, for use via freetds + pdo_dblib
*/

$drivers['sybase'] = 'Sybase (alpha)';

if (isset($_GET['sybase']))
{
	define('DRIVER', 'sybase');
	if (extension_loaded('pdo_dblib'))
	{
		class Min_DB extends Min_PDO
		{
			var $extension = 'PDO_DBLIB';
			
			function connect ($server, $username, $password)
			{
				$this->dsn('dblib:charset=utf8;host=' . preg_replace('/\[(\d+?)\]/', ':$1', str_replace(':', ';unix_socket=', preg_replace('/:(\d+?)/', ';port=$1', $server))), $username, $password);
				return true;
			}
			
			function select_db ($database)
			{
				return $this->query('USE ' . idf_escape($database));
			}
		}
	}
	
	class Min_Driver extends Min_SQL
	{
		function insertUpdate ($table, $rows, $primary)
		{
			foreach ($rows as $set)
			{
				$update = array();
				$where = array();
				foreach ($set as $key => $val)
				{
					$update[] = $key . ' = ' . $val;
					if (isset($primary[idf_unescape($key)]))
						$where[] = $key . ' = ' . $val;
				}
				
				//! can use only one query for all rows
				if (!queries('MERGE ' . table($table) . ' USING (VALUES(' . implode(', ', $set) . ')) AS source (c' . implode(', c', range(1, count($set))) . ') ON ' . implode(' AND ', $where) //! source, c1 - possible conflict
					. ' WHEN MATCHED THEN UPDATE SET ' . implode(', ', $update)
					. ' WHEN NOT MATCHED THEN INSERT (' . implode(', ', array_keys($set)) . ') VALUES (' . implode(', ', $set) . ');' // ; is mandatory
				))
					return false;
			}
			return true;
		}
		
		function begin ()
		{
			return queries('BEGIN TRANSACTION');
		}
	}
	
	function idf_escape ($idf)
	{
		return '`' . str_replace('`', '``', $idf) . '`';
	}
	
	function table ($idf)
	{
		return ($_GET["db"] != "" ? idf_escape($_GET["db"]) . "." : "") . idf_escape($idf);
	}
	
	function connect ()
	{
		global $adminer;
		$connection = new Min_DB;
		$credentials = $adminer->credentials();
		if ($connection->connect($credentials[0], $credentials[1], $credentials[2]))
			return $connection;
		return $connection->error;
	}
	
	function get_databases ()
	{
		return get_vals("SELECT DISTINCT table_owner FROM sp_tables() ORDER BY table_owner");
	}
	
	function limit ($query, $where, $limit, $offset = 0, $separator = " ")
	{
		return ($limit !== null ? sprintf(" TOP %d ", $limit) : '') . ($offset > 0 ? sprintf(" START AT %d ", $offset + 1) : '') . $query . $where;
	}
	
	function limit1 ($table, $query, $where, $separator = "\n")
	{
		return limit($query, $where, 1, 0, $separator);
	}
	
	function db_collation ($db, $collations)
	{
	}
	
	function engines ()
	{
		return array();
	}
	
	function logged_user ()
	{
		global $connection;
		return $connection->result("SELECT SUSER_NAME()");
	}
	
	function tables_list ()
	{
		return get_key_vals("SELECT table_name, table_type FROM sp_tables() WHERE table_owner = " . q(get_current_db()) . " ORDER BY table_name");
	}
	
	function count_tables ($databases)
	{
		global $connection;
		$return = array();
		foreach ($databases as $db)
			$return[$db] = $connection->result("SELECT COUNT(1) FROM sp_tables() WHERE table_owner = " . q($db));
		return $return;
	}
	
	function table_status ($name = "")
	{
		$return = array();
		foreach (get_rows("SELECT table_name AS `Name`, table_type AS `Engine`, remarks AS `Comment` FROM sp_tables() WHERE table_owner = " . q(get_current_db()) . ($name != "" ? " AND table_name = " . q($name) : "") . " ORDER BY table_name") as $row)
		{
			if ($name != "")
				return $row;
			
			$return[$row["Name"]] = $row;
		}
		return $return;
	}
	
	function get_current_db ()
	{
		global $connection;
		return $connection->_current_db ?? DB;
	}
	
	function is_view ($table_status) {
		return $table_status["Engine"] == "VIEW";
	}
	
	function fk_support ($table_status)
	{
		return false;
	}
	
	function fields ($table)
	{
		$return = array();
		foreach (get_rows("SELECT * FROM sp_columns() WHERE table_owner = " . q(get_current_db()) . " AND table_name = " . q($table) . " ORDER BY colid") as $row)
		{
			$return[$row['column_name']] = array(
				'field' => $row['column_name'],
				'full_type' => sprintf('%s (%d)', $row['type_name'], $row['length']),
				'type' => $row['type_name'],
				'length' => $row['length'],
				'default' => null,
				'null' => $row['nullable'],
				'auto_increment' => '',
				'collation' => '',
				'privileges' => array(
					'insert' => 0,
					'select' => 1,
					'update' => 0,
				),
				'primary' => '',
				'comment' => $row['remarks'],
			);
		}
		return $return;
	}
	
	function indexes ($table, $connection2 = null)
	{
		$return = array();
		return $return;
	}
	
	function view ($name)
	{
		global $connection;
		return array("select" => preg_replace('~^(?:[^[]|\[[^]]*])*\s+AS\s+~isU', '', $connection->result("SELECT VIEW_DEFINITION FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA = SCHEMA_NAME() AND TABLE_NAME = " . q($name))));
	}
	
	function collations ()
	{
		$return = array();
		return $return;
	}
	
	function information_schema ($db)
	{
		return false;
	}
	
	function error ()
	{
		global $connection;
		return nl_br(h(preg_replace('~^(\[[^]]*])+~m', '', $connection->error)));
	}
	
	function create_database ($db, $collation)
	{
		return "";//queries("CREATE DATABASE " . idf_escape($db) . (preg_match('~^[a-z0-9_]+$~i', $collation) ? " COLLATE $collation" : ""));
	}
	
	function drop_databases ($databases)
	{
		return "";//queries("DROP DATABASE " . implode(", ", array_map('idf_escape', $databases)));
	}
	
	function rename_database ($name, $collation)
	{
		/*
		if (preg_match('~^[a-z0-9_]+$~i', $collation))
			queries("ALTER DATABASE " . idf_escape(DB) . " COLLATE $collation");
		
		queries("ALTER DATABASE " . idf_escape(DB) . " MODIFY NAME = " . idf_escape($name));
		*/
		return true; //! false negative "The database name 'test2' has been set."
	}
	
	function auto_increment ()
	{
		return "";//" IDENTITY" . ($_POST["Auto_increment"] != "" ? "(" . number($_POST["Auto_increment"]) . ",1)" : "") . " PRIMARY KEY";
	}
	
	function alter_table ($table, $name, $fields, $foreign, $comment, $engine, $collation, $auto_increment, $partitioning)
	{
		/*
		$alter = array();
		$comments = array();
		foreach ($fields as $field) {
			$column = idf_escape($field[0]);
			$val = $field[1];
			if (!$val) {
				$alter["DROP"][] = " COLUMN $column";
			} else {
				$val[1] = preg_replace("~( COLLATE )'(\\w+)'~", '\1\2', $val[1]);
				$comments[$field[0]] = $val[5];
				unset($val[5]);
				if ($field[0] == "") {
					$alter["ADD"][] = "\n  " . implode("", $val) . ($table == "" ? substr($foreign[$val[0]], 16 + strlen($val[0])) : ""); // 16 - strlen("  FOREIGN KEY ()")
				} else {
					unset($val[6]); //! identity can't be removed
					if ($column != $val[0]) {
						queries("EXEC sp_rename " . q(table($table) . ".$column") . ", " . q(idf_unescape($val[0])) . ", 'COLUMN'");
					}
					$alter["ALTER COLUMN " . implode("", $val)][] = "";
				}
			}
		}
		if ($table == "") {
			return queries("CREATE TABLE " . table($name) . " (" . implode(",", (array) $alter["ADD"]) . "\n)");
		}
		if ($table != $name) {
			queries("EXEC sp_rename " . q(table($table)) . ", " . q($name));
		}
		if ($foreign) {
			$alter[""] = $foreign;
		}
		foreach ($alter as $key => $val) {
			if (!queries("ALTER TABLE " . idf_escape($name) . " $key" . implode(",", $val))) {
				return false;
			}
		}
		foreach ($comments as $key => $val) {
			$comment = substr($val, 9); // 9 - strlen(" COMMENT ")
			queries("EXEC sp_dropextendedproperty @name = N'MS_Description', @level0type = N'Schema', @level0name = " . q(get_schema()) . ", @level1type = N'Table', @level1name = " . q($name) . ", @level2type = N'Column', @level2name = " . q($key));
			queries("EXEC sp_addextendedproperty @name = N'MS_Description', @value = " . $comment . ", @level0type = N'Schema', @level0name = " . q(get_schema()) . ", @level1type = N'Table', @level1name = " . q($name) . ", @level2type = N'Column', @level2name = " . q($key));
		}
		*/
		return true;
	}
	
	function alter_indexes ($table, $alter)
	{
		/*
		$index = array();
		$drop = array();
		foreach ($alter as $val) {
			if ($val[2] == "DROP") {
				if ($val[0] == "PRIMARY") { //! sometimes used also for UNIQUE
					$drop[] = idf_escape($val[1]);
				} else {
					$index[] = idf_escape($val[1]) . " ON " . table($table);
				}
			} elseif (!queries(($val[0] != "PRIMARY"
				? "CREATE $val[0] " . ($val[0] != "INDEX" ? "INDEX " : "") . idf_escape($val[1] != "" ? $val[1] : uniqid($table . "_")) . " ON " . table($table)
				: "ALTER TABLE " . table($table) . " ADD PRIMARY KEY"
			) . " (" . implode(", ", $val[2]) . ")")) {
				return false;
			}
		}
		return (!$index || queries("DROP INDEX " . implode(", ", $index)))
			&& (!$drop || queries("ALTER TABLE " . table($table) . " DROP " . implode(", ", $drop)))
		;
		*/
	}
	
	function last_id ()
	{
		/*
		global $connection;
		return $connection->result("SELECT SCOPE_IDENTITY()"); // @@IDENTITY can return trigger INSERT
		*/
	}
	
	function explain ($connection, $query)
	{
		$connection->query("SET SHOWPLAN_ALL ON");
		$return = $connection->query($query);
		$connection->query("SET SHOWPLAN_ALL OFF"); // connection is used also for indexes
		return $return;
	}
	
	function found_rows ($table_status, $where)
	{
	}
	
	function foreign_keys ($table)
	{
		$return = array();
		return $return;
	}
	
	function truncate_tables ($tables)
	{
		// return apply_queries("TRUNCATE TABLE", $tables);
	}
	
	function drop_views ($views)
	{
		// return queries("DROP VIEW " . implode(", ", array_map('table', $views)));
	}
	
	function drop_tables ($tables)
	{
		// return queries("DROP TABLE " . implode(", ", array_map('table', $tables)));
	}
	
	function move_tables ($tables, $views, $target)
	{
		// return apply_queries("ALTER SCHEMA " . idf_escape($target) . " TRANSFER", array_merge($tables, $views));
	}
	
	function trigger ($name)
	{
		return array();
	}
	
	function triggers ($table)
	{
		$return = array();
		return $return;
	}
	
	function trigger_options ()
	{
		return array(
			"Timing" => array("AFTER", "INSTEAD OF"),
			"Event" => array("INSERT", "UPDATE", "DELETE"),
			"Type" => array("AS"),
		);
	}
	
	function schemas ()
	{
		return array();
	}
	
	function get_schema ()
	{
		return '';
	}
	
	function set_schema ($schema)
	{
		return true;
	}
	
	function use_sql ($database)
	{
		return "USE " . idf_escape($database);
	}
	
	function show_variables ()
	{
		return array();
	}
	
	function show_status ()
	{
		return array();
	}
	
	function convert_field ($field)
	{
	}
	
	function unconvert_field ($field, $return)
	{
		return $return;
	}
	
	function support ($feature)
	{
		return preg_match('~^(comment|columns|database|drop_col|indexes|descidx|sql|table|view)$~', $feature); //! routine|
	}
	
	function driver_config ()
	{
		$types = array();
		$structured_types = array();
		foreach (array( //! use sys.types
			lang('Numbers') => array("tinyint" => 3, "smallint" => 5, "int" => 10, "bigint" => 20, "bit" => 1, "decimal" => 0, "real" => 12, "float" => 53, "smallmoney" => 10, "money" => 20),
			lang('Date and time') => array("date" => 10, "smalldatetime" => 19, "datetime" => 19, "datetime2" => 19, "time" => 8, "datetimeoffset" => 10),
			lang('Strings') => array("char" => 8000, "varchar" => 8000, "text" => 2147483647, "nchar" => 4000, "nvarchar" => 4000, "ntext" => 1073741823),
			lang('Binary') => array("binary" => 8000, "varbinary" => 8000, "image" => 2147483647),
		) as $key => $val) {
			$types += $val;
			$structured_types[$key] = array_keys($val);
		}
		return array(
			'possible_drivers' => array("PDO_DBLIB"),
			'jush' => "sql",
			'types' => $types,
			'structured_types' => $structured_types,
			'unsigned' => array(),
			'operators' => array("=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%", "IN", "IS NULL", "NOT LIKE", "NOT IN", "IS NOT NULL"),
			'functions' => array("len", "lower", "round", "upper"),
			'grouping' => array("avg", "count", "count distinct", "max", "min", "sum"),
			'edit_functions' => array(
				array(
					"date|time" => "getdate",
				), array(
					"int|decimal|real|float|money|datetime" => "+/-",
					"char|text" => "+",
				)
			),
		);
	}
}
