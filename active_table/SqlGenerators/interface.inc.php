<?php
/**
 * Interfaces for building a new SQL generator for ActiveTablee.
 *
 * @package    ActivePHP 
 * @author     OwlManAtt <owlmanatt@gmail.com> 
 * @copyright  2007, Yasashii Syndicate 
 * @version    2.3.0
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
 *
 * @package    ActivePHP 
 * @author     OwlManAtt <owlmanatt@gmail.com> 
 * @copyright  2007, Yasashii Syndicate
 * @version    Release: @package_version@
 */
interface ActiveTable_SQL
{
    /**
     * Certain RDBMSes (like Oracle) support 'magic' columns that can be used
     * as primary keys. Set this to that column name (ie rowid for Oracle) and
     * that will be added to your SQL statements. 
     * 
     * @return string|null 
     */
    public function getMagicPkName();

    /**
     * Generate a WHERE fragment for use with PEAR::DB's autoexecute functionality.
     *
     * If this returns null, a default string will be generated.
     * This is most useful for things like Oracle, where CHARTOROWID() needs to be
     * called to turn the string back into an object Oracle can work with.
     * 
     * @param scalar $value 
     * @return void
     */
    public function getMagicUpdateWhere($table,$value,&$db);

    /**
     * Add the magic PK to the column list.
     *
     * This should return null if magic PKs are unused.
     *
     * @param string $table_name Name of primary table.
     * @return void
     */
    public function addMagicPkToKeys($table_name);
     
    /**
     * Return the SQL statement to get the PK for the last row inserted. 
     *
     * This is the equivillant of last_insert_id() in MySQL5.
     *
     * @return string The SQL statement.
     */ 
    public function getLastInsertId($table);

    /**
     * Return the SQL statement that performs a describe table.
     *
     * This is used for getting the column list back.
     *
     * @return string The SQL statement.
     */
    public function getDescribeTable($table_name,$database=null);

    /**
     * Return a datetime string formatted correctly for the RDBMS.
     *
     * @return string The appropriate string.
     **/
    public function getFormattedDate($datetime);

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
     * @param string The local table (one already available to the query).
     * @param string The key in the local table.
     * @param string The table being joined to.
     * @param string An alias for the table being joined to.
     * @param string The key in the table we are joining to that will be matched on.
     * @param string inner|left, support differs depending on your driver.
     * @param string The database to look in.
     * 
     * @return void
     */
    public function addJoinClause($local_table,$local_key,$foreign_table,$foreign_table_alias,$foreign_key,$join_type,$database=null);

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
     * Register 'virtual' columns to operate on.
     *
     **/
    public function addVirtualKey($statement,$index);
    
    /**
     * Register ...TODO
     *
     *
     */
    public function addWhere($table,$column,$type='equal',$count=0);
    public function addOrder($sql_fragment);
    public function addLimit($limit);
    
    /**
     * Register function to build a one-off LIMIT statement.
     *
     * This will construct an appropriate LIMIT statement for your RDBMS and the
     * number of args you are searching on (ie, AND/WHERE decision in OCI8).
     *
     * This is used for findOneBy() to stay DRY.
     *
     * @param integer The number of WHERE clauses.
     * @param integer The number of rows to retrieve. 
     **/
    public function buildOneOffLimit($condition_number,$limit_number);

    /**
     * Generate a SQL query to return a slice from a larger result set.
     *
     * @param integer $start The position to begin the slice at. Start at 0, not 1.
     * @param integer $end The position to end the slice at.
     **/
    public function setSlice($start,$end);

} // end ActiveTable_SQL
?>
