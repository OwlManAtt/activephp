<?php
/**
 * An implementation of the ActiveRecord pattern at its most basic. 
 *
 * {@tutorial aphp/README}
 *
 * @package    ActiveTable 
 * @author     OwlManAtt <owlmanatt@gmail.com> 
 * @copyright  2007, Yasashii Syndicate 
 * @version    1.6.0
 */
require_once('SqlGenerators/interface.inc.php');
require_once('SqlGenerators/mysql.class.php');
require_once('SqlGenerators/oci.class.php');

/**
 * The base class that implements all of the magic for your child classes.
 * This class itself should never be instantiated; extend this object and
 * set the table_name / primary_key attributes to get it working.
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
     * @var string
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
     *    join_type => <em>inner</em>|<em>left</em>
     *    write => <em>false</em>|<em>true</em>
     *    filter => array('table' => 'table','column' => 'column', 'value' => 'value'|array('value1','value2'))
     * )
     *
     * @var array
     */
    protected $LOOKUPS = array();

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
        
    } // end __construct

    /**
     * Magic method that any calls to undefined methods get routed to.
     * This provides the get* and set* methods.
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
                // Try here first:
                if(array_key_exists($property_name,$this->DATA) == true)
                {
                    return $this->DATA[$property_name];
                }
                else
                {
                    // Find the first occurance of this key in the lookups:
                    foreach($this->LOOKUPS_DATA as $LOOKUP)
                    {
                        if(array_key_exists($property_name,$LOOKUP) == true)
                        {
                            return $LOOKUP[$property_name];
                        }
                    } // end lookups loop
                } // end do lookups

            } // end get
            elseif($FOUND[1] == 's')
            {
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
                    
                    throw new ArgumentError('That attribute is not in the domain of the '.get_class($this)." table. Note that lookup'd values are read-only.",901);
                }
            } // end set
        } // end the regexp matched...
        elseif(preg_match('/^findBy([A-Z][A-Za-z0-9_]*)$/',$method,$FOUND) == true)
        {
            $property_name = $this->convert_camel_case($FOUND[1]);
            $property_name = strtolower($property_name);
            
            // $property_name;
            $value = $parameters[0];
            
            $sql = "SELECT `{$this->primary_key}` AS `table_id` FROM `{$this->table_name}` WHERE `$property_name` = ?"; 
            $handle = $this->db->prepare($sql);
            $resource = $this->db->execute($handle,array($value));
            $this->db->freePrepared($handle);

            if(PEAR::isError($resource))
            {
                throw new SQLError($resource->getDebugInfo(),$resource->userinfo,906);
            }

            $ROWS = array();
            while($resource->fetchInto($row))
            {
                // __CLASS__ is ActiveTable; RONG
                $code = '$obj = new '.get_class($this).'($this->db);';
                eval($code);
                $obj->load($row['table_id']);
                $ROWS[] = $obj;    
            } // end loop
        
            return $ROWS;
        } // end finder

        return false;
    } // end __call

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
     *      'value' => array('foo','bar','baz') // company.type IN ('foo','bar','baz') 
     *  );
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
        elseif(sizeof($args) <= 0)
        {
            throw new ArgumentError('Args cannot be an empty array.',951);
        }
        
        $AND = array();
            
        // Clear things out.
        $this->sql_generator->reset();
        $this->sql_generator->addKeys($this->table_name,array($this->primary_key));
        $this->sql_generator->addFrom($this->table_name,$this->database);
        $this->sql_generator->addJoinClause($this->LOOKUPS);

        $SEARCH_VALUES = array();
        foreach($args as $column => $value)
        {
            // You can pass either an array (if you want othertable.column = value) or 'column' => 'value'.
            if(is_array($value) == true)
            {
                // No table specified...? Default it!
                if(array_key_exists('table',$value) == false)
                {
                    $value['table'] = $this->table_name;
                }

                if(array_key_exists('column',$value) == false || array_key_exists('value',$value) == false)
                {
                    throw new ArgumentError('Column or value not given.',951);
                }

                if(is_array($value['value']) == true)
                {
                    $this->sql_generator->addWhere($value['table'],$value['column'],'in',sizeof($value['value']));
                    foreach($value['value'] as $in_val)
                    {
                        $SEARCH_VALUES[] = $in_val;
                    }
                } // end IN
                else
                {
                    $this->sql_generator->addWhere($value['table'],$value['column']);
                    $SEARCH_VALUES[] = $value['value'];
                } // end =
            }
            else
            {
                $this->sql_generator->addWhere($this->table_name,$column);
                $SEARCH_VALUES[] = $value;
            }
        } // end loop

        // TODO - This is taken exactly as-is and put into the query. Probably a *bad* idea...
        if($order_by != null)
        {
            $this->sql_generator->addOrder($order_by);
        }
        
        $sql = $this->sql_generator->getQuery('select');
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
            eval('$tmp = new '.get_class($this).'($this->db);');
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
                $this->sql_generator->addKeys($LOOKUP['foreign_table'],$fkeys[$table_index_number],$table_index_number);
            } // end loopup loop
        } // end lookups > 0
        
        $this->sql_generator->addFrom($this->table_name,$this->database);
        $this->sql_generator->addJoinClause($this->LOOKUPS);
        $this->sql_generator->addWhere($this->table_name,$this->primary_key);
        $this->sql_generator->addLimit(1);
        $sql = $this->sql_generator->getQuery('select');

        $handle = $this->db->prepare($sql);
        $resource = $this->db->execute($handle,array($pk_id));
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
                else
                {
                    $table = $this->LOOKUPS[$table_id]['foreign_table'];
                    $column = $fkeys[$table_id][$column_id];
                } // end table name resolver

                
                if($table == $this->table_name)
                {
                    $this->DATA[$column] = $value;
                }
                else
                {
                    $this->LOOKUPS_DATA[$table][$column] = $value;
                }
            } // end row loop

            $this->record_state = 'loaded';
        } // end got result array

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

    /**
     * Generate the MITER JOIN ON w.x = y.z clauses.
     *
     * @internal
     */

    /*
    private function __generate_joins()
    {
        $JOINS_FINAL = array();
        
        // There are lookup tables - DO IT!
        if(sizeof($this->LOOKUPS) > 0)
        {
            foreach($this->LOOKUPS as $LOOKUP)
            {
                $join = '';
                switch(strtolower($LOOKUP['join_type']))
                {
                    default:
                    {
                        throw new ArgumentError("Unknown join type '{$LOOKUP['join_type']}' specified for '{$LOOKUP['foreign_table']}' lookup.",903);

                        break;
                    }
                    
                    case 'left':
                    {
                        $join = 'LEFT JOIN';
                        
                        break;
                    } // end left

                    case 'inner':
                    {
                        $join = 'INNER JOIN';

                        break;
                    } // end inner
                } // end join switch

                if(array_key_exists('local_table',$LOOKUP) == false)
                {
                    $LOOKUP['local_table'] = $this->table_name;
                }

                $JOINS_FINAL[] = "$join `{$LOOKUP['foreign_table']}` ON `{$LOOKUP['local_table']}`.`{$LOOKUP['local_key']}` = `{$LOOKUP['foreign_table']}`.`{$LOOKUP['foreign_key']}`";
            } // end loopup loop
        } // end lookups > 0

        return implode("\n",$JOINS_FINAL);
    } // end __generate_joins
    */

} // end ActiveTable


?>
