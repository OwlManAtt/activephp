<?php
/**
 * The default cacher to be used if no better method is available.
 *
 * The globals cacher ensures that a DESCRIBE will only be executed once
 * per table per page load. Obviously, this is not the optimal solution. 
 *
 * @package    ActivePHP 
 * @author     OwlManAtt <owlmanatt@gmail.com> 
 * @copyright  2007, Yasashii Syndicate 
 * @version    2.3.0
 */
class ActiveTable_Cache_Globals implements ActiveTable_Cache
{
    public function __construct()
    {
        // Initialize to avoid PHP notices.
        if(array_key_exists('ACTIVETABLE_CACHE',$GLOBALS) == false)
        {
            $GLOBALS['ACTIVETABLE_CACHE'] = array();
        }
    } // end __construct
    
    public function loadTable($table_name,$database='')
    {
        $datum = $GLOBALS['ACTIVETABLE_CACHE'][$database][$table_name];

        if(is_array($datum) == true)
        {
            return $datum;
        }

        return false;
    } // end loadTable
    
    public function addTable($table_name,$COLUMNS,$database='') 
    {
        /*
        * ACTIVETABLE_CACHE Global => Array(
        *   'database' => Array(
        *       'table' => Array(
        *           'col_1' => null,
        *           'col_2' => null,
        *           'col_3' => null,
        *       )
        *    )
        * )
        */

        // Does it need to be initialized?
        if(is_array($GLOBALS['ACTIVETABLE_CACHE']) == false)
        {
            $GLOBALS['ACTIVETABLE_CACHE'] = array();
        } // end initialize
        
        // DB's array need initializing?
        if(is_array($GLOBALS['ACTIVETABLE_CACHE'][$database]) == false)
        {
            $GLOBALS['ACTIVETABLE_CACHE'][$database] = array();
        }
        
        $GLOBALS['ACTIVETABLE_CACHE'][$database][$table_name] = $COLUMNS;

        return true;
    } // end addTable

} // end ActiveTable_Cache_Globals

?>
