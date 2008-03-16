<?php
require_once('interface.inc.php');
require_once('oci.class.php');
require_once('mysql.class.php');

$query = new ActiveTable_SQL_Oracle();
$query->addFrom('customer','eplus');
$query->addKeys('customer',array('cust_number','company_name'),'a');
$query->addKeys('sales_category_lookup',array('sales_category_desc'),'b');
$query->addJoinClause('customer','sales_category_id','sales_category_lookup','sales_category_lookup','sales_category_id','inner','database');
$query->addWhere('customer','state');
$query->setSlice(1,10);
print "== Oracle query:\n\n";
print_r($query->getQuery('select'));
print "\n\n";

$query = new ActiveTable_SQL_MySQL();
$query->addFrom('sale');
$query->addKeys('sale',array('sale_id','acct','customer_name'),'a');
$query->addKeys('sale_status',array('long_description'),'b');
$query->addJoinClause('sale','sale_status_id','sale_status','sale_status','sale_status_id','inner');
$query->addWhere('sale','site_state');
$query->setSlice(0,10);
print "== MySQL query:\n\n";
print_r($query->getQuery('select'));
print "\n\n";

?>
