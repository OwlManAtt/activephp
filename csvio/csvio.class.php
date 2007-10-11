<?php
/**
 * An ActiveTable-based library for loading/writing CSVs to/from database tables. 
 *
 * @package    ActivePHP 
 * @author     OwlManAtt <owlmanatt@gmail.com> 
 * @copyright  2007, Yasashii Syndicate 
 * @version    2.2.7
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
            throw ArgumentError("Invalid quote character ({$this->quote}). The quote character may only be one character long or null.");
        }

        if(is_array($this->FIELDS) == false)
        {
            throw ArgumentError('The field mapping list must be an array.');
        }
        elseif(sizeof($this->FIELDS) <= 0)
        {
            throw ArgumentError('The field mapping list cannot be empty.');
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
    
    public function writeSearchResults($search,$order,$output_file)
    {
        // TODO - header record
        
        // Open a file pointer.
        $fp = fopen($output_file,'w');
        
        // Get the list of rows.
        $results = $this->findBy($search,$order);

        // Go through and get the data for the fields we want in this CSV.
        // Write it out as we go along.
        foreach($results as $result)
        {
            $RECORD = array();
            
            foreach($this->FIELDS as $field)
            {
                eval('$RECORD[$field] = $result->'.ucfirst($field).';');
            }
            
            fputcsv($fp,$RECORD,$this->seperator,$this->quote);
        } // end result loop

        fclose($fp);
    
        return null;    
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
     
        throw ArgumentError('Method not implemented at this time.');
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
                            $date = $this->sql_generator->getFormattedDate($value);
                            $date = explode(' ',$date);
                            
                            $value = $date[0]; 

                            break;
                        } // end date
                        
                        case 'datetime':
                        {
                            $value = $this->sql_generator->getFormattedDate($value);

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
