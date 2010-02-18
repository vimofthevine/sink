<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * @brief   Database migration library
 * @author  Kyle Treubig
 */
class Migration_Core {
    /** Database tables */
    private $tables = array();

    /**
     * Return an instance of the Migration library
     * @return  Migration library object
     */
    public static function instance() {
        static $instance;
        if ( ! isset($instance)) {
            $instance = new Migration;
        }
        return $instance;
    }

    /**
     * Private constructor
     */
    public function __construct() {
        $schema = Kohana::find_file('queries/schemas', 'db_deltas', 'sql');
        $sql = file_get_contents($schema);
        try {
            DB::query(Database::INSERT, $sql)->execute();
        } catch (Database_Exception $e) {
            echo $e->getMessage();
        }
    }

    /**
     * Add table to list of tracked tables
     * @param table name of table
     * @return      array of tracked tables
     */
    public function table($table) {
        return $this->tables(array($table));
    }

    /**
     * Add tables to list of tracked tables
     * @param tables    array of tables
     * @return          array of tracked tables
     */
    public function tables($tables) {
        $this->tables = array_merge($this->tables, $tables);
        return $this->tables;
    }

    /**
     * Create and populate tables
     * @param populate  whether to perform insertion queries
     * @return          array of tables (key) and result (integer)
     * Return values are as follows:
     * - 0: failure to create table
     * - 1: successful table creation
     * - 2: table populated
     */
    public function initialize($populate = FALSE) {
        $return = array();

        foreach($this->tables as $table) {
            $schema = Kohana::find_file('queries/schemas', $table, 'sql');
            $init = Kohana::find_file('queries/init', $table, 'sql');
            $create = file_get_contents($schema);
            $insert = $init ? file_get_contents($init) : FALSE;

            try {
                DB::query(Database::INSERT, $create)->execute();
                $return[$table] = 1;
                if ($init AND $populate) {
                    DB::query(Database::INSERT, $insert)->execute();
                    $return[$table] = 2;
                }
            } catch (Database_Exception $e) {
                $return[$table] = 0;
                echo $e->getMessage();
            }
        }

        return $return;
    }

    /**
     * Apply database patches
     * @return  array of available and applied patches
     * Available patches array is of the form number=>patch description.
     * Applied patches array is of the form number=>array('descrip','result')
     * where result is a boolean indicating patch success.
     */
    public function patch() {
        $return = array(0=>array(), 1=>array());
        $deltas = Kohana::list_files('queries/deltas');
        $available = array();

        foreach($deltas as $delta) {
            $lines = file($delta);
            $tmp = explode("/", $delta);
            $file = substr(end($tmp), 0, -4);
            $delta = explode("-", $file, 3);
            $num = (int) $delta[0];
            $table = $delta[1];
            $descrip = substr($lines[0], 3);
            if (in_array($table, $this->tables)) {
                $available[$num] = $file;
                $return[0][$num] = "#$num [$table] - $descrip";
            }
        }

        $applied = DB::select()->from('db_deltas')->execute()->as_array('id', 'file');
        $applicable = array_diff($available, $applied);

        foreach($applicable as $id=>$delta) {
            $query = Kohana::find_file('queries/deltas', $delta, 'sql');
            $sql = file_get_contents($query);

            $lines = file($query);
            $tmp = explode("/", $delta);
            $file = end($tmp);
            $info = explode("-", $file, 3);
            $num = (int) $info[0];
            $table = $info[1];
            $descrip = trim(substr($lines[0], 3),"\n");
            $return[1][$num]['descrip'] = "#$num [$table] - $descrip.";

            try {
                DB::query(Database::UPDATE, $sql)->execute();
                DB::insert('db_deltas', array('id','file','date'))
                    ->values(array($id, $delta, time()))->execute();
                $return[1][$num]['result'] = TRUE;
            } catch (Database_Exception $e) {
                $return[1][$num]['result'] = FALSE;
                echo $e->getMessage();
            }
        }

        return $return;
    }
}

