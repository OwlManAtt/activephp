<?php
/**
 * Classes for writing PL/SQL queries.
 *
 * @package    ActivePHP 
 * @author     OwlManAtt <owlmanatt@gmail.com> 
 * @copyright  2007, Yasashii Syndicate 
 * @version    2.2.7
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
    public $magic_pk_name = 'rowid';

    public function __construct()
    {
        return null;
    } // end __construct

    public function getMagicPkName()
    {
        return 'rowid';
    } // end getMagicPkName

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
                $sql .= "FROM ".implode(", ",$this->from)."\n";
                    
                if(sizeof($this->where) > 0)
                {
                    $sql .= "WHERE ".implode("\nAND ",$this->where)."\n";
                }

                if($this->order != null)
                {
                    $sql .= $this->order."\n";
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
            $this->from[] = "$table";
        }
        else
        {
            $this->from[] = "$database.$table";
        }

        $this->columns[] = "ROWIDTOCHAR($table.rowid) AS cx_0";
        
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
} // end ActiveTable_Oracle_SQL

?>
