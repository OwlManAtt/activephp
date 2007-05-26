<?php
/**
 * Classes for writing PL/SQL queries.
 *
 * @package    ActiveTable 
 * @author     OwlManAtt <owlmanatt@gmail.com> 
 * @copyright  2007, Yasashii Syndicate 
 * @version    1.7.0
 */

class ActiveTable_SQL_Oracle implements ActiveTable_SQL
{
    protected $columns = array();
    protected $from = array();
    protected $where = array();
    protected $order = '';

    public function __construct()
    {
        // Initialize
        $this->reset();
    } // end __construct

    public function reset()
    {
        $this->columns = array();
        $this->from = array();
        $this->where = array();
        $this->order = '';
    } // end reset

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
                
                $placeholders = implode(',',array_fill(0,$count,'?'));
                $this->where[] = "$table.$column $in ($placeholders)";
    
                break;
            } // end in, not_in

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
                $this->columns[] = "{$table}.{$key} AS c{$table_id}_{$id}";
            }
        } // end column loop
        
    } // end addKeys
    
    public function getDescribeTable($table_name,$database=null)
    {
        $sql = "SELECT COLUMN_NAME AS field FROM ALL_TAB_COLUMNS WHERE UPPER(TABLE_NAME) = UPPER('$table_name')";
        $sql .= " AND UPPER(OWNER) = UPPER('$database')";
        
        return $sql;
    } // end getDescribeTable

    public function addJoinClause($LOOKUPS)
    {
        // There are lookup tables - DO IT!
        if(sizeof($LOOKUPS) > 0)
        {
            foreach($LOOKUPS as $LOOKUP)
            {
                if($LOOKUP['database'] == null)
                {
                    $this->from[] = $LOOKUP['foreign_table'];
                }
                else
                {
                    $this->from[] = $LOOKUP['database'].'.'.$LOOKUP['foreign_table'];
                }
                
                switch(strtolower($LOOKUP['join_type']))
                {
                    default:
                    {
                        throw new ArgumentError("Unknown join type '{$LOOKUP['join_type']}' specified for '{$LOOKUP['foreign_table']}' lookup.",903);

                        break;
                    }
                   
                    /* 
                    case 'left':
                    {
                        $join = 'LEFT JOIN';
                        
                        break;
                    } // end left
                    */

                    case 'inner':
                    {
                        $this->where[] = "{$LOOKUP['local_table']}.{$LOOKUP['local_key']} = {$LOOKUP['foreign_table']}.{$LOOKUP['foreign_key']}";

                        break;
                    } // end inner
                } // end join switch
            } // end loopup loop
        } // end lookups > 0

    } // end addJoinClause

    public function getLastInsertId()
    {

    } // end getLastInsertId
} // end ActiveTable_Oracle_SQL

?>
