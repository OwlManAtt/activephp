<?php
/**
 * Classes for writing MySQL 4 and 5 compatible queries.
 *
 * @package    ActivePHP 
 * @author     OwlManAtt <owlmanatt@gmail.com> 
 * @copyright  2007, Yasashii Syndicate 
 * @version    1.9.0
 */

/**
 * MySQL SQL driver for ActiveTable.
 *
 * @package    ActivePHP 
 * @author     OwlManAtt <owlmanatt@gmail.com> 
 * @copyright  2007, Yasashii Syndicate
 * @version    Release: @package_version@
 **/
class ActiveTable_SQL_MySQL implements ActiveTable_SQL
{   
    protected $columns = array();
    protected $from = '';
    protected $join = array();
    protected $where = array();
    protected $order = '';
    protected $limit = '';

    public function __construct()
    {
        // Initialize
        $this->reset();

    } // end __construct

    public function reset()
    {
        $this->columns = array();
        $this->from = '';
        $this->join = array();
        $this->where = array();
        $this->order = '';
        $this->limit = '';
    } // end reset

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
        $this->order = $sql_fragment;
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
                    $sql .= $this->order."\n";
                }

                if($this->limit != null)
                {
                    $sql .= "LIMIT {$this->limit}";
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
            $this->from = "`$table`";
        }
        else
        {
            $this->from = "`$database`.`$table`";
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
                $this->where[] = "`$table`.`$column` $type ?";

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
                
                $placeholders = implode(',',array_fill(0,$count,'?'));
                $this->where[] = "`$table`.`$column` $in ($placeholders)";
    
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
                
                $this->where[] = "`$table`.`$column` $is";
    
                break;
            } // end is, is_not

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
                $this->columns[] = "`{$table}`.`{$key}` AS `c{$table_id}_{$id}`";
            }
        } // end column loop

    } // end addKeys

    public function addVirtualKey($statement,$index)
    {
        $this->columns[] = "$statement AS `cVIRT_$index`";
    } // end addVirtualKey
    
    public function getDescribeTable($table_name,$database=null)
    {
        if($database == null)
        {
            return "DESCRIBE `$table_name`";
        }
        else
        {
            return "DESCRIBE `$database`.`$table_name`";
        }
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

        $this->join[] = "$join `{$foreign_table}` `{$foreign_table_alias}` ON `{$local_table}`.`{$local_key}` = `{$foreign_table_alias}`.`{$foreign_key}`";
    } // end addJoinClause

    public function getLastInsertId()
    {

    } // end getLastInsertId
} // end ActiveTable_MySQL_SQL

?>
