<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Sink module library unit tests
 *
 * @author  Kyle Treubig
 * @group   sink
 * @group   sink.library
 */
class Sink_Library_FuncationalTest extends PHPUnit_Framework_TestCase {

    /**
     * Set up the test case
     * - drop the MySQL tables
     */
    protected function setUp() {
        $db = Kohana::config('database.default.connection.database');
        Kohana::config('database')->default['connection']['database'] = 'test_'.$db;
        DB::query(Database::DELETE, 'DROP TABLE IF EXISTS ut_init, ut_pop, db_deltas')
            ->execute();
    }

    /**
     * Test adding table
     */
    public function testAddTable() {
        $sink = new Sink;

        $result1 = $sink->table('some_table');
        $this->assertEquals(1, count($result1));
        $this->assertEquals('some_table', $result1[0]);

        $result2 = $sink->table('another_table');
        $this->assertEquals(2, count($result2));
        $this->assertEquals('some_table', $result2[0]);
        $this->assertEquals('another_table', $result2[1]);
    }

    /**
     * Test adding tables
     */
    public function testAddTables() {
        $sink = new Sink;

        $result1 = $sink->tables(array(
            'one_table',
            'two_table',
        ));
        $this->assertEquals(2, count($result1));
        $this->assertEquals('one_table', $result1[0]);
        $this->assertEquals('two_table', $result1[1]);

        $result2 = $sink->tables(array(
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
        $sink = new Sink;

        $database = Database::instance();
        $this->assertContains('db_deltas', $database->list_tables());
    }

    /**
     * Test library instantiation
     */
    public function testInstance() {
        $sink = Sink::instance();
        $sink->table('one_table');

        $library = Sink::instance();
        $tables = $library->table('second_table');

        $this->assertEquals(2, count($tables));
        $this->assertEquals('one_table', $tables[0]);
        $this->assertEquals('second_table', $tables[1]);
    }

    /**
     * Test initialization of no tables
     */
    public function testInitializeNoTables() {
        $sink = new Sink;
        $result = $sink->initialize();
        $this->assertEquals(0, count($result));
    }

    /**
     * Test initialization of tables
     */
    public function testInitializeTables() {
        $sink = new Sink;
        $sink->tables(array(
            'ut_init',
            'ut_pop',
        ));
        $result = $sink->initialize();

        $this->assertEquals(2, count($result));
        $this->assertEquals(1, $result['ut_init']);
        $this->assertEquals(1, $result['ut_pop']);

        $database = Database::instance();
        $tables = $database->list_tables();
        $this->assertContains('ut_init', $tables);
        $this->assertContains('ut_pop', $tables);
    }

    /**
     * Test initialization and population of tables
     */
    public function testInitializeAndPopulate() {
        $sink = new Sink;
        $sink->tables(array(
            'ut_init',
            'ut_pop',
        ));
        $result = $sink->initialize(TRUE);

        $this->assertEquals(2, count($result));
        $this->assertEquals(1, $result['ut_init']);
        $this->assertEquals(2, $result['ut_pop']);

        $items = DB::query(Database::SELECT, "select * from ut_pop")
            ->execute()->as_array('id', 'name');

        $this->assertEquals('testName_1', $items[1]);
        $this->assertEquals('someName_2', $items[3]);
    }

    /**
     * Test patching of no tables
     */
    public function testPatchNoTables() {
        $sink = new Sink;
        $sink->patch($available, $applied);
        $this->assertEquals(0, count($available));
        $this->assertEquals(0, count($applied));
    }

    /**
     * Test patching with no patches available
     */
    public function testPatchNoPatches() {
        $sink = new Sink;
        $sink->table('db_deltas');
        $sink->patch($available, $applied);
        $this->assertEquals(0, count($available));
        $this->assertEquals(0, count($applied));
    }

    /**
     * Test patching of table
     */
    public function testPatchOnTable() {
        $sink = new Sink;
        $sink->table('ut_pop');
        $sink->initialize();
        $sink->patch($available, $applied);

        $this->assertArrayHasKey('ut_pop', $available);
        $this->assertEquals(2, count($available['ut_pop']));

        $this->assertArrayHasKey('ut_pop', $applied);
        $this->assertEquals(2, count($applied['ut_pop']));
        $this->assertTrue($applied['ut_pop'][1]['result']);
        $this->assertTrue($applied['ut_pop'][2]['result']);

        $database = Database::instance();
        $columns = $database->list_columns('ut_pop');
        $this->assertArrayHasKey('editor', $columns);
        $this->assertArrayHasKey('modified', $columns);
        $this->assertEquals('int', $columns['editor']['type']);
        $this->assertEquals('string', $columns['modified']['type']);
        $this->assertEquals(32, $columns['modified']['character_maximum_length']);
    }

    /**
     * Test patching on already applied patches
     */
    public function testPatchAlreadyApplied() {
        $sink = new Sink;
        $sink->table('ut_pop');
        $sink->initialize();

        DB::insert('db_deltas', array('file'))
            ->values(
                array('01-ut_pop-add_column'),
                array('02-ut_pop-add_column')
            )->execute();
        $sink->patch($available, $applied);

        $this->assertArrayHasKey('ut_pop', $available);
        $this->assertEquals(2, count($available['ut_pop']));

        $this->assertEquals(0, count($applied));
    }

    /**
     * Test patching on populated tables
     */
    public function testPatchOnPopulated() {
        $sink = new Sink;
        $sink->table('ut_pop');
        $sink->initialize(TRUE);
        $sink->patch($available, $applied);

        $this->assertArrayHasKey('ut_pop', $available);
        $this->assertEquals(2, count($available['ut_pop']));

        $this->assertArrayHasKey('ut_pop', $applied);
        $this->assertEquals(2, count($applied['ut_pop']));
        $this->assertTrue($applied['ut_pop'][1]['result']);
        $this->assertTrue($applied['ut_pop'][2]['result']);

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
        $sink = new Sink;
        $sink->table('ut_init');
        $sink->initialize();
        $sink->patch($available, $applied);

        $this->assertArrayHasKey('ut_init', $applied);
        $this->assertEquals(2, count($applied['ut_init']));
        $this->assertTrue($applied['ut_init'][3]['result']);
        $this->assertTrue($applied['ut_init'][4]['result']);

        $database = Database::instance();
        $columns = $database->list_columns('ut_init');
        $this->assertArrayNotHasKey('modified', $columns);
    }

    /**
     * Test patching on a previous patch
     */
    public function testSecondPatch() {
        $sink = new Sink;
        $sink->table('ut_pop');
        $sink->initialize();

        DB::insert('db_deltas', array('id','file'))
            ->values(array(1,'01-ut_pop-add_column'))->execute();
        $sink->patch($available, $applied);

        $this->assertArrayHasKey('ut_pop', $applied);
        $this->assertEquals(1, count($applied['ut_pop']));
        $this->assertTrue($applied['ut_pop'][2]['result']);

        $database = Database::instance();
        $columns = $database->list_columns('ut_pop');
        $this->assertArrayNotHasKey('editor', $columns);
        $this->assertArrayHasKey('modified', $columns);
    }
}

