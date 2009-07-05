<?php
/**
 * Classes for writing MySQL 4 and 5 compatible queries.
 *
 * @package    ActivePHP 
 * @author     OwlManAtt <owlmanatt@gmail.com> 
 * @copyright  2007-2008, Yasashii Syndicate 
 * @version    2.4.0
 */

/**
 * MySQL SQL driver for ActiveTable.
 *
 * @package    ActivePHP 
 * @author     OwlManAtt <owlmanatt@gmail.com> 
 * @copyright  2007-2008, Yasashii Syndicate
 * @version    Release: @package_version@
 **/
class ActiveTable_SQL_MsSQL implements ActiveTable_SQL
{   
    protected $columns = array();
    protected $from = '';
    protected $join = array();
    protected $where = array();
    protected $order = '';
    protected $order_direction = '';
    protected $limit = '';
    public $magic_pk_name = null;

    // If get_slice is true, a slice query will be generated.
    private $get_slice = false;
    private $slice_size = null;
    private $slice_end = null;
    private $order_columns = null;
    private $slice_ordering_column_name_cache = null;

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
            // We need to be able to use the order by information for slicing because MS SQL is retarded and has no ROWNUM or OFFSET.
            throw new SQLGenerationError('Using a raw SQL order-by fragment with the MS SQL driver is unsupported. Please use array syntax.');
        }
        else
        {
            $ORDER = array();
            $COLUMNS = $sql_fragment['columns'];
            $RAW_COLUMNS = array();
            
            foreach($COLUMNS as $COLUMN)
            {
                $ORDER[] = "[{$COLUMN['table']}].[{$COLUMN['column']}]";

                // Find the alias for this column, since the outer queries in the nested query won't have table.
                $RAW_COLUMNS[] = '['.$this->slice_ordering_column_name_cache[$COLUMN['table']][$COLUMN['column']].']';
            }
            
            $this->order = $ORDER;
            $this->order_direction = $sql_fragment['direction'];
            $this->order_columns = $RAW_COLUMNS;
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
                
                if($this->limit != null)
                {
                    $sql .= "TOP ({$this->limit})\n";
                }
                elseif($this->get_slice == true)
                {
                    $sql .= "TOP ({$this->slice_end})\n";
                }
                
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
                    if($this->get_slice == true && $this->order_direction != 'DESC')
                    {
                        if($this->order_direction == 'DESC')
                        {
                            $SLICE_ORDER = array('DESC','ASC','DESC');
                        }
                        else
                        {
                            $SLICE_ORDER = array('ASC','DESC','ASC');
                        }
                    } // end build the ORDER BY orders.
                    
                    $sql .= "ORDER BY ".implode(', ',$this->order)." {$this->order_direction}\n";
                }
                elseif($this->order == null && $this->get_slice == true)
                {
                    // If there is no order by clause specified, use the first column (which we have AS'd to cx_0).
                    // Kind of kludgy, but I don't know what else to do here apart from throw an exception, and that
                    // would make the MS SQL driver work *way* to differently from the rest.
                    $sql .= "ORDER BY cx_0 DESC\n";  
                    $this->order_columns = array('cx_0');
                }

                if($this->get_slice == true)
                {
                    /*
                    * Selecting the slice 10, 40. MS SQL does not have an OFFSET, mysqlesque LIMIT, or ROWNUM[1].
                    * To do this, we need to take every row UP TO the last row we want, then flip that around and
                    * take the top $how_many_we_want rows from that result set. Then we flip it back around so its
                    * in the order we wanted it originally.
                    *
                    * [1] SQL Server 2005 has something like rownum, but it works weirdly with the ORDER BY 
                    *     (some kind of OVER (order by clause) in the beginning of the query) and that would generate 
                    *     invalid SQL for a SQL Server 2000 server, which is Bad (and useless to me!).
                    *
                    * SELECT * FROM 
                    * (
                    *     SELECT TOP (30) -- Number of results you want ${40 - 10}
                    *         * 
                    *     FROM 
                    *     (
                    *         SELECT TOP (40) -- 'Deepest' result you want (We need 40 rows to get the bottom 30)
                    *             affiliate_id, comapany_name 
                    *         FROM affiliate 
                    *         ORDER BY affiliate_id DESC
                    *     )
                    *     ORDER BY affiliate_id ASC 
                    * )
                    * ORDER BY affiliate_id DESC
                    */
                    $wrapper = "SELECT * FROM (\n";
                        $wrapper .= "SELECT TOP ({$this->slice_size}) *\n";
                        $wrapper .= "FROM (\n";
                            $wrapper .= $sql; 
                        $wrapper .= ") AS [fake_table]\n"; 
                        $wrapper .= "ORDER BY ".implode(', ',$this->order_columns)." {$SLICE_ORDER[1]}\n";
                    $wrapper .= ") AS [another_fake_table]\n";
                    $wrapper .= "ORDER BY ".implode(', ',$this->order_columns)." {$SLICE_ORDER[2]}";

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
            $this->from = "[$table]";
        }
        else
        {
            $this->from = "[$database].[$table]";
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
                $this->where[] = "[$table].[$column] $type ?";

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
                
                $this->where[] = "[$table].[$column] $in ($placeholders)";
    
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
                
                $this->where[] = "[$table].[$column] $is";
    
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
                
                $this->where[] = "[$table].[$column] $like ?";

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
        $this->limit = $limit;
    } // end addLimit
    
    // Must return in the format table__column
    public function addKeys($table,$COLUMNS,$table_id='x')
    {
        $COLUMN_NAME_CACHE = array();
        
        foreach($COLUMNS as $id => $key)
        {
            if($key != null)
            {
                $column_alias = "c{$table_id}_{$id}";
                $this->columns[] = "[{$table}].[{$key}] AS [$column_alias]";
                
                if(array_key_exists($table,$COLUMN_NAME_CACHE) == false)
                {
                    $COLUMN_NAME_CACHE[$table] = array();
                }
                
                $COLUMN_NAME_CACHE[$table][$key] = $column_alias;
            } // end key not null do it do it
        } // end column loop
            
        $this->slice_ordering_column_name_cache = $COLUMN_NAME_CACHE;
    } // end addKeys

    public function addVirtualKey($statement,$index)
    {
        $this->columns[] = "$statement AS [cVIRT_$index]";
    } // end addVirtualKey
    
    public function getDescribeTable($table_name,$database=null)
    {
        $sql = "SELECT COLUMN_NAME AS [field], DATA_TYPE AS [type] FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '{$table_name}'";

        if($database != null)
        {
            $sql .= " AND TABLE_CATALOG = '{$database}'"; 
        }

        return $sql;
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

        $this->join[] = "$join [{$foreign_table}] [{$foreign_table_alias}] ON [{$local_table}].[{$local_key}] = [{$foreign_table_alias}].[{$foreign_key}]";
    } // end addJoinClause

    public function getLastInsertId($table)
    {
        throw new SQLGenerationError('Write operations are not currently implemented for MSSQL. Cannot generate last insert ID query.');
    } // end getLastInsertId

    public function setSlice($start,$end)
    {
        $this->get_slice = true;
        $this->slice_size = ($end - $start);
        $this->slice_end = $end;
    } // end setSlice

    public function getReservedWordEscapeCharacterLeft()
    {
        return '[';
    } // end getReservedWordEscapeCharacter

    public function getReservedWordEscapeCharacterRight()
    {
        return ']';
    } // end getReservedWordEscapeCharacter

} // end ActiveTable_MySQL_SQL

?>
