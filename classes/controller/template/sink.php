<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * @brief   Sink installation template controller
 * @author  Kyle Treubig
 */
abstract class Controller_Template_Sink extends Controller {

    /**
     * Default action
     */
    public function action_index() {
        $this->action_tables();
        $this->action_patches();
    }

    /**
     * Create tables
     */
    public function action_tables() {
        $tables = Sink::instance()->initialize(TRUE);
        echo '<h1>Table Schemas:</h1>';

        foreach($tables as $table=>$result) {
            if ($result > 0) {
                echo $table.' table created successfully.';
            } else {
                echo 'Error creating table '.$table.'.';
            }

            if ($result == 2) {
                echo ' [Populated]';
            }

            echo '<br />';
        }
    }

    /**
     * Apply database patches
     */
    public function action_patches() {
        $patches = Sink::instance()->patch();
        echo '<h1>Patches:</h1>';

        echo '<h2>Available patches:</h2>';
        echo '<pre>';
        foreach($patches[0] as $patch) {
            echo $patch.'<br />';
        }
        echo '</pre>';

        echo '<h2>Applied patches:</h2>';
        echo '<pre>';
        foreach($patches[1] as $patch) {
            echo $patch['descrip'],'<br />';
            echo '  '.($patch['result'] ? 'Patch applied successfully.' : 'Error applying patch.').'<br />';
        }
        echo '</pre>';
    }
}

