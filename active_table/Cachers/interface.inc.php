<?php
/**
 * Interfaces for building a new table definition cacher for ActiveTable. 
 *
 * @package    ActivePHP 
 * @author     OwlManAtt <owlmanatt@gmail.com> 
 * @copyright  2007, Yasashii Syndicate 
 * @version    2.3.0
 */

/**
 * Interface demonstrating what methods a Cacher absolutely must
 * implement before it can be included in ActiveTable.
 *
 * <code>
 * ActiveTable_Cache_Globals implements Activetable_Cache
 * {
 *    // . . . required methods . . . 
 * }
 * </code>
 *
 * @package    ActivePHP 
 * @author     OwlManAtt <owlmanatt@gmail.com> 
 * @copyright  2007, Yasashii Syndicate
 * @version    Release: @package_version@
 */
interface ActiveTable_Cache
{
    public function loadTable($table_name,$database='');
    public function addTable($table_name,$COLUMNS,$database='');
} // end ActiveTable_SQL
?>
