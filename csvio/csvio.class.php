<?php
/**
 * An ActiveTable-based library for loading/writing CSVs to/from database tables. 
 *
 * @package    ActivePHP 
 * @author     OwlManAtt <owlmanatt@gmail.com> 
 * @copyright  2007, Yasashii Syndicate 
 * @version    2.2.0
 */

/**
 * The base class for CSVIO-enabled ActiveTable classes.
 *
 * This class should never be instantiated; extend the object
 * and set the required attributes to get it working. 
 *
 * @package    ActivePHP 
 * @author     OwlManAtt <owlmanatt@gmail.com> 
 * @copyright  2007, Yasashii Syndicate
 * @version    Release: @package_version@
 **/
class CSVIO extends ActiveTable
{
    /**
     * Empty the table before loading anything in.
     *
     * @var boolean
     **/
    protected $truncate = false;
    
    /**
     * The field mapping for a CSV's row. 
     *
     * This represents the field mapping for a CSV row.
     * The mapping should be an indexed array, starting from 0,
     * where each value is the table's column name that it maps to.
     * The mappings should in *in the order they appear* in the CSV
     * file.
     *
     * Also note that if you do not want a field imported, leave it's column
     * name null.
     *
     * <code>
     * protected $FIELDS = array(
     *     0 => 'acct',
     *     1 => 'foo',
     *     2 => null,
     *     3 => 'other_db_column',
     * );
     * </code>
     *
     * CSVIO also allows for 'formatters' to be applied to data. You can instruct 
     * CSVIO to format an input before adding it to the database by doing the following:
     *
     * <code>
     * protected $FIELDS = array(
     *     0 => 'acct',
     *     1 => 'foo',
     *     2 => array(
     *           'column' => 'db_column',
     *           'format' => 'date|datetime',
     *     ),
     *     3 => 'other_db_column',
     * );
     * </code>
     *
     * At this time, only 'date' and 'datetime' are supported. These will take any date that
     * can be read by strtotime() and turn it into the appropriate date/datetime format for the
     * RDBMS you are using.
     * 
     * Custom formatters using a callback function will be supported in a future release.
     *
     * @var array
     **/
    protected $FIELDS = array();

    /**
     * A list of records to be outputted in the header. 
     *
     * If this is left empty, no header row will be written.
     *
     * @var array
     **/
    protected $HEADERS = array();

    /**
     * The normalized form of $FIELDS. Do not define this; it is auto-generated.
     *
     * @var array
     * @internal
     **/
    protected $FIELDS_NORMALIZED = array();

    /**
     * The field seperator.
     *
     * @var string
     **/
    protected $seperator = ',';

    /**
     * The 'quote' character. This may be null OR one single character.
     *
     * @var string
     **/
    protected $quote = '"';

    /**
     * This indicates whether or not a header row is present in the CSV.
     *
     * Row 0 of the CSV will be ignored if this is true.
     *
     * @var bool
     **/
    protected $header_row = true;

    /**
     * Set up the CSVIO instance. 
     *
     * @param object See ActiveTable documentation.
     * @return void
     **/
    public function __construct($db)
    {
        parent::__construct($db);

        if(strlen($this->quote) > 1)
        {
            throw new ArgumentError("Invalid quote character ({$this->quote}). The quote character may only be one character long or null.");
        }

        if(is_array($this->FIELDS) == false)
        {
            throw new ArgumentError('The field mapping list must be an array.');
        }
        elseif(sizeof($this->FIELDS) <= 0)
        {
            throw new ArgumentError('The field mapping list cannot be empty.');
        }
        else
        {
            // Validate & normalize field list.
            $this->FIELDS_NORMALIZED = $this->FIELDS;

            // For our purposes, the field list need only be 0 => foo; the 0 => array(...) needs to be normalized.
            foreach($this->FIELDS_NORMALIZED as $index => $field)
            {
                if(is_array($field) == true)
                {
                    if(array_key_exists('column',$field) == false)
                    {
                        throw new ArgumentError("Error in field $index - array given as value with no column defined.");
                    }
                    else
                    {
                        $this->FIELDS_NORMALIZED[$index] = $field['column'];
                    }

                    if(array_key_exists('format',$field) == true)
                    {
                        $FORMATS = array('date','datetime');
                        
                        if(in_array($field['format'],$FORMATS) == false)
                        {
                            throw new ArgumentError("Invalid format '{$field['format']}' specified for column $index ('{$field['column']}').");
                        }
                    } // end format is set
                } // end if field is array
            } // end field normalizer
        } // end field size looks OK; check in detail 
    } // end __construct

    /**
     * Load a CSV file's contents into CSVIO.
     *
     * @param string    The path to the file.
     **/
    public function loadCSVFile($path)
    {
        if($this->truncate == true)
        {
            $this->db->query("DELETE FROM `{$this->table_name}`");
        }
        
        $handle = fopen($path,'r');
        
        $imported = 0;
        $i = 0;
        while($data = fgetcsv($handle,0,$this->seperator,$this->quote))
        {
            $i++;
            if($i == 1 && $this->header_row == true)
            {
                continue;
            }
            
            $CREATE = $this->map_fields($data);
            $CREATE = $this->handle_formatters($CREATE);
            eval('$tmp = new '.get_class($this).'($this->db);');

            if($this->debug == true)
            {
                $tmp->debug = true;
            }

            $tmp->create($CREATE);
            $imported++;
        } // end line loop

        return $imported;
    } // end loadFile
    
    public function writeSearchResults($args,$order,$output_file)
    {
        $start = microtime(true);
        $SEARCH_VALUES = array();

        if(is_array($args) == false)
        {
            throw new ArgumentError('Args must be an array.',950);
        }

        $sql_generator = $this->newSqlGenerator();
        $columns = $this->make_columns($sql_generator);
        $sql_generator = $columns['sql_generator'];

        if($sql_generator->getMagicPkName() != null)
        {
            $sql_generator->addMagicPkToKeys($this->table_name);
        }

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
        } // end loop

        // TODO - This is taken exactly as-is and put into the query. Probably a *bad* idea...
        if($order_by != null)
        {
            $sql_generator->addOrder($order_by);
        }
        
        $sql = $sql_generator->getQuery('select');
        $handle = $this->db->prepare($sql);
        $resource = $this->execute($handle,$SEARCH_VALUES);
        $this->debug($this->db->last_query,'sql');

        $this->db->freePrepared($handle);

        if(PEAR::isError($resource))
        {
            throw new SQLError($resource->getDebugInfo(),$resource->userinfo,909);
        }
        
        // Try to open a file pointer.
        $fp = fopen($output_file,'w'); 

        if($fp == false)
        {
            throw new FileHandleError('The specified file could not be opened and written to.');
        }

        // Header row.
        if(sizeof($this->HEADERS) != 0)
        {
            fputcsv($fp,$this->HEADERS,$this->seperator,$this->quote);
        }
        
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
           
            $RECORD = array();
            foreach($this->FIELDS as $field)
            {
                $RECORD[$field] = $tmp->get($field);
            }
            
            fputcsv($fp,$RECORD,$this->seperator,$this->quote);
        } // end while

        fclose($fp);
    
        return true;
    } // end writeSearchResults
    
    /**
     * Load a CSV from a string.
     *
     * @param string    The CSV contents.
     **/
    public function loadCSV($csv)
    {
        if($csv === null)
        {
            throw new FileEmptyError('The CSV file supplied contains no data.');
        } // end csv is not null
     
        throw new ArgumentError('Method not implemented at this time.');
    } // end loadCSV

    /**
     * Process formatters on a column.
     *
     * @param array     An imported row from the CSV.
     * @return array    The array with formatted values.
     **/
    protected function handle_formatters($data)
    {
        $i = 0;
        foreach($data as $key_index => $value)
        {
            $field = $this->FIELDS[$i];
            
            if(is_array($field) == true)
            {
                if(array_key_exists('format',$field) == true)
                {
                    // Note - this has been validated before we got to this point. Do not worry 
                    // about a failure case.
                    switch($field['format'])
                    {
                        case 'date':
                        {
                            $date = $this->newSqlGenerator()->getFormattedDate($value);
                            $date = explode(' ',$date);
                            
                            $value = $date[0]; 

                            break;
                        } // end date
                        
                        case 'datetime':
                        {
                            $value = $this->newSqlGenerator()->getFormattedDate($value);

                            break;
                        } // end datetime

                    } // end format switch

                    $data[$key_index] = $value;
                } // end format
            } // end is array

            $i++;
        } // end data loop

        return $data;
    } // end handle_formatters

    protected function map_fields($data)
    {
        if(sizeof($this->FIELDS_NORMALIZED) > sizeof($data))
        {
            // Pad the data w/ difference
            $data = array_pad($data,sizeof($this->FIELDS_NORMALIZED),null);
        }
        elseif(sizeof($this->FIELDS_NORMALIZED) < sizeof($data))
        {
            $original = sizeof($data);
            
            while($original > sizeof($this->FIELDS_NORMALIZED))
            {
                array_pop($data);
                $original--;
            } // end pop loop
        } // end data too large

        $RETURN = array_combine($this->FIELDS_NORMALIZED,$data);

        return $RETURN;
    } // end map_fields

    
} // end CSVIO
?>
