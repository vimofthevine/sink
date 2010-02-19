# Sink

Database schema synchronization for group collaboration.  Allows project contributors to push database schema changes out to other collaborators.  It is not intended for production environments.

## Configuring Sink

The only configuration required for Sink to operate is to specify the tables Sink should track.  This is accomplished with the `table()` and `tables()` methods.

    $sink = new Sink;
    $sink->table('users');
    $sink->tables(array(
        'articles',
        'comments',
    ));

## Database Initialization

The sink module can be used as a database table installer.  The `initialize()` method, for each tracked table, searches the cascading filesystem for a _tablename_.sql file in the `queries/schemas` folder.  It will return an associative array containing the creation status of each table (table name is the key, status is the value).  If it finds a _tablename_.sql file in the `queries/init` folder, it will also apply the sql query contained in that file.  This could be useful for inserting default records into a table.

If the table could not be created, for a schema file could not be found, the return status is 0.  If the table was created, the return status is 1.  If the table was populated (an init file was found and successfully applied), the return status is 2.

    $sink = new Sink;
    $sink->tables(array(
        'doesnt_exist',
        'exists',
        'init',
    ));
    $result = $sink->initialize();

    // $result will contain
    $result = array(
        'doesnt_exist' => 0,
        'exists' => 1,
        'init' => 2,
    );

## Publishing Schema Changes

Changes to a database table schema can be captured by Sink through delta files.  These delta files are stored in `queries/deltas` and take the form _deltanumber_-_tablename_-_deltadescription_.sql, where the delta number is applicable per table (ie, 02-users-add_email_column.sql and 02-comments-remove_subject_column.sql will not conflict).  Each delta file can only contain one query, and the first line must contain an SQL comment (ie, `-- Some explanation of the patch`).

## Applying Schema Changes

The `patch()` method will browse through the cascading filesystem for all delta files belonging to the list of tracked tables.  The Sink module tracks which deltas have already been applied, so each time `patch()` is called, it will only execute the change queries that have not yet been run.  The list of available and applied patches is provided through two arrays passed as arguments to the function.

    $sink = new Sink;
    $sink->table('table_with_deltas');
    $sink->patch($available, $applied);

    // assuming queries/init/01-table_with_deltas-add_column.sql exists,
    // will result in
    $available = array(
        'table_with_deltas' => array(
            1 => 'add_column',
        ),
    );

    $applied = array(
        'table_with_deltas' => array(
            1 => array(
                'descrip' => 'add_column',
                'result' => TRUE,    // or FALSE, if unsuccessful
            ),
        ),
    );


