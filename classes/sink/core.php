<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Database synchronization library
 *
 * @package     Sink
 * @author      Kyle Treubig
 * @copyright   (c) 2010 Kyle Treubig
 * @license     MIT
 */
abstract class Sink_Core {
	/** Database tables */
	private $tables = array();

	/**
	 * Return an instance of the Sink library
	 *
	 * @return  Sink
	 */
	public static function instance()
	{
		static $instance;
		if ( ! isset($instance))
		{
			$instance = new Sink;
		}
		return $instance;
	}

	/**
	 * Create deltas table if it doesn't exist
	 *
	 * @return  void
	 */
	public function __construct()
	{
		$schema = Kohana::find_file('queries/schemas', 'db_deltas', 'sql');
		$sql = file_get_contents($schema);
		try
		{
			DB::query(Database::INSERT, $sql)->execute();
		}
		catch (Database_Exception $e)
		{
			echo $e->getMessage();
		}
	}

	/**
	 * Add table to list of tracked tables
	 *
	 * @param   string  table name of table
	 * @return  array   tracked tables
	 */
	public function table($table) {
		return $this->tables(array($table));
	}

	/**
	 * Add tables to list of tracked tables
	 *
	 *      $this->tables(array('table1', 'table2'));
	 *
	 * @param   array   array of tables
	 * @return  array   tracked tables
	 */
	public function tables($tables) {
		$this->tables = array_merge($this->tables, $tables);
		return $this->tables;
	}

	/**
	 * Create and populate tables
	 *
	 * @param   bool    whether to perform insertion (populate) queries
	 * @return  array   associative array as table => result
	 * Return values are as follows:
	 * <ul>
	 *  <li>0: failure to create table</li>
	 *  <li>1: successful table creation</li>
	 *  <li>2: table populated</li>
	 * </ul>
	 */
	public function initialize($populate = FALSE) {
		$return = array();

		foreach ($this->tables as $table)
		{
			$schema = Kohana::find_file('queries/schemas', $table, 'sql');
			$init = Kohana::find_file('queries/init', $table, 'sql');
			$create = file_get_contents($schema);
			$insert = $init ? file_get_contents($init) : FALSE;

			try
			{
				DB::query(Database::INSERT, $create)->execute();
				$return[$table] = 1;
				if ($init AND $populate)
				{
					DB::query(Database::INSERT, $insert)->execute();
					$return[$table] = 2;
				}
			}
			catch (Database_Exception $e)
			{
				$return[$table] = 0;
				echo $e->getMessage();
			}
		}

		return $return;
	}

	/**
	 * Apply database patches
	 *
	 * $available and $applied arrays are overwritten as:
	 *
	 *      $available = array(
	 *          'table_name' => array(
	 *              1 => 'description for patch #1',
	 *              2 => 'description for patch #2',
	 *          ),
	 *      );
	 *      $applied = array(
	 *          'table_name' => array(
	 *              2 => array(
	 *                  'descrip' => 'description for patch #2',
	 *                  'result' => TRUE,
	 *              ),
	 *          ),
	 *      );
	 *
	 * @param   array   available patches
	 * @param   array   applied patches, with results
	 * @return  void
	 */
	public function patch(&$available, &$applied) {
		$available = array();
		$applied = array();

		$delta_files = Kohana::list_files('queries/deltas');
		$available_files = array();

		foreach($delta_files as $delta_file)
		{
			$tmp = explode("/", $delta_file);
			$file = substr(end($tmp), 0, -4);

			$info = explode("-", $file, 3);
			$num = (int) $info[0];
			$table = $info[1];

			$lines = file($delta_file);
			$descrip = substr($lines[0], 3);

			if (in_array($table, $this->tables))
			{
				$available_files[$file] = $file;
				$available[$table][$num] = $descrip;
			}
		}

		$applied_files = DB::select()->from('db_deltas')->execute()->as_array('file', 'file');
		$applicable_files = array_diff($available_files, $applied_files);

		foreach($applicable_files as $delta_file)
		{
			$query = Kohana::find_file('queries/deltas', $delta_file, 'sql');
			$sql = file_get_contents($query);

			$tmp = explode("/", $delta_file);
			$file = end($tmp);

			$info = explode("-", $file, 3);
			$num = (int) $info[0];
			$table = $info[1];

			$lines = file($query);
			$descrip = trim(substr($lines[0], 3),"\n");

			$applied[$table][$num]['descrip'] = $descrip;

			try
			{
				DB::query(Database::UPDATE, $sql)->execute();
				DB::insert('db_deltas', array('file','date'))
					->values(array($delta_file, time()))->execute();
				$applied[$table][$num]['result'] = TRUE;
			}
			catch (Database_Exception $e)
			{
				$applied[$table][$num]['result'] = FALSE;
				echo $e->getMessage();
			}
		}
	}
}	// End of Sink_Core

