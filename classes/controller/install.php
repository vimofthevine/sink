<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * @brief   Installation demo controller (Sink module)
 * @author  Kyle Treubig
 */
class Controller_Install extends Controller_Template_Sink {

    public function before() {
        Sink::instance()->tables(array(
            'ut_init',
            'ut_pop',
            // 'table_name',
            // 'table_name',
        ));
    }

}

