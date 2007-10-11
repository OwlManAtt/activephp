<?php
/**
 * Exceptions used by the ActivePHP suite. 
 *
 * @package    ActivePHP 
 * @author     OwlManAtt <owlmanatt@gmail.com> 
 * @copyright  2007, Yasashii Syndicate 
 * @version    2.2.7
 **/

/**
 * An exception to be thrown when the specified file is a blank and it is expected to be non-blank. 
 *
 * @package    ActivePHP 
 * @version    Release: @package_version@
*/
class FileEmptyError extends Exception
{
    /**
     * Sets up the exception. 
     * 
     * @param   string    $message    The error text.
     * @param   int       $code       An error code.
     * @access private
     * @return void
    */
    public function __construct($message, $code = 0) 
    {
        parent::__construct($message,$code);
    }

    /**
     * Convert the exception into a string. 
     * 
     * @access private
     * @return string
    */
    public function __toString() 
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message} in '{$this->file}' on line {$this->line}.\n";
    }
} // end FileEmptyError 

class ArgumentError extends Exception
{
    /**
     * Sets up the exception. 
     * 
     * @param   string    $message    The error text.
     * @param   int       $code       An error code.
     * @access private
     * @return void
    */
    public function __construct($message, $code = 0)
    {
        parent::__construct($message,$code);
    }

    /**
     * Convert the exception into a string. 
     * 
     * @access private
     * @return string
    */
    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message} in '{$this->file}' on line {$this->line}.\n";
    }

} // end ArgumentError

class SQLError extends Exception
{   
    /**
     * The offending SQL query.
     *
     * @access private
     * @var string
     */
    public $sql;

    /**
     * Sets up the exception. 
     * 
     * @param   string    $message    The error text.
     * @param   string    $sql        The query that failed. 
     * @param   int       $code       An error code.
     * access   private
     * @return void
    */
    public function __construct($message,$sql,$code = 0)
    {
        parent::__construct($message,$code);
        $this->sql = $sql;
    }

    /**
     * Convert the exception into a string. 
     * 
     * @return string
    */
    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message} in '{$this->file}' on line {$this->line}. SQL specified was: {$this->sql}\n";
    }

} // end


?>