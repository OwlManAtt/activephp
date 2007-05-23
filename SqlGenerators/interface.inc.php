<?php
/**
 * Interfaces for building a new SQL generator for ActiveTablee.
 *
 * @package    ActiveTable 
 * @author     OwlManAtt <owlmanatt@gmail.com> 
 * @copyright  2007, Yasashii Syndicate 
 * @version    1.6.0
 */

/**
 * Interface demonstrating what methods a SQL Generator absolutely must
 * implement before it can be included in ActiveTable.
 *
 * <code>
 * ActiveTable_SQL_Postgresql implements Activetable_SQL
 * {
 *    // . . . required methods . . . 
 * }
 * </code>
 */
interface ActiveTable_SQL
{
    /**
     * Clear the SQL generator instance's cache out.
     *
     * This is to be called whenever a SQL statement has been set
     * up and pulled out. 
     *  
     * @return void
     */
    public function reset();
   
    /**
     * Return the SQL statement to get the PK for the last row inserted. 
     *
     * This is the equivillant of last_insert_id() in MySQL5.
     *
     * return string The SQL statement.
     */ 
    public function getLastInsertId();

    /**
     * Return the SQL statement that performs a describe table.
     *
     * This is used for getting the column list back.
     *
     * return string The SQL statement.
     */
    public function getDescribeTable($table_name,$database=null);

    /**
     * Set what action this query is to perform and generate it.
     *
     * Depending on the verb, a different type of statement will be
     * generated. The verbs implemented should be select, delete, update,
     * and insert.
     *
     * @param string select, delete, update, insert.
     * return string The SQL statement.
     */
    public function getQuery($verb);

    /**
     * Register the primary table you are selecting from.
     *
     * @param string The table name.
     * @param string The database or user in which the table resides (eplus.customer, e911.MAIN_NENA).
     * @return void
     */
    public function addFrom($table,$database=null);

    /**
     * Register the list of tables to join to and the columns to join on.
     *
     * For more information on the structure of the lookup arrays, see the
     * $LOOKUPS documentation on the ActiveTable class.
     *
     * @param array An array of arrays detailing the lookup tables.
     * @return void
     */
    public function addJoinClause($LOOKUPS);

    /**
     * Register columns to operate upon.
     *
     * @param string The table these columns are from.
     * @param array The list of columns to be operated upon.
     * @param string A prefix that should be unique to the table but no more than
     *               three characters.
     * @return void
     */
    public function addKeys($table,$COLUMNS,$table_id='x');
    
    /**
     * Register ...TODO
     *
     *
     */
    public function addWhere($table,$column,$type='equal',$count=0);
    public function addOrder($sql_fragment);
    public function addLimit($limit);
} // end ActiveTable_SQL
?>
