<?php
/**
 * The base file for the ActivePHP suite of tools. 
 *
 * @package    ActivePHP
 * @author     OwlManAtt <owlmanatt@gmail.com> 
 * @copyright  2007, Yasashii Syndicate 
 * @version    2.2.7 
 **/

// Required parts - the exception library.
require_once('exceptions.inc.php');

$base_path = realpath(getcwd());
$MODULES = array(
    'active_table',
    'csvio',
    'dagg'
);

foreach($MODULES as $module)
{
    $path = "$module/$module.class.php";

    // TODO - Verify the module exists.
    include_once($path);
} // end module load loop

?>
