<?php
/**
 * Exceptions used by the ActivePHP suite. 
 *
 * @package    ActivePHP 
 * @author     OwlManAtt <owlmanatt@gmail.com> 
 * @copyright  2007, Yasashii Syndicate 
 * @version    2.3.0
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

/**
 * An exception to be thrown when the specified file cannot be opened. 
 *
 * @package    ActivePHP 
 * @version    Release: @package_version@
*/
class FileHandleError extends Exception
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
} // end FileHandleError 

/**
 * An exception to be thrown when there is an error in ActiveTable's SQL generation process.
 *
 * @package    ActivePHP 
 * @version    Release: @package_version@
*/
class SQLGenerationError extends Exception
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
} // end SQLGenerationError

?>
