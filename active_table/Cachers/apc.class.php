<?php
/**
 * A cacher to take advantage of APC's ability to store stuff between
 * page requests. This reduces the DESC table overhead to almost nothing,
 * since it describes it once per webserver restart... 
 *
 * @package    ActivePHP 
 * @author     OwlManAtt <owlmanatt@gmail.com> 
 * @copyright  2007, Yasashii Syndicate 
 * @version    2.7.0
 */
class ActiveTable_Cache_APC implements ActiveTable_Cache
{
    public function loadTable($table_name,$database='')
    {
        $datum = apc_fetch("{$database}__{$table_name}"); 

        if(is_array($datum) == true)
        {
            return $datum;
        }

        return false;
    } // end loadTable
    
    public function addTable($table_name,$COLUMNS,$database='') 
    {
        $datum = apc_store("{$database}__{$table_name}",$COLUMNS); 

        return true;
    } // end addTable

} // end ActiveTable_Cache_APC

?>
