<?php
/**
 * Classes for writing PL/SQL queries.
 *
 * @package    ActivePHP 
 * @author     OwlManAtt <owlmanatt@gmail.com> 
 * @copyright  2007, Yasashii Syndicate 
 * @version    2.3.0
 */

/**
 * Oracle PL/SQL driver for ActiveTable.
 *
 * @package    ActivePHP 
 * @author     OwlManAtt <owlmanatt@gmail.com> 
 * @copyright  2007, Yasashii Syndicate
 * @version    Release: @package_version@
 **/
class ActiveTable_SQL_Oracle implements ActiveTable_SQL
{
    protected $columns = array();
    protected $from = array();
    protected $where = array();
    protected $order = '';
    protected $order_direction;
    public $magic_pk_name = 'rowid';
    
    // If get_slice is true, a slice query will be generated. 
    private $get_slice = false;
    private $slice_start = null;
    private $slice_end = null;

    public function __construct()
    {
        return null;
    } // end __construct

    public function getMagicPkName()
    {
        return 'rowid';
    } // end getMagicPkName

    public function addMagicPkToKeys($table_name)
    {
        $this->columns[] = "ROWIDTOCHAR($table_name.rowid) AS cx_0";
    } // end addMagicPkToKeys

    public function getMagicUpdateWhere($table,$value,&$db)
    {
        return "{$this->getMagicPkName()} = CHARTOROWID(".$db->quoteSmart($value).")";
    } // end getMagicUpdateWhere

    public function getFormattedDate($datetime)
    {
        if($datetime == null)
        {
            return '0000-00-00 00:00:00';
        }
        
        // YYYY-MM-DD HH24:MI:SS
        return date('Y-m-d H:i:s',strtotime($datetime));
    } // end getFormattedDate

    public function addOrder($sql_fragment)
    {
        if(is_array($sql_fragment) == false)
        {
            $this->order = $sql_fragment;
        }
        else
        {
            $ORDER = array();
            $COLUMNS = $sql_fragment['columns'];

            foreach($COLUMNS as $COLUMN)
            {
                $ORDER[] = "\"{$COLUMN['table']}\".\"{$COLUMN['column']}\"";
            }

            $this->order = $ORDER;
            $this->order_direction = $sql_fragment['direction'];
        }
    } // end addOrder

    public function getQuery($verb)
    {
        $sql = '';
        
        switch(strtolower($verb))
        {
            case 'select':
            {
                $sql .= "SELECT\n";
                $sql .= implode(",\n",$this->columns)."\n";
                $sql .= "FROM ".implode(", ",$this->from)."\n";
                    
                if(sizeof($this->where) > 0)
                {
                    $sql .= "WHERE ".implode("\nAND ",$this->where)."\n";
                }

                if($this->order != null)
                {
                    if(is_array($this->order) == true)
                    {
                        $sql .= "ORDER BY ".implode(', ',$this->order)." {$this->order_direction}\n";
                    }
                    else
                    {
                        $sql .= $this->order."\n";
                    }
                }

                if($this->get_slice == true)
                {
                    $wrapper = "SELECT * FROM (\n";
                        $wrapper .= "\tSELECT a.*, ROWNUM rnum\n";
                        $wrapper .= "\tFROM (\n";
                            $wrapper .= $sql;
                        $wrapper .= "\t) a\n";
                        $wrapper .= "\tWHERE ROWNUM <= {$this->slice_end}\n";
                    $wrapper .= ")\n";
                    $wrapper .= "WHERE rnum >= {$this->slice_start}\n";

                    $sql = $wrapper;
                } // end add slice wrapper query

                break;
            } // end select
        } // end switch

        return $sql;
    } // end getQuery

    public function addFrom($table,$database=null)
    {
        if($database == null)
        {
            $this->from[] = "$table";
        }
        else
        {
            $this->from[] = "$database.$table";
        }
    } // end addFrom

    public function addWhere($table,$column,$type='=',$count=0)
    {
        switch($type)
        {
            case '>=':
            case '>':
            case '<=':
            case '<':
            case '<>':
            case '=':
            {
                $this->where[] = "$table.$column $type ?";

                break;
            } // end >= > <= < <> =

            case 'not_in':
            case 'in':
            {
                $in = '';
                if($type == 'in')
                {
                    $in = 'IN';
                }
                elseif($type == 'not_in')
                {
                    $in = 'NOT IN';
                }
               
                if($count > 0)
                {
                    $placeholders = implode(',',array_fill(0,$count,'?'));
                }
                else
                {
                    // Prevent INs with 0 records in them from generating invalid SQL.
                    throw new ArgumentError('Attempting to do IN with no data.');
                }

                $this->where[] = "$table.$column $in ($placeholders)";
    
                break;
            } // end in, not_in

            case 'is_not':
            case 'is':
            {
                $is = '';
                if($type == 'is')
                {
                    $is = 'IS NULL';
                }
                elseif($type == 'is_not')
                {
                    $is = 'IS NOT NULL';
                }
                
                $this->where[] = "$table.$column $is";
    
                break;
            } // end is, is_not

            case 'like':
            case 'not_like':
            {
                $like = '';
                if($type == 'like')
                {
                    $like = 'LIKE';
                }
                elseif($type == 'not_like')
                {
                    $like = 'NOT LIKE';
                }
                
                $this->where[] = "$table.$column $like ?";

                break;
            } // end like, not_like

            default:
            {
                throw new ArgumentError('Invalid type given to SQL generator.');
                
                break;
            } // end default

        } // end switch
    } // end addWhere

    public function addLimit($limit)
    {
        $this->where[] = "rownum >= $limit";
    } // end addLimit

    public function addKeys($table,$COLUMNS,$table_id='x')
    {
        foreach($COLUMNS as $id => $key)
        {
            if($key != null)
            {
                if($key != $this->getMagicPkName())
                {
                    $this->columns[] = "{$table}.{$key} AS c{$table_id}_{$id}";
                }
            }
        } // end column loop
        
    } // end addKeys

    public function addVirtualKey($statement,$index)
    {
        $this->columns[] = "$statement AS cVIRT_$index";
    } // end addVirtualKey
    
    public function getDescribeTable($table_name,$database=null)
    {
        $sql = "SELECT COLUMN_NAME AS field FROM ALL_TAB_COLUMNS WHERE UPPER(TABLE_NAME) = UPPER('$table_name')";
        $sql .= " AND UPPER(OWNER) = UPPER('$database')";
        
        return $sql;
    } // end getDescribeTable

    public function addJoinClause($local_table,$local_key,$foreign_table,$foreign_table_alias,$foreign_key,$join_type,$database=null)  
    {
        if($database == null)
        {
            $this->from[] = "$foreign_table $foreign_table_alias";
        }
        else
        {
            $this->from[] = "{$database}.{$foreign_table} {$foreign_table_alias}";
        }
        
        switch(strtolower($join_type))
        {
            default:
            {
                throw new ArgumentError("Unknown join type '{$join_type}' specified for '{$foreign_table}' lookup.",903);

                break;
            }
           
            case 'left':
            {
                $this->where[] = "{$local_table}.{$local_key} = {$foreign_table_alias}.{$foreign_key} (+)";
                
                break;
            } // end left

            case 'inner':
            {
                $this->where[] = "{$local_table}.{$local_key} = {$foreign_table_alias}.{$foreign_key}";

                break;
            } // end inner
        } // end join switch

    } // end addJoinClause

    public function getLastInsertId($table)
    {
        return "SELECT ROWIDTOCHAR(MAX(rowid)) FROM $table";
    } // end getLastInsertId

    public function buildOneOffLimit($condition_number,$limit_number)
    {
        if($condition_number == 0)
        {
            return "WHERE rownum >= $limit_number\n";
        }
        
        return "AND rownum >= $limit_number\n";
    } // end buildOneOffLimit

    public function setSlice($start,$end)
    {
        $this->get_slice = true;
        $this->slice_start = $start;
        $this->slice_end = $end;
    } // end setSlice

    public function getReservedWordEscapeCharacter()
    {
        // TODO. I think ' works, but I need to test that.
        return '';
    } // end getReservedWordEscapeCharacter

} // end ActiveTable_Oracle_SQL

?>
