<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Migration module library unit tests
 *
 * @todo    Fix assertions to work with mock database
 * @author  Kyle Treubig
 * @group   unused.migration
 * @group   unused.mgration.library
 */
class Migration_Library_UnitTest extends PHPUnit_Framework_TestCase {

    /**
     * Set up the test case
     * - drop the MySQL tables
     */
    protected function setUp() {
        Kohana::config('database')->default['type'] = 'mock';
        //DB::query(Database::DELETE, 'DROP TABLE IF EXISTS ut_init, ut_pop, db_deltas')
            //->execute();
    }

    /**
     * Test adding table
     */
    public function testAddTable() {
        $migration = new Migration;

        $result1 = $migration->table('some_table');
        $this->assertEquals(1, count($result1));
        $this->assertEquals('some_table', $result1[0]);

        $result2 = $migration->table('another_table');
        $this->assertEquals(2, count($result2));
        $this->assertEquals('some_table', $result2[0]);
        $this->assertEquals('another_table', $result2[1]);
    }

    /**
     * Test adding tables
     */
    public function testAddTables() {
        $migration = new Migration;

        $result1 = $migration->tables(array(
            'one_table',
            'two_table',
        ));
        $this->assertEquals(2, count($result1));
        $this->assertEquals('one_table', $result1[0]);
        $this->assertEquals('two_table', $result1[1]);

        $result2 = $migration->tables(array(
            'three_table',
            'four_table',
            'five_table',
        ));
        $this->assertEquals(5, count($result2));
        $this->assertEquals('three_table', $result2[2]);
        $this->assertEquals('four_table', $result2[3]);
        $this->assertEquals('five_table', $result2[4]);
    }

    /**
     * Test library constructor
     */
    public function testConstructor() {
        $migration = new Migration;

        $database = Database::instance();
        $this->assertRegExp('/db_deltas/', $database->queries[0]);
    }

    /**
     * Test library instantiation
     */
    public function testInstance() {
        $migration = Migration::instance();
        $migration->table('one_table');

        $library = Migration::instance();
        $tables = $library->table('second_table');

        $this->assertEquals(2, count($tables));
        $this->assertEquals('one_table', $tables[0]);
        $this->assertEquals('second_table', $tables[1]);
    }

    /**
     * Test initialization of no tables
     */
    public function testInitializeNoTables() {
        $migration = new Migration;
        $result = $migration->initialize();
        $this->assertEquals(0, count($result));
    }

    /**
     * Test initialization of tables
     */
    public function testInitializeTables() {
        $migration = new Migration;
        $migration->tables(array(
            'ut_init',
            'ut_pop',
        ));
        $result = $migration->initialize();

        $this->assertEquals(2, count($result));
        $this->assertEquals(1, $result['ut_init']);
        $this->assertEquals(1, $result['ut_pop']);

        $database = Database::instance();
        $this->assertRegExp('/ut_init/', $database->queries[0]);
        $this->assertRegExp('/ut_pop/', $database->queries[1]);
    }

    /**
     * Test initialization and population of tables
     */
    public function testInitializeAndPopulate() {
        $migration = new Migration;
        $migration->tables(array(
            'ut_init',
            'ut_pop',
        ));
        $result = $migration->initialize(TRUE);

        $this->assertEquals(2, count($result));
        $this->assertEquals(1, $result['ut_init']);
        $this->assertEquals(2, $result['ut_pop']);

        $database = Database::instance();
        $this->assertRegExp('/testName_1/', $database->queries[2]);
        $this->assertRegExp('/someName_2/', $database->queries[2]);
    }

    /**
     * Test patching of no tables
     */
    public function testPatchNoTables() {
        $migration = new Migration;
        $result = $migration->patch();
        $this->assertEquals(0, count($result[0]));
        $this->assertEquals(0, count($result[1]));
    }

    /**
     * Test patching with no patches available
     */
    public function testPatchNoPatches() {
        $migration = new Migration;
        $migration->table('db_deltas');
        $result = $migration->patch();
        $this->assertEquals(0, count($result[0]));
        $this->assertEquals(0, count($result[1]));
    }

    /**
     * Test patching of table
     */
    public function testPatchOnTable() {
        $migration = new Migration;
        $migration->table('ut_pop');
        $migration->initialize();
        $result = $migration->patch();

        $this->assertEquals(2, count($result[0]));
        $available = implode("\n", $result[0]);
        $this->assertRegExp('/ut_pop/', $available);
        $this->assertEquals(2, count($result[1]));
        $this->assertRegExp('/ut_pop/', $result[1][1]['descrip']);
        $this->assertTrue($result[1][1]['result']);
        $this->assertRegExp('/ut_pop/', $result[1][2]['descrip']);
        $this->assertTrue($result[1][2]['result']);

        $database = Database::instance();
        $this->assertRegExp('/editor/', $database->queries[2]);
        $this->assertRegExp('/modified/', $database->queries[3]);
    }

    /**
     * Test patching on already applied patches
     */
    public function testPatchAlreadyApplied() {
        $migration = new Migration;
        $migration->table('ut_pop');
        $migration->initialize();

        DB::insert('db_deltas', array('id','file'))
            ->values(
                array(1,'01-ut_pop-add_column'),
                array(2,'02-ut_pop-add_column')
            )->execute();
        $result = $migration->patch();

        $this->assertEquals(2, count($result[0]));
        $available = implode("\n", $result[0]);
        $this->assertRegExp('/ut_pop/', $available);

        $this->assertEquals(0, count($result[1]));
    }

    /**
     * Test patching on populated tables
     */
    public function testPatchOnPopulated() {
        $migration = new Migration;
        $migration->table('ut_pop');
        $migration->initialize(TRUE);
        $result = $migration->patch();

        $this->assertEquals(2, count($result[0]));
        $available = implode("\n", $result[0]);
        $this->assertRegExp('/ut_pop/', $available);
        $this->assertEquals(2, count($result[1]));
        $this->assertRegExp('/ut_pop/', $result[1][1]['descrip']);
        $this->assertTrue($result[1][1]['result']);
        $this->assertRegExp('/ut_pop/', $result[1][2]['descrip']);
        $this->assertTrue($result[1][2]['result']);

        $database = Database::instance();
        $columns = $database->list_columns('ut_pop');
        $this->assertArrayHasKey('editor', $columns);
        $this->assertArrayHasKey('modified', $columns);

        $items = DB::query(Database::SELECT, "select * from ut_pop")
            ->execute();
        $editors = $items->as_array('id', 'editor');
        $modified = $items->as_array('id', 'modified');
        $this->assertEquals('', $editors[1]);
        $this->assertEquals('Jan 2010', $modified[1]);
        $this->assertEquals('', $editors[3]);
        $this->assertEquals('Jan 2010', $modified[3]);
    }

    /**
     * Test patching order
     */
    public function testPatchOrder() {
        $migration = new Migration;
        $migration->table('ut_init');
        $migration->initialize();
        $result = $migration->patch();

        $this->assertEquals(2, count($result[1]));
        $this->assertRegExp('/ut_init/', $result[1][3]['descrip']);
        $this->assertTrue($result[1][3]['result']);
        $this->assertRegExp('/ut_init/', $result[1][4]['descrip']);
        $this->assertTrue($result[1][4]['result']);

        $database = Database::instance();
        $columns = $database->list_columns('ut_init');
        $this->assertArrayNotHasKey('modified', $columns);
    }

    /**
     * Test patching on a previous patch
     */
    public function testSecondPatch() {
        $migration = new Migration;
        $migration->table('ut_pop');
        $migration->initialize();

        DB::insert('db_deltas', array('id','file'))
            ->values(array(1,'01-ut_pop-add_column'))->execute();
        $result = $migration->patch();

        $this->assertEquals(1, count($result[1]));
        $this->assertRegExp('/ut_pop/', $result[1][2]['descrip']);
        $this->assertTrue($result[1][2]['result']);

        $database = Database::instance();
        $columns = $database->list_columns('ut_pop');
        $this->assertArrayNotHasKey('editor', $columns);
        $this->assertArrayHasKey('modified', $columns);
    }
}

