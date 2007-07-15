<?php
/**
 * An implementation of the ActiveRecord pattern at its most basic. 
 *
 * @package    ActivePHP 
 * @author     OwlManAtt <owlmanatt@gmail.com> 
 * @copyright  2007, Yasashii Syndicate 
 * @version    1.9.0
 **/

/**
 * SQL generator libraries.
 **/
require_once('SqlGenerators/interface.inc.php');
require_once('SqlGenerators/mysql.class.php');
require_once('SqlGenerators/oci.class.php');

/**
 * The base class that implements all of the magic for your child classes.
 * This class itself should never be instantiated; extend this object and
 * set the table_name / primary_key attributes to get it working.
 *
 * @example ActiveTable.pkg Demonstration on how to use ActiveTable in a few different ways.
 *
 * @package    ActivePHP 
 * @author     OwlManAtt <owlmanatt@gmail.com> 
 * @copyright  2007, Yasashii Syndicate
 * @version    Release: @package_version@
 */
class ActiveTable
{
    /**
     * A PEAR::DB database connector.
     *
     * @var object
     */
    protected $db;

    /**
     * The arguments passed to the constructor, minus the required DB.
     * 
     * This is used for findBy - in the event that a user defines a
     * custom constructor with more than just the required DB connector,
     * the same things that were passed to the creator will be given to the
     * instances being instantiated. 
     *
     * @internal
     * @var array
     **/
    protected $CONSTRUCTOR_ARGS = array();

    /**
     * An ActiveTable_SQL_* instance to be used for writing SQL statements.
     *
     * The appropriate driver for your database will be determined in the constructor.
     * The DSN for the PEAR::DB connector is examined and the driver is spawned.
     *
     * @var object
     */
    protected $sql_generator;

    /**
     * The name of the database for your table. Leave this blank to default it to
     * whatever the DB connector is set to. (Mainly used for Oracle.)
     *
     * @var string
     */
    protected $database = '';

    /**
     * The name of the table being wrapped. This *must* be defined in
     * the child objects.
     *
     * @var string
     */
    protected $table_name = null;

    /**
     * The name of the primary (auto-incrementing) key in the table being
     * wrapped. 
     *
     * Note that (at this time) ActivePHP does -not- support compound primary
     * keys or primary keys that are not auto-incrementing.
     *
     * @var string|array
     */
    protected $primary_key = null;

    /**
     * This determines whether or not you can modify the PK.
     *
     * By default, it is false, AND IT SHOULD REMAIN
     * AS SUCH UNLESS YOU KNOW *VERY* WELL WHAT YOU ARE DOING.
     * This should be used when doing ghettoinheritence.
     *
     * @var bool
     */
     protected $allow_pk_write = false;
    
    /**
     * One-to-one support. This will join to another table on a FK to get some
     * lookup data. <strong>Note that any data retrieved as a lookup is read-only</strong>. 
     * 
     * For each lookup table, add an array with the following to the LOOKUPS array:
     *
     * Array (
     *    local_key => <em>the key in $this table</em>
     *    foreign_key => <em>the key in your lookup table</em>
     *    foreign_table => <em>the lookup table's name</em>
     *    foreign_table_alias => <em>the lookup table's alias (needed to join to the same table >1 times)</em>
     *    join_type => <em>inner</em>|<em>left</em>
     *    write => <em>false</em>|<em>true</em>
     *    filter => @See the findBy documentation. Pass one of those arrays.@ 
     * )
     *
     * @var array
     */
    protected $LOOKUPS = array();

    /**
     * Additional clauses to add to a <em>SELECT col1, col2...</em>. This will
     * allow you to get 'virtual' columns, such as the result of MySQL's IF(foo=1,'YES','NO').
     *
     * Specify them in this format:
     *
     * <code>
     * protected $VIRTUAL = array(
     *    'attribute_name' => "IF(foo=1,'YES','NO')",
     *    'math_result' => "1+2",
     * );
     * </code>
     *
     * The array key you assign will be the name of the attribute you use when getting the data, ie:
     *  
     * <code>echo $foo->getAttributeName().' '.$foo->getMathResult()."\n";</code>
     *
     * If there are name collisions, you can resolve them by using the #get() method directly. Specify
     * '__virtual' as the tablename.
     *
     * @var array
     **/
    protected $VIRTUAL = array();

    /** 
     * An array of 'related' classes/tables to prepare to load.
     *
     * This will pre-load the IDs for related records when an instance
     * of this class is loaded. Further methods will be available for
     * causing the IDs to be loaded into objects. See the README and __call
     * documentation for details on those methods.
     *
     * Define the related classes as such:
     * 
     * <code>
     * protected $RELATED = array(
     *   'record_set' => array( // Verbose example.
     *       'class' => 'Tags',
     *       'local_table' => 'table_from_this_class', // Defaults to the 'primary' table.
     *       'local_key' => 'id_a',
     *       'foreign_table' => 'table_from_other_class', // Defaults to the 'primary' table.
     *       'foreign_key' => 'id_z',
     *       'foreign_primary_key' => 'table_from_other_class_id', // Defaults to the PK.
     *       'foreign_database' => 'db', // optional
     *   ),
     *   'users' => array( // Minimalistic example.
     *       'class' => 'User',
     *       'local_key' => 'group_id',
     *       'foreign_key' => 'group_id',
     *   ),
     * );
     * </code>
     * 
     * Note that each definition has a key; 'record_set' and 'users' will be the names
     * that the sets can be retrieved by.
     *
     * @var array
     **/
    protected $RELATED = array();

    /**
     * The internal list of related object PKs.
     *
     * @internal
     * @var array
     **/
    protected $RELATED_IDS = array();

    /**
     * The internal list of related (loaded) objects.
     *
     * @internal
     * @var array
     **/
    protected $RELATED_OBJECTS = array();

    /**
     * The state that this row is in. It is either 'new' or 'loaded'. Depending
     * on the state, an INSERT or an UPDATE will be performed when saving.
     *
     * @var string
     * @internal
     */
    protected $record_state = 'new';

    /**
     * This is the array which contains the columns/data for this row
     * in the database table which the class represents. The get* and 
     * set* functions manipulate data in this array.
     *
     * @var array
     * @internal
     */
    protected $DATA = array();

    /**
     * This is the array which contains JOIN'd data. It cannot be written back
     * to; this information is read-only.
     *
     * @var array
     * @internal
     */
    protected $LOOKUPS_DATA = array();

    /**
     * This is an array that contains virtual attributes (ie, the result of
     * some function).
     *
     * @var array
     * @internal
     **/
    protected $VIRTUAL_DATA = array();

    /**
     * Set this to true to enable debugging.
     *
     * @var boolean
     **/
    public $debug = false;
    
    /**
     * The constructor to set up the wrapper object. Give this a PEAR::DB
     * connection and it will give you the world.
     *
     * This should be called from inside the child's constructor.
     * <code>
     * <?php
     * class Foo {
     *      public function __constuct($db) {
     *          parent::__construct($db);
     *      }
     *
     * }
     * </code>
     *
     * @param object PEAR::DB connector.
     * @throws ArgumentError If the connector is not an object OR if the table/pk
     *                       attributes are unset, an ArgumentError will be thrown.
     * @return void
     */
    public function __construct($db)
    {
        // Ensure this is valid.
        if(is_object($db) == false)
        {
            throw new ArgumentError('Invalid DB connector passed.',901);
        }

        $db->setOption('portability',DB_PORTABILITY_ALL);
        $db->setOption('debug',2);
        $db->setFetchMode(DB_FETCHMODE_ASSOC);
        $this->db = $db;

        switch($this->db->phptype)
        {
            case 'mysql':
            {
                $this->sql_generator = new ActiveTable_SQL_MySQL();

                break;
            } // end mysql

            case 'oci8':
            {
                $this->sql_generator = new ActiveTable_SQL_Oracle();

                break;
            } // end oci8

            default:
            {
                throw new ArgumentError('Unsupported RDBMS specified in phptype.',20);

                break;
            } // end default
        } // end db connector type switch 
        
        // TODO - Have it guess these like Ruby's AR can do...
        if($this->table_name == null)
        {
            throw new ArgumentError('No table specified.',902);
        }

        if($this->primary_key == null)
        {
            throw new ArgumentError('No PK specified.',903);
        }
        
        // Get the field list.
        $this->DATA = $this->load_fields();
            
        // Validate the lookup tables and clean up some data.
        foreach($this->LOOKUPS as $id => $LOOKUP)
        {
            if($LOOKUP['foreign_table'] == $this->table_name)
            {
                throw new ArgumentError('You cannot specify the local table as a foreign table at this time.',910);
            }

            if(array_key_exists('foreign_table_alias',$LOOKUP) == false)
            {
                $this->LOOKUPS[$id]['foreign_table_alias'] = $LOOKUP['foreign_table'];
            }

            if($LOOKUP['local_table'] == null)
            {
                $this->LOOKUPS[$id]['local_table'] = $this->table_name;
            }

            if(array_key_exists('local_key',$LOOKUP) == false)
            {
                $this->LOOKUPS[$id]['local_key'] = $this->primary_key;
            }

            if($LOOKUP['write'] == true)
            {
                $this->LOOKUPS_DATA[$LOOKUP['foreign_table']] = $this->load_fields($LOOKUP['foreign_table']);
            }
        } // end lookup validations

        // Validate the associated record set definitions and clean up some data.
        /*
        * 'record_set' => array(
        *    'class' => 'Tags',
        *    'local_table' => 'table_from_this_class',
        *    'local_key' => 'id_a',
        *    'foreign_table' => 'table_from_other_class',
        *    'foreign_key' => 'id_z',
        *    'foreign_primary_key' => 'id_z',
        * ),
        */

        foreach($this->RELATED as $record_set_name => $SET_DEFINITION)
        {
            if(array_key_exists('class',$SET_DEFINITION) == false)
            {
                throw new ArgumentError("Class not defined in record set $record_set_name.",911);
            }

            if(class_exists($SET_DEFINITION['class']) == false)
            {
                throw new ArgumentError("The class for record set $record_set_name is not a defined class.",916);
            }

            // For inspecting.
            eval('$tmp = new '.$SET_DEFINITION['class'].'($this->db'.$arg_fragment.');');

            if(array_key_exists('local_table',$SET_DEFINITION) == false)
            {
                $this->RELATED[$record_set_name]['local_table'] = $this->table_name;
            }

            if(array_key_exists('local_key',$SET_DEFINITION) == false)
            {
                throw new ArgumentError("Local key not definedin record set $record_set_name.",912);
            }

            if(array_key_exists('foreign_table',$SET_DEFINITION) == false)
            {
                $this->RELATED[$record_set_name]['foreign_table'] = $tmp->tableName();
            }
            
            if(array_key_exists('foreign_key',$SET_DEFINITION) == false)
            {
                throw new ArgumentError("Foreign key not definedin record set $record_set_name.",914);
            }
            
            if(array_key_exists('foreign_primary_key',$SET_DEFINITION) == false)
            {
                $this->RELATED[$record_set_name]['foreign_primary_key'] = $tmp->primaryKey();
            }

            if(array_key_exists('foreign_database',$SET_DEFINITION) == false)
            {
                $this->RELATED[$record_set_name]['foreign_database'] = $tmp->database();
            }

            // Intialize the ID list.
            $this->RELATED_IDS[$record_set_name] = array();
        } // end related set validation

        // Handle additional arguments.
        $args = func_get_args();
        if(sizeof($args) > 1)
        {
            array_shift($args); 
            $this->CONSTRUCTOR_ARGS = $args;
        } // end args > 1
        
    } // end __construct

    /**
     * Return the current date/time in the RDBMS' native format.
     *
     * @return string
     **/
    public function sysdate()
    {
        return $this->sql_generator->getFormattedDate(date('Y-m-d H:i:s'));
    } // end sysdate

    /**
     * Initiate a database transaction by disabling autocommit.
     *
     * @return void
     **/
    public function beginTransaction()
    {
        $this->db->autoCommit(false);

        return null;
    } // end beginTransaction

    /**
     * Commit a transaction and re-enable autocommit.
     *
     * @return void
     **/
    public function commitTransaction()
    {
        $this->db->commit();
        $this->db->autoCommit(true);

        return null;
    } // end commitTransaction

    /**
     * Roll back a transaction and re-enable autocommit.
     *
     * @return void
     **/
    public function rollbackTransaction()
    {
        $this->db->rollback();
        $this->db->autoCommit(true);

        return null;
    } // end rollbackTransaction

    /**
     * Magic method that any calls to undefined methods get routed to.
     * This provides the #get*(), #set*(), #grab*(), and #findBy*() methods.
     *
     * @internal
     */
    public function __call($method,$parameters)
    {
        if(preg_match('/^(g|s)et([A-Z][A-Za-z0-9_]*)$/',$method,$FOUND) == true)
        {
            $property_name = $this->convert_camel_case($FOUND[2]);
            $property_name = strtolower($property_name);

            if($FOUND[1] == 'g')
            {
                return $this->get($property_name);
            } // end get
            elseif($FOUND[1] == 's')
            {
                // TODO - Extract this into a #set($column,$table) like #get() is set up.
                
                // Don't let people screw around with the PK... 
                if($property_name == $this->primary_key && $this->allow_pk_write == false)
                {
                    throw new ArgumentError('Cannot modify primary key.',900);
                }
                
                if(array_key_exists($property_name,$this->DATA) == true)
                {
                    $this->DATA[$property_name] = $parameters[0];
                    return null;
                }
                else
                {
                    // Try to find a +w lookup table's shit...
                    foreach($this->LOOKUPS as $LOOKUP)
                    {
                        if($LOOKUP['write'] == true)
                        {
                            if(array_key_exists($property_name,$this->LOOKUPS_DATA[$LOOKUP['foreign_table']]) == true)
                            {
                                $this->LOOKUPS_DATA[$LOOKUP['foreign_table']][$property_name] = $parameters[0];
                                return null;
                            }
                        }
                    } // end lookup loopdewhoop
                    
                    throw new ArgumentError("That attribute ($property_name) is not in the domain of the ".get_class($this)." table. Note that lookup'd values are read-only unless otherwise enabled.",901);
                }
            } // end set
        } // end the regexp matched...
        elseif(preg_match('/^findBy([A-Z][A-Za-z0-9_]*)$/',$method,$FOUND) == true)
        {
            $property_name = $this->convert_camel_case($FOUND[1]);
            $property_name = strtolower($property_name);
            
            // $property_name;
            $value = $parameters[0];
           
            $ORDER = "";
            if($parameters[1] != null)
            {
                $ORDER = $parameters[1];
            }
            
            $params = array();
            $params[] = array(
                'table' => $this->table_name,
                'column' => $property_name,
                'value' => $value,
            );
            $ROWS = $this->findBy($params,$ORDER);
        
            return $ROWS;
        } // end finder
        elseif(preg_match('/^grab([A-Z][A-Za-z0-9_]*)$/',$method,$FOUND) == true)
        {
            $set_name = $this->convert_camel_case($FOUND[1]);
            $set_name = strtolower($set_name);
            
            // The array in RELATED_OBJECTS is not created for the set until loaded.
            // This keeps a difference between non-loaded and loaded-but-zero-rows-returned. 
            if(array_key_exists($set_name,$this->RELATED_OBJECTS) == false)
            {
                $this->load_recordset($set_name);
            }

            return $this->RELATED_OBJECTS[$set_name];
        } // end load record sets

        return false;
    } // end __call

    /**
     * Get the value back for a column from a row.
     *
     * It is preferred to use the shorthand, #getColumnName(), but in some cases
     * where JOINs cause column name collisions (status_a.description, status_b.description - which 
     * should #getDescription() look at?), this may be used and the table may be specified.
     *
     * @param string The column name to retrieve.
     * @param string|void The table name to look in. If blank, a search is done and the first occurance is returned.
     * @return string|integer|boolean The value.
     **/
    public function get($column,$table=null)
    {
        $column = strtolower($column);
        
        if($table == null)
        {
            // Try here first:
            if(array_key_exists($column,$this->DATA) == true)
            {
                return $this->DATA[$column];
            }
            elseif(array_key_exists($column,$this->VIRTUAL_DATA) == true)
            {
                return $this->VIRTUAL_DATA[$column];
            }
            else
            {
                // Find the first occurance of this key in the lookups:
                foreach($this->LOOKUPS_DATA as $LOOKUP)
                {
                    if(array_key_exists($column,$LOOKUP) == true)
                    {
                        return $LOOKUP[$column];
                    }
                } // end lookups loop
            } // end do lookups
        } // end table is not specified - generic search
        else
        {
            // Table name is given - LET'S DO IT!
            if($table == $this->table_name)
            {
                if(array_key_exists($column,$this->DATA) == true)
                {
                    return $this->DATA[$column];
                }
            } // end look in default table
            elseif($table == '__virtual')
            {
                return $this->VIRTUAL_DATA[$column];
            }
            else
            {
                if(array_key_exists($table,$this->LOOKUPS_DATA) == true)
                {
                    if(array_key_exists($column,$this->LOOKUPS_DATA[$table]) == true)
                    {
                        return $this->LOOKUPS_DATA[$table][$column];
                    }
                }
            } // end look in JOIN'd table
        } // end table specified - look it up.
        
        return null; 
    } // end get

    /* ================ */
    /* ===== FIND ===== */
    /* ================ */

    /**
     * Perform a search and return the result send. This function may be called
     * directly or via a magic method.
     *
     * $foo->findByColumnName($value) is the magical way of performing a search.
     * However, that is limitted to the one column. 
     *
     * $foo->findBy(array('column_1' => 'Y', 'column_2' => 'N')) builds AND statements
     * out and therefore can be used on to search on X number of column. 
     *
     * If you want to search by columns in LOOKUP'd tables, you may pass an array as shown
     * below. Also note that you can mix the key => value and something => array().
     *
     * <code>
     *  $TO_PASS[] = array(
     *      'table' => 'company',   // A table from LOOKUP
     *      'column' => 'type',     // A column from this table.
     *      'value' => 'contractor' // The value to search on.
     *  );
     * </code>
     *
     * That 'value' can also have an array passed as its value. If an array is give, an
     * IN will be used.
     *
     * <code>
     *  $TO_PASS[] = array(
     *      'table' => 'company',   // A table from LOOKUP
     *      'column' => 'type',     // A column from this table.
     *      'search_type' => '>'    // < =< > >= = <> //
     *      'value' => array('foo','bar','baz') // company.type IN ('foo','bar','baz') 
     *  );
     * </code>
     * 
     * As far as IS (NOT) NULL goes, you can do it like so:
     *
     * <code>
     *  $TO_PASS[] = array(
     *      'table' => 'company',   // A table from LOOKUP
     *      'column' => 'type',     // A column from this table.
     *      'search_type' => '='    // '=' is 'IS NULL', '<>' is 'IS NOT NULL' 
     *      'value' => null, 
     *  );
     * </code>
     * 
     * To get all rows for a table back, simply pass #findBy() a blank array.
     *
     * <code>
     *  $all_rows = $foo->findBy(array());
     * </code>
     *
     * *NOTE* - At this time, nesting and ORs are not supported.
     *
     * @param array Things to search on. Everything is an AND and nesting
     *              is not supported.
     * @param string ORDER BY clause.
     * @throws SQLError If an invalid SQL statement is generated (usually due to
     *                  an invalid column name getting mogged in somewhere),
     *                  this exception will be thrown.
     * @throws ArgumentError If args is either not an array or a 0-length array, an
     *                       argument error will be thrown.
     * @return array An array of __CLASS__ instances representing the dataset.
     */
    public function findBy($args,$order_by='')
    {
        if(is_array($args) == false)
        {
            throw new ArgumentError('Args must be an array.',950);
        }

        // Disabling this - We can get every row back with a blank array.
        // elseif(sizeof($args) <= 0)
        // {
        //    throw new ArgumentError('Args cannot be an empty array.',951);
        // }
        
        $SEARCH_VALUES = array();
        $AND = array();
            
        // Clear things out.
        $this->sql_generator->reset();
        $this->sql_generator->addKeys($this->table_name,array($this->primary_key));
        $this->sql_generator->addFrom($this->table_name,$this->database);
        $foo = $this->make_join($this->LOOKUPS);
        $SEARCH_VALUES = array_merge($SEARCH_VALUES,$foo);

        foreach($args as $column => $value)
        {
            $bar = $this->make_wheres($column,$value);
            if($bar !== null)
            {
                if(is_array($bar) == true)
                {
                    $SEARCH_VALUES = array_merge($SEARCH_VALUES,$bar);
                }
                else
                {
                    $SEARCH_VALUES[] = $bar; 
                }
            }
            else
            {
                $SEARCH_VALUES[] = '0';
            }
        } // end loop

        // TODO - This is taken exactly as-is and put into the query. Probably a *bad* idea...
        if($order_by != null)
        {
            $this->sql_generator->addOrder($order_by);
        }
        
        $sql = $this->sql_generator->getQuery('select');
        $this->debug($sql,'sql');
        
        $handle = $this->db->prepare($sql);
        $resource = $this->db->execute($handle,$SEARCH_VALUES);
        $this->db->freePrepared($handle);

        if(PEAR::isError($resource))
        {
            throw new SQLError($resource->getDebugInfo(),$resource->userinfo,909);
        }

        $SET = array();
        while($resource->fetchInto($row))
        {
            // If things were passed to this instance's constructor (additonal db connectors?),
            // then pass them on to what we're finding.
            $arg_fragment = '';
            if(sizeof($this->CONSTRUCTOR_ARGS) > 0)
            {
                $keys = array_keys($this->CONSTRUCTOR_ARGS);
                $arg_fragment = array();
                
                foreach($keys as $key)
                {
                    $arg_fragment[] = '$this->CONSTRUCTOR_ARGS['.$key.']';
                } // end key loop

                $arg_fragment = ','.implode(',',$arg_fragment);
                
            } // end additional constructor arg handler
            
            eval('$tmp = new '.get_class($this).'($this->db'.$arg_fragment.');');
            $tmp->load(array_shift($row));
            $SET[] = $tmp;
        } // end loop
        
        return $SET;
    } // end findBy
    

    /* ================ */
    /* ===== CRUD ===== */
    /* ================ */

    /**
     * Load a row from the database into this instance of the object
     * based on the specified ID. If any LOOKUPS are defined, they will be
     * joined against.
     *
     * <code>
     * $record = new Staff($db);
     * $record->load(1);
     * </code>
     *
     * @param integer ID for the primary key for the record you wish to load.
     * @throws SQLError If an invalid SQL statement is generated (usually due to
     *                  an invalid column name getting mogged in somewhere),
     *                  this exception will be thrown.
     * @return void
     */ 
    public function load($pk_id)
    {
        /*
        * Column names follow tablename__column_name to avoid collisions. 
        *                              ^ Note that there are TWO underscores.
        *
        * 1. Build column names for the base table & the base SQL statement.
        * 2. If there are any lookups, build their key names and JOIN clause.
        */
        $keys = array_keys($this->DATA);
        $fkeys = array();

        $this->sql_generator->reset();
        $this->sql_generator->addKeys($this->table_name,$keys);
        
        // There are lookup tables - DO IT!
        if(sizeof($this->LOOKUPS) > 0)
        {
            foreach($this->LOOKUPS as $table_index_number => $LOOKUP)
            {
                $fkeys[$table_index_number] = array_keys($this->load_fields($LOOKUP['foreign_table']));
                $this->sql_generator->addKeys($LOOKUP['foreign_table_alias'],$fkeys[$table_index_number],$table_index_number);
            } // end loopup loop
        } // end lookups > 0
        
        if(sizeof($this->VIRTUAL) > 0)
        {
            // This is used below - but only do it once and only if there's virtual attributes
            // to consider.
            $virt_map = array_keys($this->VIRTUAL);
            
            $i = 0;
            foreach($this->VIRTUAL as $virtual_key => $function_fragment)
            {
                $this->sql_generator->addVirtualKey($function_fragment,$i);

                $i++;
            } // end virtual loop
        } // end virtual > 0
        
        $this->sql_generator->addFrom($this->table_name,$this->database);
        
        // A filter on a join might make this more than just the PK... 
        $SEARCH_VALUES = $this->make_join($this->LOOKUPS);
        $this->sql_generator->addWhere($this->table_name,$this->primary_key);
        $SEARCH_VALUES[] = $pk_id;
        
        $this->sql_generator->addLimit(1);
        $sql = $this->sql_generator->getQuery('select');
        $this->debug($sql,'sql');

        $handle = $this->db->prepare($sql);
        $resource = $this->db->execute($handle,$SEARCH_VALUES);
        $this->db->freePrepared($handle);
        if(PEAR::isError($resource))
        {
            throw new SQLError($resource->getDebugInfo(),$resource->userinfo,904);
        }
        $resource->fetchInto($ROW);

        if(is_array($ROW) == true)
        {
            foreach($ROW as $key => $value)
            {
                $key = strtolower($key);
                $key = substr($key,1); // Strip off the leading 'c' - used to prevent oracle from crying.
                
                $table_id = substr($key,0,strpos($key,'_'));
                $column_id = substr($key,strpos($key,'_')+1);

                // Translate this shit back in to something human-readable.
                // Thank Oracle's 30-character limit on aliases for this x_0
                // crap.
                if($table_id == 'x')
                {
                    $table = $this->table_name;

                    $column = array_keys($this->DATA);
                    $column = $column[$column_id];
                }
                elseif($table_id == 'virt')
                {
                    $table = 'VIRT';
                    $this->VIRTUAL_DATA[$virt_map[$column_id]] = $value;
                }
                else
                {
                    $table = $this->LOOKUPS[$table_id]['foreign_table_alias'];
                    $column = $fkeys[$table_id][$column_id];
                } // end table name resolver
                
                if($table == $this->table_name)
                {
                    $this->DATA[$column] = $value;
                }
                elseif($table == 'VIRT')
                {
                    $this->VIRTUAL_DATA[$virt_map[$column_id]] = $value;
                }
                else
                {
                    $this->LOOKUPS_DATA[$table][$column] = $value;
                }
            } // end row loop

            $this->record_state = 'loaded';
        } // end got result array

        // Load the related IDs.
        foreach($this->RELATED as $record_set_name => $SET_DEFINITION)
        {
            $this->RELATED_IDS[$record_set_name] = $this->load_recordset_id_list($SET_DEFINITION); 
        } // end related load loop

        if($this->record_state == 'loaded')
        {
            return true;
        }
        
        return false;
    } // end load

    /**
     * Delete a record from the table. This can be called in two situations:
     *
     * 1. You already have the record you want to delete loaded. This can be
     *    called with no arguments.
     * 2. If you know the ID. Call this on an instance of YourTable'sClass
     *    with the ID as an argument.
     * 
     * @param integer The ID number (optional).
     * @throws SQLError If an invalid SQL statement is generated (usually due to
     *                  an invalid column name getting mogged in somewhere),
     *                  this exception will be thrown.
     * @return void
     */
    public function destroy($id=null)
    {
        if($id == null)
        {
            $id = $this->DATA[$this->primary_key];
        }
        
        if($id == 0)
        {
            throw new ArgumentError('No ID specified.',905);
        }

        $sql = "DELETE FROM {$this->table_name} WHERE {$this->primary_key} = ?";
        $this->debug($sql,'sql');
        
        $handle = $this->db->prepare($sql);
        $resource = $this->db->execute($handle,array($id));
        $this->db->freePrepared($handle);
        if(PEAR::isError($resource))
        {
            throw new SQLError($resource->getDebugInfo(),$resource->userinfo,906);
        }
        
        // Load blanks back in to reset the object.
        $this->DATA = $this->load_fields(); 
        $this->record_state = 'new';

        return true;
    } // end destroy

    /**
     * This inserts a new record into the table. This can be called in one of two ways:
     *
     * 1. Your object has had setAttr($value) called on it for all of your values. In that
     *    case, this behaves exactly like save() would and insert your new row.
     * 2. You may specify an array with the column name as the key and the data as the value.
     *    It will be loaded onto the object and then saved.
     *
     * When this is done with its insert, it will load the new row into this instance of the
     * object.
     *
     * @param array The data to use for your new row. (Optional)
     * @throws SQLError If an invalid SQL statement is generated (usually due to
     *                  an invalid column name getting mogged in somewhere),
     *                  this exception will be thrown.
     * @return void
     */
    public function create($new_data=array())
    {
        /* 
        * Ideally, this would be a static method that returns to you your record
        * (as a new instance of this object). 
        *
        * However, PHP has a bug where __CLASS__ is always the class it is used in
        * (ActiveTable) instead of the class it is called from (your child) when it
        * is used in a static method.
        */ 

        // Set it up.
        foreach($new_data as $key => $value)
        {
            $key = ucfirst($key);
            eval("\$this->set$key(\$value);");
        } // end loop

        try
        {
            $this->save();
        }
        catch(SQLError $e)
        {
            // Pass it up.
            throw $e;
        }
    } // end create

    /**
     * Perform an update on your record. When it is done with the update, the record
     * will be reloaded.
     *
     * @throws SQLError If an invalid SQL statement is generated (usually due to
     *                  an invalid column name getting mogged in somewhere),
     *                  this exception will be thrown.
     * @return void
     */
    public function save()
    {
        $DATA = $this->DATA;

        // PEAR::DB will try to insert NULL instead of '' if the value is null.
        foreach($DATA as $key => $value)
        {
            if($value == null)
            {
                $value = '';
            }
                
            $DATA[$key] = $value;
        } // end loop
        
        if($this->record_state == 'new')
        {
            if($this->allow_pk_write == false)
            {
                unset($DATA[$this->primary_key]);
            }
            
            $resource = $this->db->autoExecute($this->table_name,$DATA,DB_AUTOQUERY_INSERT);
            if(PEAR::isError($resource))
            {
                throw new SQLError($resource->getDebugInfo(),$resource->userinfo,907);
            }
            $this->debug($resource->userinfo,'sql');

            // TODO
            // PEAR::DB offers no way to get back the last_insert_id in a generic way.
            // As such, this is a MySQL-specific hack. Oh well.
            $id = $this->db->getOne("SELECT last_insert_id() AS last_insert_id");
            if(PEAR::isError($id))
            {
                throw new SQLError($resource->getDebugInfo(),$resource->userinfo,908);
            }
        } // end new
        elseif($this->record_state == 'loaded')
        {
            $DATA = $this->DATA;

            if($this->allow_pk_write == false)
            {
                unset($DATA[$this->primary_key]);
            }
            
            $resource = $this->db->autoExecute($this->table_name,$DATA,DB_AUTOQUERY_UPDATE,"{$this->primary_key} = ".$this->db->quoteSmart($this->DATA[$this->primary_key]));
            if(PEAR::isError($resource))
            {
                throw new SQLError($resource->getDebugInfo(),$resource->userinfo,909);
            }
            $this->debug($resource->userinfo,'sql');

            $id = $this->DATA[$this->primary_key];

            // Save back to the writable lookup tables.
            foreach($this->LOOKUPS as $LOOKUP )
            {
                if($LOOKUP['write'] == true)
                {
                    $resource = $this->db->autoExecute($LOOKUP['foreign_table'],$this->LOOKUPS_DATA[$LOOKUP['foreign_table']],DB_AUTOQUERY_UPDATE,"{$LOOKUP['foreign_key']} = ".$this->db->quoteSmart($this->DATA[$LOOKUP['local_key']]));
                    if(PEAR::isError($resource))
                    {
                        throw new SQLError($resource->getDebugInfo(),$resource->userinfo,910);
                    }
                } 
            } // end lookup loop
        } // end loaded

        // Refresh from the DB.
        $this->load($id);
    } // end save

    /* ====================================================================================== */
    /* =============== Internal methods such as helpers, debugging stuff, etc. ============== */
    /* ====================================================================================== */

    /** 
     * Write a debug message to the debugging system.
     *
     * Nothing will be done with this unless $debug is true.
     *
     * @param string The message to note.
     * @param string A message type. 'info' is an informational note, 'sql' is a SQL query.
     **/
    protected function debug($message,$type='info')
    {
        if($this->debug == true)
        {
            print $message;
        }
        
        return true;
    } // end debug

    /* ====================================================================================== */
    /* ================== Informational doodads for inspecting the objects. ================= */
    /* ====================================================================================== */
    public function tableName()
    {
        return $this->table_name;
    } // end tableName

    public function primaryKey()
    {
        return $this->primary_key;
    } // end primaryKey

    public function database()
    {
        return $this->database;
    } // end database

    /* ====================================================================================== */
    /* ===== Implementation details. These are irrelevant. Do not read below this line. ===== */
    /* ====================================================================================== */

    /**
     * Grab a list of fields in the table.
     *
     * @var The table name. This defaults to the current table.
     * @internal
     */
    private function load_fields($table=null,$database=null)
    {
        if($table == null)
        {
            $table = $this->table_name;
        }

        if($database == null && $this->database != null)
        {
            $database = $this->database;
        }
        
        $sql = $this->sql_generator->getDescribeTable($table,$database);
        $resource = $this->db->query($sql);
        if(PEAR::isError($resource))
        {
            throw new SQLError($resource->getDebugInfo(),$resource->userinfo,905);
        }
        
        $RESULT = array();
        while($resource->fetchInto($ROW))
        {
            // Normalize into all lower case (I'm lookin at you, Oracle...)
            foreach($ROW as $key => $value)
            {
                $ROW[strtolower($key)] = $value;
            }
            
            $RESULT[strtolower($ROW['field'])] = null;
        } // end loop

        return $RESULT;
    } // end load_fields

    private function load_recordset_id_list($RELATED)
    {
        /*
        * 'record_set' => array( // Verbose example.
        *     'class' => 'Tags',
        *     'local_table' => 'table_from_this_class', // Defaults to the 'primary' table.
        *     'local_key' => 'id_a',
        *     'foreign_table' => 'table_from_other_class', // Defaults to the 'primary' table.
        *     'foreign_key' => 'id_z',
        *     'foreign_primary_key' => 'table_from_other_class_id', // Defaults to the PK.
        *     'foreign_database' => 'db', // optional
        * ),
        * 
        * SELECT $foreign_primary_key 
        * FROM foreign_table 
        * INNER JOIN $local_table ON $local_table.$local_key = $foreign_table.$foreign_key
        */

        $this->sql_generator->reset();
        $this->sql_generator->addKeys($RELATED['foreign_table'],array($RELATED['foreign_primary_key']));
        $this->sql_generator->addFrom($RELATED['foreign_table'],$RELATED['foreign_database']);
        $this->sql_generator->addJoinClause($RELATED['foreign_table'],$RELATED['foreign_key'],$RELATED['local_table'],$RELATED['local_table'],$RELATED['local_key'],'inner',$RELATED['foreign_database']);
        $this->sql_generator->addWhere($RELATED['local_table'],$RELATED['local_key']);
        $sql = $this->sql_generator->getQuery('select');
        $this->sql_generator->reset();

        $resource = $this->db->query($sql,array($this->get($this->primary_key,$this->table_name)));
        
        if(PEAR::isError($resource))
        {
            throw new SQLError($resource->getDebugInfo(),$resource->userinfo,100);
        }

        $IDS = array();
        while($resource->fetchInto($ROW))
        {
            $IDS[] = $ROW['cx_0'];
        }

        return $IDS;
    } // end load_recordset_id_list
    
    /**
     * This loads a set of objects that correspond to an entry in RELATED.
     *
     * @internal
     * @param string The name of the record set to be loaded.
     * @throws ArgumentError ArgumentErrors will be thrown if the record set specified
     *                       is not actually defined.
     * @return void
     **/
    private function load_recordset($record_set_name)
    {
        if(array_key_exists($record_set_name,$this->RELATED) == false)
        {
            throw new ArgumentError("Invalid record set '$record_set_name' specified.",915);
        }

        // Alias.
        $SET = $this->RELATED[$record_set_name];
        $IDS = $this->RELATED_IDS[$record_set_name];
        $this->RELATED_OBJECTS[$record_set_name] = array();

        // This should never happen...the first exception would be thrown instead...but just in case something
        // happens to the instance's state...
        if(is_array($IDS) == false)
        {
            throw new ArgumentError("Record ID array for $record_set_name was not created. Please report this error.",25);
        }

        foreach($IDS as $id)
        {
            eval('$tmp = new '.$SET['class'].'($this->db);');
            $tmp->load($id);

            $this->RELATED_OBJECTS[$record_set_name][] = $tmp;
        } // end ID load loop
        
        return true;
    } // end load_recordset

    /**
     * Convert the pretty setSomeAttribute to some_attribute for use elsewhere. 
     *
     * @internal
     */
    private function convert_camel_case($studly_word)
    {
        $simple_word = preg_replace('/([a-z0-9])([A-Z])/','\1_\2',$studly_word);
        $simple_word = strtolower($simple_word);

        return $simple_word;
    } // end convert_camel_case
    
    private function make_join($LOOKUPS)
    {
        if(is_array($LOOKUPS) == true)
        {
            $FILTER_VALUES = array();
            foreach($LOOKUPS as $LOOKUP)
            {
                $this->sql_generator->addJoinClause($LOOKUP['local_table'],$LOOKUP['local_key'],$LOOKUP['foreign_table'],$LOOKUP['foreign_table_alias'],$LOOKUP['foreign_key'],$LOOKUP['join_type'],$LOOKUP['database']);

                if(array_key_exists('filter',$LOOKUP) == true)
                {
                    foreach($LOOKUP['filter'] as $column => $value)
                    {
                        $foo = $this->make_wheres($column,$value);

                        if($foo !== null)
                        {
                            if(is_array($foo) == true)
                            {
                                $FILTER_VALUES = array_merge($FILTER_VALUES,$foo);
                            }
                            else
                            {
                                $FILTER_VALUES[] = $foo; 
                            }
                        }
                    } // end filter loop
                } // end filter?
            }
        } // end is array == true

        return $FILTER_VALUES;
    } // end make_join

    private function make_wheres($column,$value)
    {
        // You can pass either an array (if you want othertable.$column,column = value) or 'column' => 'value'.
        if(is_array($value) == true)
        {
            // no table specified...? default it!
            if(array_key_exists('table',$value) == false)
            {
                $value['table'] = $this->table_name;
            }

            if(array_key_exists('column',$value) == false || array_key_exists('value',$value) == false)
            {
                throw new ArgumentError('Column or value not given.',951);
            }
            
            if(array_key_exists('search_type',$value) == false)
            {
                $value['search_type'] = '=';
            }
            
            if(in_array($value['search_type'],array('>','>=','<','<=','<>','=')) == false)
            {
                throw new ArgumentError('Invalid search type given.',955);
            }

            if(is_array($value['value']) == true)
            {
                $in_type = '';
                if($value['search_type'] == '=')
                {
                    $in_type = 'in';
                }
                elseif($value['search_type'] == '<>')
                {
                    $in_type = 'not_in';
                }
                else
                {
                    throw new ArgumentError('Invalid search type given for in. Valid values are = and <>.',956);
                }
                
                $this->sql_generator->addWhere($value['table'],$value['column'],$in_type,sizeof($value['value']));

                $search_value = array();
                foreach($value['value'] as $in_val)
                {
                    $search_value[] = $in_val;
                }
            } // end in
            else
            {
                // The === ensures 0 won't be null.
                if($value['value'] === null)
                {
                    $is_type = '';
                    if($value['search_type'] == '=')
                    {
                        $is_type = 'is';
                    }
                    elseif($value['search_type'] == '<>')
                    {
                        $is_type = 'is_not';
                    }
                    else
                    {
                        throw new ArgumentError('Invalid search type given for IS. Valid values are = and <>.',957);
                    }
                } // end value is null
                
                if($is_type == null)
                {
                    $type = $value['search_type'];
                }
                else
                {
                    $type = $is_type;
                }
                
                $this->sql_generator->addWhere($value['table'],$value['column'],$type);
                $search_value = $value['value'];
            } // end =
        } // end is_array
        else
        {
            $this->sql_generator->addWhere($this->table_name,$column);
            $search_value = $value;
        } // end string

        return $search_value;
    } // end make_wheres

} // end ActiveTable


?>
