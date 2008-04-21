<?php
/**
 * Classes for writing PostgreSQL queryes. 
 *
 * @package    ActivePHP 
 * @author     OwlManAtt <owlmanatt@gmail.com> 
 * @copyright  2008, Yasashii Syndicate 
 * @version    2.4.0
 */

/**
 * PostgreSQL SQL driver for ActiveTable.
 *
 * @package    ActivePHP 
 * @author     OwlManAtt <owlmanatt@gmail.com> 
 * @copyright  2008, Yasashii Syndicate
 * @version    Release: @package_version@
 **/
class ActiveTable_SQL_PgSQL implements ActiveTable_SQL
{   
    protected $columns = array();
    protected $from = '';
    protected $join = array();
    protected $where = array();
    protected $order = '';
    protected $order_direction;
    protected $limit = '';
    protected $offset = '';
    public $magic_pk_name = null;

    public function __construct()
    {
        return null;
    } // end __construct

    public function getMagicPkName()
    {
        return null;
    } // end getMagicPkName

    public function getMagicUpdateWhere($table,$value,&$db)
    {
        return null;
    } // end getMagicUpdateWhere

    public function addMagicPkToKeys($table_name)
    {
        return null;
    } // end addMagicPkToKeys

    public function getFormattedDate($datetime)
    {
        if($datetime == null)
        {
            return '0000-00-00 00:00:00';
        }
        
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
                $sql .= "FROM {$this->from}\n";

                if(sizeof($this->join) > 0)
                {
                    $sql .= implode("\n",$this->join)."\n";
                }

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

                if($this->limit != null)
                {
                    $sql .= "LIMIT {$this->limit}\n";
                }

                if($this->offset != null)
                {
                    $sql .= "OFFSET {$this->offset}\n";
                }

                break;
            } // end select
        } // end switch

        return $sql;
    } // end getQuery

    public function addFrom($table,$database=null)
    {
        if($database == null)
        {
            $this->from = "\"{$table}\"";
        }
        else
        {
            $this->from = "\"{$database}\".\"{$table}\"";
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
                $this->where[] = "\"{$table}\".\"{$column}\" {$type} ?";

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
                    // Prevent INs with no IDs from generating invalid SQL.
                    throw new ArgumentError('Attempting to do IN with no data.');
                }
                
                $this->where[] = "\"{$table}\".\"{$column}\" $in ({$placeholders})";
    
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
                
                $this->where[] = "\"{$table}\".\"{$column}\" {$is}";
    
                break;
            } // end is, is_not
            
            case 'like':
            case 'not_like':
            {
                $like = '';
                if($type == 'like')
                {
                    $like = 'ILIKE';
                }
                elseif($type == 'not_like')
                {
                    $like = 'NOT ILIKE';
                }
                
                $this->where[] = "\"{$table}\".\"{$column}\" {$like} ?";

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
        $this->limit = "$limit";
    } // end addLimit
    
    // Must return in the format table__column
    public function addKeys($table,$COLUMNS,$table_id='x')
    {
        foreach($COLUMNS as $id => $key)
        {
            if($key != null)
            {
                $this->columns[] = "\"{$table}\".\"{$key}\" AS c{$table_id}_{$id}";
            }
        } // end column loop

    } // end addKeys

    public function addVirtualKey($statement,$index)
    {
        $this->columns[] = "$statement AS cVIRT_$index";
    } // end addVirtualKey
    
    public function getDescribeTable($table_name,$database=null)
    {
        return "
            SELECT 
                pg_attribute.attname AS field,
                pg_type.typname AS type 
            FROM pg_class 
            INNER JOIN pg_attribute ON pg_class.oid = pg_attribute.attrelid 
            INNER JOIN pg_type ON pg_attribute.atttypid = pg_type.oid
            WHERE pg_class.relname = '{$table_name}'
            AND pg_attribute.attnum > 0
        ";
    } // end getDescribeTable
    
    // public function addJoinClause($LOOKUPS)
    public function addJoinClause($local_table,$local_key,$foreign_table,$foreign_table_alias,$foreign_key,$join_type,$database=null)
    {
        $join = '';
        switch(strtolower($join_type))
        {
            default:
            {
                throw new ArgumentError("Unknown join type '{$join_type}' specified for '{$foreign_table}' lookup.",903);

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

        $this->join[] = "$join \"{$foreign_table}\" \"{$foreign_table_alias}\" ON \"{$local_table}\".\"{$local_key}\" = \"{$foreign_table_alias}\".\"{$foreign_key}\"";
    } // end addJoinClause

    public function getLastInsertId($table)
    {
        return "SELECT lastval() AS last_insert_id";
    } // end getLastInsertId

    public function buildOneOffLimit($condition_number,$limit_number)
    {
        return "LIMIT $limit_number\n";
    } // end buildOneOffLimit
    
    public function setSlice($start,$end)
    {
        if($this->limit != null)
        {
            throw new SQLGenerationError('Limit has been set for this query; cannot return a slice.');
        }
        
        $end++; 
        $total = $end - $start;
        $this->limit = $total;
        $this->offset = $start - 1;
    } // end setSlice

    public function getReservedWordEscapeCharacter()
    {
        return '"';
    } // end getReservedWordEscapeCharacter

} // end ActiveTable_PgSQL_SQL

?>
