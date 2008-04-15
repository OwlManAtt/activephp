<?php
/**
 * An implementation of the ActiveRecord pattern at its most basic. 
 *
 * @package    ActivePHP 
 * @author     OwlManAtt <owlmanatt@gmail.com> 
 * @copyright  2007, Yasashii Syndicate 
 * @version    2.3.0
 **/

/**
 * SQL generator libraries.
 **/
require_once('SqlGenerators/interface.inc.php');
require_once('SqlGenerators/mysql.class.php');
require_once('SqlGenerators/sqlite3.class.php');
require_once('SqlGenerators/oci.class.php');

/**
 * Table definition cacher libraries. 
 **/
require_once('Cachers/interface.inc.php');
require_once('Cachers/globals.class.php');
require_once('Cachers/apc.class.php');

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
     * An ActiveTable_SQL_* class to be used for writing SQL statements.
     *
     * The appropriate driver for your database will be determined in the constructor.
     * The DSN for the PEAR::DB connector is examined and the driver is spawned.
     *
     * @internal
     * @var mixed 
     */
    protected $sql_generator;

    /**
     * A variable to hold an ActiveTable_Cache_* class to be used for holding
     * on to table definitions.
     * 
     * @internal
     * @var mixed 
     **/
    protected $cacher;

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
     * <code>
     * array (
     *    'local_key' => 'the key in $this table',
     *    'foreign_key' => 'the key in your lookup table',
     *    'foreign_table' => 'the lookup table's name',
     *    'foreign_table_alias' => 'the lookup table's alias (needed to join to the same table >1 times)',
     *    'join_type' => 'inner|left',
     *    'write' => false|true,
     *    'filter' => 'See the findBy documentation. Pass one of those arrays.',
     * );
     * </code>
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
     *       'one' => false, // Defaults to false, set to true to act like
     *                       // #findOneBy().
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
     * The path to write logfiles out to. 
     * 
     * @var string
     */
    public $logfile_path = '/tmp/active_table.log';

    /** 
     * Debug messages are stored in this array. 
     *
     * @var array
     **/
    public $debug_messages = array();
    
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
        $start = microtime(true);
        
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
                $this->sql_generator = 'ActiveTable_SQL_MySQL';

                break;
            } // end mysql

            case 'oci8':
            {
                $this->sql_generator = 'ActiveTable_SQL_Oracle';

                break;
            } // end oci8

            case 'sqlite3':
            {
                $this->sql_generator = 'ActiveTable_SQL_Sqlite3';

                break;
            } // end sqlite3

            default:
            {
                throw new ArgumentError('Unsupported RDBMS specified in phptype.',20);

                break;
            } // end default
        } // end db connector type switch 

        // Pick a Cacher to load.
        if(ini_get('apc.enabled') == 1)
        {
            // We have APC! HELL YES!
            $this->debug("APC detected.",'cache');
            $this->cacher = new ActiveTable_Cache_APC();
        } 
        else
        {
            $this->cacher = new ActiveTable_Cache_Globals();
        }
        
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
        *    'one' => false, // Behave like #findOneBy().
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

            if(array_key_exists('one',$SET_DEFINITION) == false)
            {
                $this->RELATED[$record_set_name]['one'] = false; 
            } // end one = false

            // For inspecting. Only do so if absolutely required, though.
            // In cases where you want to reciprocate RELATEDs, DEFINE THE
            // FT/FK/FPK IN ONE to avoid INFINITE LOOPS.
            if(array_key_exists('foreign_table',$SET_DEFINITION) == false ||
               array_key_exists('foreign_key',$SET_DEFINITION) == false ||
               array_key_exists('foreign_primary_key',$SET_DEFINITION) == false
            )
            {
                // Avoid doing the eval() in most cases - its slow.
                if($arg_fragment != null)
                {
                    eval('$tmp = new '.$SET_DEFINITION['class'].'($this->db'.$arg_fragment.');');
                }
                else
                {
                    $tmp = new $SET_DEFINITION['class']($this->db);
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
            } // end need to inspect

            if(array_key_exists('local_table',$SET_DEFINITION) == false)
            {
                $this->RELATED[$record_set_name]['local_table'] = $this->table_name;
            }

            if(array_key_exists('local_key',$SET_DEFINITION) == false)
            {
                throw new ArgumentError("Local key not defined in record set $record_set_name.",912);
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
       
        $total = round((microtime(true) - $start),4);
        $this->debug("#__construct() executed in '$total' seconds.",'method_time'); 
    } // end __construct

    /**
     * Return the current date/time in the RDBMS' native format.
     *
     * @return string
     **/
    public function sysdate()
    {
        return $this->newSqlGenerator()->getFormattedDate(date('Y-m-d H:i:s'));
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
     * This provides the #get*(), #set*(), #grab*(), #findBy*(), and
     * #findOneBy() methods.
     *
     * @internal
     */
    public function __call($method,$parameters)
    {
        $start = microtime(true);

        if(preg_match('/^(g|s)et([A-Z][A-Za-z0-9_]*)$/',$method,$FOUND) == true)
        {
            $property_name = $this->convert_camel_case($FOUND[2]);

            if($FOUND[1] == 'g')
            {
                $total = round((microtime(true) - $start),4);
                $this->debug("#__call($method) executed in '$total' seconds.",'method_time'); 

                return $this->get($property_name);
            } // end get
            elseif($FOUND[1] == 's')
            {
                $total = round((microtime(true) - $start),4);
                $this->debug("#__call($method) executed in '$total' seconds.",'method_time');

               return $this->set($parameters[0],$property_name);
            } // end set
        } // end the regexp matched...
        elseif(preg_match('/^findBy([A-Z][A-Za-z0-9_]*)$/',$method,$FOUND) == true)
        {
            $property_name = $this->convert_camel_case($FOUND[1]);
            
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
            $ROWS = $this->findBy($params,$ORDER,$parameters[2],$parameters[3],$parameters[4]);
       
            $total = round((microtime(true) - $start),4);
            $this->debug("#__call($method) executed in '$total' seconds.",'method_time');       
        
            return $ROWS;
        } // end finder
        elseif(preg_match('/^grab([A-Z][A-Za-z0-9_]*)$/',$method,$FOUND) == true)
        {
            $set_name = $this->convert_camel_case($FOUND[1]);
 
            $total = round((microtime(true) - $start),4);
            $this->debug("#__call($method) executed in '$total' seconds.",'method_time');       
          
            return $this->grab($set_name,$parameters[0],$parameters[1],$parameters[2],$parameters[3]); 
        } // end load record sets
        elseif(preg_match('/^findOneBy([A-Z][A-Za-z0-9_]*)$/',$method,$FOUND) == true)
        {
            $property_name = $this->convert_camel_case($FOUND[1]);
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
            $row = $this->findOneBy($params,$ORDER);
       
            $total = round((microtime(true) - $start),4);
            $this->debug("#__call($method) executed in '$total' seconds.",'method_time');       
        
            return $row;
        } // end findOneBy

        $total = round((microtime(true) - $start),4);
        $this->debug("#__call() executed in '$total' seconds.",'method_time'); 

        trigger_error("Call to undefined method $method on line ".__LINE__." in ".__FILE__.'.',E_USER_ERROR);
 
        return false;
    } // end __call

    /**
     * Grab RELATED record set.
     *
     * @param string $record_set A record set name.
     * @param string $order_by A raw ORDER BY clause.
     * $param boolean $count If true, the number of rows that would be returned is returned.
     * @param integer $slice_start The beginning of a slice from the related rows.
     * @param integer $slice_end The end of a slice from the related rows. 
     * @param bool $reset Throw away any cached results from previous grabs.
     * @return array
     **/
    public function grab($record_set,$order_by=null,$count=false,$slice_start=null,$slice_end=null,$reset=false)
    {
        $start = microtime(true);

        if(array_key_exists($record_set,$this->RELATED) == false)
        {
            throw new ArgumentError('No such recordset is defined.');
        } // end recordset does not exist

        if(($slice_start === null && $slice_end === null) == false &&
            ($slice_start !== null && $slice_end !== null) == false
        )
        {
            throw new ArgumentError('Must specify either no slice arguments or both slice arguments.');
        } // end problem w/ args.

        // Give the slice a unique name, if we want a slice. Otherwise, if someone wanted the full set,
        // we would have an issue. 
        $record_set_name = $record_set;
        if($slice_start !== null && $slice_end !== null)
        {
            $record_set_name .= "_{$slice_start},{$slice_end}";
        }
        
        // The array in RELATED_OBJECTS is not created for the set until loaded.
        // This keeps a difference between non-loaded and loaded-but-zero-rows-returned. 
        if(array_key_exists($record_set_name,$this->RELATED_OBJECTS) == false || $reset == true)
        {
            $this->RELATED_IDS[$record_set_name] = $this->load_recordset_id_list($this->RELATED[$record_set],$order_by,$slice_start,$slice_end);
           
            // TODO
            // Making this into a count(*) would probably be faster, but this is OK for the time being. 
            if($count == true)
            {
                $total = round((microtime(true) - $start),4);
                $this->debug("#grab() executed in '$total' seconds.",'method_time');

                return sizeof($this->RELATED_IDS[$record_set_name]);
            } // end count

            $this->load_recordset($record_set,$record_set_name,$order_by);
        } // end record set not loaded

        $total = round((microtime(true) - $start),4);
        $this->debug("#grab() executed in '$total' seconds.",'method_time');

        return $this->RELATED_OBJECTS[$record_set_name];
    } // end grab

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
        $start = microtime(true);
        
        $column = strtolower($column);
        
        if($table == null)
        {
            // Try here first:
            if(array_key_exists($column,$this->DATA) == true)
            {
                $total = round((microtime(true) - $start),4);
                $this->debug("#get() executed in '$total' seconds.",'method_time');

                return $this->DATA[$column];
            }
            elseif(array_key_exists($column,$this->VIRTUAL_DATA) == true)
            {
                $total = round((microtime(true) - $start),4);
                $this->debug("#get() executed in '$total' seconds.",'method_time');
                
                return $this->VIRTUAL_DATA[$column];
            }
            else
            {
                // Find the first occurance of this key in the lookups:
                foreach($this->LOOKUPS_DATA as $LOOKUP)
                {
                    if(array_key_exists($column,$LOOKUP) == true)
                    {
                        $total = round((microtime(true) - $start),4);
                        $this->debug("#get() executed in '$total' seconds.",'method_time');
                        
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
                    $total = round((microtime(true) - $start),4);
                    $this->debug("#get() executed in '$total' seconds.",'method_time');
                    
                    return $this->DATA[$column];
                }
            } // end look in default table
            elseif($table == '__virtual')
            {
                $total = round((microtime(true) - $start),4);
                $this->debug("#get() executed in '$total' seconds.",'method_time');
                
                return $this->VIRTUAL_DATA[$column];
            }
            else
            {
                if(array_key_exists($table,$this->LOOKUPS_DATA) == true)
                {
                    if(array_key_exists($column,$this->LOOKUPS_DATA[$table]) == true)
                    {
                        $total = round((microtime(true) - $start),4);
                        $this->debug("#get() executed in '$total' seconds.",'method_time');
                        
                        return $this->LOOKUPS_DATA[$table][$column];
                    }
                }
            } // end look in JOIN'd table
        } // end table specified - look it up.
        
        $total = round((microtime(true) - $start),4);
        $this->debug("#get() executed in '$total' seconds.",'method_time');

        return null; 
    } // end get

    /**
     * Set the value for a column.
     *
     * It is preferred to use the shorthand, #setColumnName(), but in some cases
     * where JOINs cause column name collisions (status_a.description, status_b.description - which 
     * should #setDescription() look at?), this may be used and the table may be specified.
     *
     * @param mixed The new value.
     * @param string The column name to update.
     * @param string|void The table name to look in. If blank, a search is done and the first occurance is updated.
     * @return string|integer|boolean The value.
     **/
    public function set($value,$column,$table=null)
    {
        $start = microtime(true);
        
        $column = strtolower($column);

        if($column == $this->primary_key && $this->allow_pk_write == false)
        {
            throw new ArgumentError('Cannot modify primary key.',900);
        }
        
        if($table != null)
        {
            if($table == $this->table_name)
            {
                if(array_key_exists($column,$this->DATA) == true)
                {
                    $this->DATA[$column] = $value;

                    $total = round((microtime(true) - $start),4);
                    $this->debug("#set() executed in '$total' seconds.",'method_time');
                    
                    return null;
                } // end key exists in primary tabl;e
            } // end table is primary
            else
            {
                if(array_key_exists($table,$this->LOOKUPS_DATA) == true)
                {
                    // Find the table and see if it's +w.
                    foreach($this->LOOKUPS as $LOOKUP)
                    {
                        if($LOOKUP['foreign_table'] == $table)
                        {
                            if($LOOKUP['write'] == true)
                            {
                                if(array_key_exists($column,$this->LOOKUPS_DATA[$table]) == true)
                                {
                                    $this->LOOKUPS_DATA[$table][$column] = $value;

                                    $total = round((microtime(true) - $start),4);
                                    $this->debug("#set() executed in '$total' seconds.",'method_time');
                                    
                                    return null;
                                }
                            } // end +w = true
                            break;
                        } // end tables match
                    } // end lookup loop
                } // end ensure table exists    
            } // end lookup table
        } // end table specified
        else
        {
            if(array_key_exists($column,$this->DATA) == true)
            {
                $this->DATA[$column] = $value;

                $total = round((microtime(true) - $start),4);
                $this->debug("#set() executed in '$total' seconds.",'method_time');
                
                return null;
            }
            else
            {
                // Try to find a +w lookup table's shit...
                foreach($this->LOOKUPS as $LOOKUP)
                {
                    if($LOOKUP['write'] == true)
                    {
                        if(array_key_exists($column,$this->LOOKUPS_DATA[$LOOKUP['foreign_table']]) == true)
                        {
                            $this->LOOKUPS_DATA[$LOOKUP['foreign_table']][$column] = $value;

                            $total = round((microtime(true) - $start),4);
                            $this->debug("#set() executed in '$total' seconds.",'method_time');

                            return null;
                        }
                    }
                } // end lookup loopdewhoop
                
                throw new ArgumentError("That attribute ($column) is not in the domain of the ".get_class($this)." table. Note that lookup'd values are read-only unless otherwise enabled.",901);
            } // end try +w'd lookup tables
        } // end default search mode

    } // end set

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
     * LIKE and NOT LIKE comparisons can be done with the additional 'like' parameter. To do LIKE,
     * simply specify 'like' => true. This default search_type to '=', but you may explicitly declare 
     * search_type as '='.
     *
     * To do NOT_LIKE, set search_type = <> and like = true.
     * 
     * <code>
     *  $TO_PASS[] = array(
     *      'table' => 'company',
     *      'column' => 'type',
     *      'like' => true,
     *      'search_type' => '<>',
     *      'value' => '%contract%'
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
     * @param boolean Return count instead of results, true or false.
     * @throws SQLError If an invalid SQL statement is generated (usually due to
     *                  an invalid column name getting mogged in somewhere),
     *                  this exception will be thrown.
     * @throws ArgumentError If args is either not an array or a 0-length array, an
     *                       argument error will be thrown.
     * @return array An array of __CLASS__ instances representing the dataset.
     */
    public function findBy($args,$order_by='',$count=false,$slice_start=null,$slice_end=null)
    {
        $start = microtime(true);
        
        if(is_array($args) == false)
        {
            throw new ArgumentError('Args must be an array.',950);
        }

        if(($slice_start === null && $slice_end === null) == false &&
            ($slice_start !== null && $slice_end !== null) == false
        )
        {
            throw new ArgumentError('Must specify either no slice arguments or both slice arguments.');
        } // end problem w/ args.

        /*
        * Translate into start,# to fetch.
        *
        * == MySQL
        * $total = $end - $start;
        * $limit = "LIMIT $start,$total";
        *
        * == OCIAIDS
        *   select * from
        *   (
        *       select
        *       a.*, ROWNUM rnum
        *       from (
        *         $query
        *       ) a
        *       where ROWNUM <= $end
        *   )
        *   where rnum  >= $start
        */

        $SEARCH_VALUES = array();
        $AND = array();
            
        // Clear things out.
        $sql_generator = $this->newSqlGenerator();
        
        // If count is not enabled, add the columns. Otherwise, add a count. 
        if($count == false)
        {
            $columns = $this->make_columns($sql_generator);
            $sql_generator = $columns['sql_generator'];

            if($sql_generator->getMagicPkName() != null)
            {
                $sql_generator->addMagicPkToKeys($this->table_name);
            }
        } // end get data
        else
        {
            $sql_generator->addVirtualKey('count(*)',0);
        } // end count(*)
        
        $sql_generator->addFrom($this->table_name,$this->database);
        $foo = $this->make_join($this->LOOKUPS,$sql_generator);
        $sql_generator = $foo['sql_generator'];
        
        if(is_array($foo['search_values']) == true)
        {
            $SEARCH_VALUES = array_merge($SEARCH_VALUES,$foo['search_values']);
        }
        elseif($foo['search_values'] != null)
        {
            $SEARCH_VALUES[] = $foo['search_values'];
        }

        foreach($args as $column => $value)
        {
            $where = $this->make_wheres($column,$value,$sql_generator);
            $sql_generator = $where['sql_generator'];
            $bar = $where['search_values'];
            
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
            //else
            //{
            //    $SEARCH_VALUES[] = '0';
            //}
        } // end loop

        // TODO - This is taken exactly as-is and put into the query. Probably a *bad* idea...
        if($order_by != null)
        {
            $sql_generator->addOrder($order_by);
        }

        if($slice_start !== null && $slice_end !== null)
        {
            $sql_generator->setSlice($slice_start,$slice_end);
        } // end slice
        
        $sql = $sql_generator->getQuery('select');
        $handle = $this->db->prepare($sql);
        $resource = $this->execute($handle,$SEARCH_VALUES);
        $this->debug($this->db->last_query,'sql');
        
        $this->db->freePrepared($handle);

        if(PEAR::isError($resource))
        {
            throw new SQLError($resource->getDebugInfo(),$resource->userinfo,909);
        }

        // Determine if #load() has been defined in the child. If it has,
        // it's probably safe to assume that we should call it. If #load()
        // has been left as-is, then I know that there is no need to call it -
        // #findBy() calls #setUp() and the instances will be completely OK.
        $call_load = false;
        $reflect = new ReflectionClass(get_class($this));
        
        if(strtolower($reflect->getMethod('load')->getDeclaringClass()->getName()) != 'activetable')
        {
            $call_load = true;
        }

        $SET = array();
        $class_name = get_class($this);
        while($resource->fetchInto($row))
        {
            $RESULT_DATA = $this->parse_columns($row,$columns['lookup_mapping'],$columns['virtual_mapping']);
            
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
            
            if($count == true)
            {
                return array_shift($RESULT_DATA['virtual_fields']);
            }
            else
            {
                // The eval() is slow, so only do it if we need the additional args fragment.
                if($arg_fragment != null)
                {
                    eval('$tmp = new '.$class_name.'($this->db'.$arg_fragment.');');
                }
                else
                {
                    $tmp = new $class_name($this->db);
                }
                
                $tmp->setUp($RESULT_DATA['primary_table'],$RESULT_DATA['lookup_tables'],$RESULT_DATA['virtual_fields']);

                if($call_load == true)
                {
                    $tmp->load($RESULT_DATA['primary_table'][$this->primary_key]);
                } // end call load
                
                $SET[] = $tmp;
            } // end not count
        } // end loop
       
        $total = round((microtime(true) - $start),4);
        $this->debug("#findBy() executed in '$total' seconds.",'method_time'); 
        
        return $SET;
    } // end findBy

    /**
     * An alias for #findBy() that returns either an object or null.
     *
     * This is an alias for #findBy() that returns the first result or,
     * if no results are found, null.
     *
     * Similarly to #findBy(), #findOneByColumnName($value,'ORDER BY column_foo ASC') is
     * also supported.
     *
     * For further information on the parameters, see #findBy()'s documentation.
     * 
     * @param array $ARGS See #findBy()'s first parameter for details.
     * @param string $order_by An ORDER BY SQL fragment.
     * @return object 
     */
    public function findOneBy($ARGS,$order_by='')
    {
        $sql_generator = $this->newSqlGenerator();
        $limit = $sql_generator->buildOneOffLimit(sizeof($ARGS),1);
        
        $result = $this->findBy($ARGS,"$order_by $limit");
        $result = array_shift($result);

        return $result;
    } // end findOneBy
    
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
        $start = microtime(true);
        
        $columns = $this->make_columns($sql_generator);
        $sql_generator = $columns['sql_generator'];
        
        $sql_generator->addFrom($this->table_name,$this->database);
        if($sql_generator->getMagicPkName() != null)
        {
            $sql_generator->addMagicPkToKeys($this->table_name);
        }
        
        // A filter on a join might make this more than just the PK... 
        $join = $this->make_join($this->LOOKUPS,$sql_generator);
        $sql_generator = $join['sql_generator'];
        $SEARCH_VALUES = $join['search_values'];
        $sql_generator->addWhere($this->table_name,$this->primary_key);
        $SEARCH_VALUES[] = $pk_id;
        
        $sql_generator->addLimit(1);
        $sql = $sql_generator->getQuery('select');

        $handle = $this->db->prepare($sql);
        $resource = $this->execute($handle,$SEARCH_VALUES);
        $this->debug($this->db->last_query,'sql');
        
        $this->db->freePrepared($handle);
        if(PEAR::isError($resource))
        {
            throw new SQLError($resource->getDebugInfo(),$resource->userinfo,904);
        }
        $resource->fetchInto($ROW);

        if(is_array($ROW) == true)
        {
            $RESULT_DATA = $this->parse_columns($ROW,$columns['lookup_mapping'],$columns['virtual_mapping']);

            $total = round((microtime(true) - $start),4);
            $this->debug("#load() executed in '$total' seconds.",'method_time'); 

            return $this->setUp($RESULT_DATA['primary_table'],$RESULT_DATA['lookup_tables'],$RESULT_DATA['virtual_fields']);
        } // end got result array

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
        $start = microtime(true);
        
        if($id == null)
        {
            $id = $this->DATA[$this->primary_key];
        }
        
        if($id == 0)
        {
            throw new ArgumentError('No ID specified.',905);
        }

        $sql = "DELETE FROM {$this->table_name} WHERE {$this->primary_key} = ?";
        
        $handle = $this->db->prepare($sql);
        $resource = $this->execute($handle,array($id));
        $this->debug($this->db->last_query,'sql');
        $this->db->freePrepared($handle);
        if(PEAR::isError($resource))
        {
            throw new SQLError($resource->getDebugInfo(),$resource->userinfo,906);
        }
        
        // Load blanks back in to reset the object.
        $this->DATA = $this->load_fields(); 
        $this->record_state = 'new';

        $total = round((microtime(true) - $start),4);
        $this->debug("#destroy() executed in '$total' seconds.",'method_time');

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
        $start = microtime(true);
        
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
            $this->set($value,$key);
            // eval("\$this->set$key(\$value);");
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

        $total = round((microtime(true) - $start),4);
        $this->debug("#create() executed in '$total' seconds.",'method_time');
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
        $start = microtime(true);

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

        if($this->allow_pk_write == false)
        {
            unset($DATA[$this->primary_key]);
        }

        // Do not attempt to set the magic PK field to anything...it's 'virtual'-ish.
        if($this->newSqlGenerator()->getMagicPkName() != null)
        {
            unset($DATA[strtolower($this->newSqlGenerator()->getMagicPkName())]);
        } // end has magic PK
        
        // Oracle users need db.table.
        $table_name = $this->table_name;
        if($this->database != '')
        {
            $table_name = "{$this->database}.{$this->table_name}";
        }
        
        if($this->record_state == 'new')
        {
            $resource = $this->db->autoExecute($table_name,$DATA,DB_AUTOQUERY_INSERT);
            $this->debug($this->db->last_query,'sql');
            if(PEAR::isError($resource))
            {
                throw new SQLError($resource->getDebugInfo(),$resource->userinfo,907);
            }

            $id = $this->db->getOne($this->newSqlGenerator()->getLastInsertId($table_name));
            
            if(PEAR::isError($id))
            {
                throw new SQLError($id->getDebugInfo(),$id->userinfo,908);
            }
        } // end new
        elseif($this->record_state == 'loaded')
        {
            // If we're doing magic...
            if($this->newSqlGenerator()->getMagicPkName() == $this->primary_key)
            {
                $where_fragment = $this->newSqlGenerator()->getMagicUpdateWhere($this->table_name,$this->DATA[$this->primary_key],$this->db);
            }
            
            // Fall back to something reasonable:
            if($where_fragment == null)
            {
                $where_fragment = "{$this->primary_key} = ".$this->db->quoteSmart($this->DATA[strtolower($this->primary_key)]);
            }
            
            $resource = $this->db->autoExecute($table_name,$DATA,DB_AUTOQUERY_UPDATE,$where_fragment);

            if(PEAR::isError($resource))
            {
                throw new SQLError($resource->getDebugInfo(),$resource->userinfo,909);
            }
            $this->debug($this->db->last_query,'sql');

            $id = $this->DATA[$this->primary_key];

            // Save back to the writable lookup tables.
            foreach($this->LOOKUPS as $LOOKUP)
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

        $total = round((microtime(true) - $start),4);
        $this->debug("#save() executed in '$total' seconds.",'method_time');
    } // end save

    /* ====================================================================================== */
    /* =============== Internal methods such as helpers, debugging stuff, etc. ============== */
    /* ====================================================================================== */

    protected function execute($handle,$args=array())
    {
        $start = microtime(true);
        
        $result = $this->db->execute($handle,$args);
        
        $total = round((microtime(true) - $start),4);
        $this->debug("SQL command executed in '$total' seconds.",'sql_time');

        return $result;
    } // end execute

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
        if($this->debug == true && $this->logfile_path != null)
        {
            $log = Log::singleton('file',$this->logfile_path,get_class($this),array('timeFormat' => '%Y-%m-%d %H:%M:%S'));
            
            switch(strtolower($type))
            {
                case 'sql':
                {
                    $log->log($message,PEAR_LOG_DEBUG);

                    break;
                } // end SQL
                
                default:
                {
                    $log->log($message,PEAR_LOG_INFO);
                    
                    break;
                } // end default
            } // end switch
        } // end debug to logfile
        elseif($debug == true)
        {
            // Fallback to something else...
            print_r($message);
        } // end catchall
        
        return true;
    } // end debug

    /* ====================================================================================== */
    /* ================== Informational doodads for inspecting the objects. ================= */
    /* ====================================================================================== */

    /**
     * Get the primary table name.
     * 
     * @return string 
     */
    public function tableName()
    {
        return $this->table_name;
    } // end tableName

    /**
     * Get the primary table's key. 
     * 
     * @return string
     */
    public function primaryKey()
    {
        return $this->primary_key;
    } // end primaryKey

    /**
     * database 
     * 
     * @return void
     */
    public function database()
    {
        return $this->database;
    } // end database

    /* ====================================================================================== */
    /* ===== Implementation details. These are irrelevant. Do not read below this line. ===== */
    /* ====================================================================================== */

    /**
     * Setup method for loading data into an instance.
     *
     * Do not call this directly. Ever. I will break your goddamn fingers
     * if I ever catch you calling this. It should not be public, but it has
     * to be for findBy to be efficient (without something like this,
     * it would have to call #load() and run another query for every result,
     * which is slow).
     *
     * I'm serious. If you call this, I will appear out of thin air with a 
     * small hammer and turn your knuckles to a fine pulp. Don't make me do it.
     * 
     * @internal
     * @access private
     * @param array $PRIMARY_DATA 
     * @param array $LOOKUP_DATA 
     * @param array $VIRTUAL_DATA 
     * @return bool True
     */
    public function setUp($PRIMARY_DATA,$LOOKUP_DATA,$VIRTUAL_DATA)
    {
        $this->DATA = $PRIMARY_DATA;
        $this->LOOKUPS_DATA = $LOOKUP_DATA;
        $this->VIRTUAL_DATA = $VIRTUAL_DATA;

        $this->record_state = 'loaded';

        return true;
    } // end setUp

    protected function newSqlGenerator()
    {
        return new $this->sql_generator();
    } // end newSqlGenerator

    /**
     * Grab a list of fields in the table.
     *
     * @param string The table name. This defaults to the current table.
     * @internal
     */
    protected function load_fields($table=null,$database=null)
    {
        // OWNER name.
        if($database == null)
        {
            $database = $this->database;
        }
        
        // DB name from DSN.
        $database_schema_name = $this->db->dsn['database'];
        if($table == null)
        {
            $table = $this->table_name;
        }

        $CACHE = $this->cacher->loadTable($table,$database_schema_name);

        if(is_array($CACHE) == true)
        {
            $this->debug("Cached structure found for $table.",'cache');
            $RESULT = $CACHE;
        }
        else
        {
            $this->debug("Cached structure not found for $table; describing...",'cache');

            $sql_generator = $this->newSqlGenerator(); 
            $sql = $sql_generator->getDescribeTable($table,$database);

            $resource = $this->db->query($sql);
            $this->debug($sql,'sql');

            if(PEAR::isError($resource))
            {
                throw new SQLError($resource->getDebugInfo(),$resource->userinfo,905);
            }
            
            $RESULT = array();
            
            // If this is the primary table and the DB supports magic PKs, include it in the describe.
            // Should be in position zero (cx_0).
            if($sql_generator->getMagicPkName() != null && $table == $this->table_name)
            {
                $RESULT[strtolower($sql_generator->getMagicPkName())] = null;
            } // end add rowid
            
            while($resource->fetchInto($ROW))
            {
                // Normalize into all lower case (I'm lookin at you, Oracle...)
                foreach($ROW as $key => $value)
                {
                    $ROW[strtolower($key)] = $value;
                }
                
                $RESULT[strtolower($ROW['field'])] = null;
            } // end loop

            $this->cacher->addTable($table,$RESULT,$database_schema_name);
        } // end not cached

        return $RESULT;
    } // end load_fields

    /**
     * load_recordset_id_list 
     * 
     * @param array $RELATED Record set specification.
     * @param string $order_by A raw ORDER BY clause.
     * @return void
     **/
    protected function load_recordset_id_list($RELATED,$order_by=null,$slice_start=null,$slice_end=null)
    {
        if(($slice_start === null && $slice_end === null) == false &&
            ($slice_start !== null && $slice_end !== null) == false
        )
        {
            throw new ArgumentError('Must specify either no slice arguments or both slice arguments.');
        } // end problem w/ args.

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

        $sql_generator = $this->newSqlGenerator();
        $sql_generator->addKeys($RELATED['foreign_table'],array($RELATED['foreign_primary_key']));
        $sql_generator->addFrom($RELATED['foreign_table'],$RELATED['foreign_database']);
        $sql_generator->addJoinClause($RELATED['foreign_table'],$RELATED['foreign_key'],$RELATED['local_table'],$RELATED['local_table'],$RELATED['local_key'],'inner',$RELATED['foreign_database']);
        $sql_generator->addWhere($RELATED['local_table'],$RELATED['local_key']);
        
        if($order_by != null)
        {
            $sql_generator->addOrder($order_by);
        }

        if($slice_start !== null && $slice_end !== null)
        {
            $sql_generator->setSlice($slice_start,$slice_end);
        }
        
        $sql = $sql_generator->getQuery('select');

        $resource = $this->db->query($sql,array($this->get($RELATED['local_key'],$RELATED['local_table'])));
        $this->debug($this->db->last_query,'sql');
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
    protected function load_recordset($record_set,$storage_name,$order_by=null)
    {
        if(array_key_exists($record_set,$this->RELATED) == false)
        {
            throw new ArgumentError("Invalid record set '$record_set' specified.",915);
        }

        // Alias.
        $SET = $this->RELATED[$record_set];
        $IDS = $this->RELATED_IDS[$storage_name];
        $this->RELATED_OBJECTS[$storage_name] = array();

        // This should never happen...the first exception would be thrown instead...but just in case something
        // happens to the instance's state...
        if(is_array($IDS) == false)
        {
            throw new ArgumentError("Record ID array for $record_set was not created. Please report this error.",25);
        }

        // No IDs to look for - don't even bother querying the DB.
        if(sizeof($IDS) == 0)
        {
            $this->RELATED_OBJECTS[$storage_name] = array();
        }
        else
        {
            $method = 'findBy';
            if($SET['one'] == true)
            {
                $method = 'findOneBy';
            }

            $tmp = new $SET['class']($this->db);
            $this->RELATED_OBJECTS[$storage_name] = $tmp->$method(array(
                array(
                    'table' => $tmp->tableName(),
                    'column' => $tmp->primaryKey(),
                    'value' => $IDS,
                ),
            ),$order_by);
        }

        return true;
    } // end load_recordset

    /**
     * Convert the pretty setSomeAttribute to some_attribute for use elsewhere. 
     *
     * @internal
     */
    protected function convert_camel_case($studly_word)
    {
        $simple_word = preg_replace('/([a-z0-9])([A-Z])/','\1_\2',$studly_word);
        $simple_word = strtolower($simple_word);

        return $simple_word;
    } // end convert_camel_case
    
    /**
     * make_join 
     * 
     * @param array $LOOKUPS 
     * @param mixed $sql_generator 
     * @return array
     */
    protected function make_join($LOOKUPS,$sql_generator)
    {
        if(is_array($LOOKUPS) == true)
        {
            $FILTER_VALUES = array();
            foreach($LOOKUPS as $LOOKUP)
            {
                $sql_generator->addJoinClause($LOOKUP['local_table'],$LOOKUP['local_key'],$LOOKUP['foreign_table'],$LOOKUP['foreign_table_alias'],$LOOKUP['foreign_key'],$LOOKUP['join_type'],$LOOKUP['database']);

                if(array_key_exists('filter',$LOOKUP) == true)
                {
                    foreach($LOOKUP['filter'] as $column => $value)
                    {
                        $foo = $this->make_wheres($column,$value,$sql_generator);
                        $sql_generator = $foo['sql_generator'];

                        if($foo['search_values'] !== null)
                        {
                            if(is_array($foo['search_values']) == true)
                            {
                                $FILTER_VALUES = array_merge($FILTER_VALUES,$foo['search_values']);
                            }
                            else
                            {
                                $FILTER_VALUES[] = $foo['search_values']; 
                            }
                        }
                    } // end filter loop
                } // end filter?
            }
        } // end is array == true

        return array(
            'sql_generator' => $sql_generator,
            'search_values' => $FILTER_VALUES,
        );
    } // end make_join

    /**
     * make_wheres 
     * 
     * @param string $column 
     * @param string  $value 
     * @param object $sql_generator 
     * @return void
     */
    protected function make_wheres($column,$value,$sql_generator)
    {
        // You can pass either an array (if you want othertable.$column,column = value) or 'column' => 'value'.
        if(is_array($value) == true)
        {
            // no table specified...? default it!
            if(array_key_exists('table',$value) == false)
            {
                $value['table'] = $this->table_name;
            }

            if(array_key_exists('like',$value) == false)
            {
                $value['like'] = false;
            }
            elseif(in_array($value['like'],array(true,false),true) == false)
            {
                throw new ArgumentError('Like may only be true, false, or omitted.',959);
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
            elseif($value['like'] == true && in_array($value['search_type'],array('<>','=')) == false)
            {
                throw new ArgumentError('Valid search_types when like = true are = and <>.',560);
            }

            if($value['value'] === null && in_array($value['search_type'],array('<>','=')) == false)
            {
                throw new ArgumentError('Invalid search type given for IS. Valid values are = and <>.',957);
            } // end check is not

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
                
                $sql_generator->addWhere($value['table'],$value['column'],$in_type,sizeof($value['value']));

                $search_value = array();
                foreach($value['value'] as $in_val)
                {
                    $search_value[] = $in_val;
                } // end loop
            } // end in
            else
            {
                $type = '';
                if($value['like'] === true)
                {
                    // Like + search_type is already validated.
                    if($value['search_type'] == '=')
                    {
                        $type = 'like';
                    }
                    elseif($value['search_type'] == '<>')
                    {
                        $type = 'not_like';
                    }
                } // end like
                elseif($value['value'] === null) // The === ensures 0 won't be null.
                {
                    // Is + search_type is already validated.
                    if($value['search_type'] == '=')
                    {
                        $type = 'is';
                    }
                    elseif($value['search_type'] == '<>')
                    {
                        $type = 'is_not';
                    }
                } // end value is null
                
                if($type == null)
                {
                    $search_type = $value['search_type'];
                }
                else
                {
                    $search_type = $type;
                }
                
                $sql_generator->addWhere($value['table'],$value['column'],$search_type);

                // Is/is not NULLs should not have anything appended to
                // the search value list - they are not added as ? placeholders,
                // and a DB Mismatch will occur.
                if(in_array($search_type,array('is','is_not')) == false)
                {
                    $search_value = $value['value'];
                }
            } // end =
        } // end is_array
        else
        {
            $sql_generator->addWhere($this->table_name,$column);

            if($value === null)
            {
                $search_value = '0';
            }
            else
            {
                $search_value = $value;
            }
        } // end string

        return array(
            'sql_generator' => $sql_generator,
            'search_values' => $search_value,
        );
    } // end make_wheres

    /**
     * Add columns to a SQL query in the appropriate manner.
     * 
     * Column names follow tablename__column_name to avoid collisions. 
     *                              ^ Note that there are TWO underscores.
     * However, in the SQL, they follow x_y - these aliases are used because
     * certain RDBMS have limits on the length of column aliases - but x_y
     * resolves to tablename__column_name.
     *
     * 1. Build column names for the base table & the base SQL statement.
     * 2. If there are any lookups, build their key names and JOIN clause.
     * 
     * @param mixed $sql_generator 
     * @return void
     */
    protected function make_columns($sql_generator)
    {
        $keys = array_keys($this->DATA);
        $fkeys = array();

        $sql_generator = $this->newSqlGenerator();
        $sql_generator->addKeys($this->table_name,$keys);
        
        // There are lookup tables - DO IT!
        if(sizeof($this->LOOKUPS) > 0)
        {
            foreach($this->LOOKUPS as $table_index_number => $LOOKUP)
            {
                $fkeys[$table_index_number] = array_keys($this->load_fields($LOOKUP['foreign_table']));
                $sql_generator->addKeys($LOOKUP['foreign_table_alias'],$fkeys[$table_index_number],$table_index_number);
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
                $sql_generator->addVirtualKey($function_fragment,$i);

                $i++;
            } // end virtual loop
        } // end virtual > 0
        
        return array(
            'sql_generator' => $sql_generator,
            'lookup_mapping' => $fkeys,
            'virtual_mapping' => $virt_map,
        );
    } // end make_column 

    /**
     * parse_columns 
     * 
     * @param mixed $DATA 
     * @param mixed $LOOKUP_MAP 
     * @param mixed $VIRTUAL_MAP 
     * @return void
     */
    protected function parse_columns($DATA,$LOOKUP_MAP,$VIRTUAL_MAP)
    {
        $PARSED = array(
            'primary_table' => array(),
            'lookup_tables' => array(),
            'virtual_fields' => array(),
        );

        // Do this once up here - we need to map the index number to field name.
        $DATA_COLUMNS = array_keys($this->DATA);

        foreach($DATA as $key => $value)
        {
            $key = strtolower($key);
            $key = substr($key,1); // Strip off the leading 'c' - used to prevent oracle from crying.
            
            $table_id = substr($key,0,strpos($key,'_'));
            $column_id = substr($key,strpos($key,'_')+1);

            // Translate this shit back in to something human-readable.
            // Thank Oracle's 30-character limit on aliases for this x_0
            // crap.
            if($table_id == 'x') // X is for primary.
            {
                $table = $this->table_name;
                $column = $DATA_COLUMNS[$column_id];
            }
            elseif($table_id == 'virt')
            {
                $table = 'VIRT';
            }
            else
            {
                $table = $this->LOOKUPS[$table_id]['foreign_table_alias'];
                $column = $LOOKUP_MAP[$table_id][$column_id];
            } // end table name resolver
           
            if($table == $this->table_name)
            {
                $PARSED['primary_table'][$column] = $value;
            }
            elseif($table == 'VIRT')
            {
                $PARSED['virtual_fields'][$VIRTUAL_MAP[$column_id]] = $value;
            }
            else
            {
                $PARSED['lookup_tables'][$table][$column] = $value;
            }
        } // end row loop       

        return $PARSED;
    } // end parse_columns
} // end ActiveTable

?>
