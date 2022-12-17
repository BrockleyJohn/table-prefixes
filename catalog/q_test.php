<?php

// load server configuration parameters
  include 'includes/configure.php';

// autoload classes in the classes or modules directories
  require 'includes/functions/autoloader.php';
  spl_autoload_register('tep_autoload_catalog');

// include the database functions
  require 'includes/functions/database.php';

// make a connection to the database... now
  tep_db_connect() or die('Unable to connect to database server!');

$dbug = true;
/*
$sql = "INSERT INTO whos_online (customer_id, full_name, session_id, ip_address, time_entry, time_last_click, last_page_url) SELECT wo.customer_id, wo.full_name, 'eb2cbefc5c12191c638c41ed4434c528', wo.ip_address, wo.time_entry, wo.time_last_click, wo.last_page_url FROM whos_online wo WHERE wo.session_id = '09f9c74c4c6bfa6f494d7f213b992797' ON DUPLICATE KEY UPDATE customer_id = VALUES(customer_id), full_name = VALUES(full_name), ip_address = VALUES(ip_address), time_entry = VALUES(time_entry), time_last_click = VALUES(time_last_click), last_page_url = VALUES(last_page_url)";

$sql = "INSERT INTO table (column1, column2, column3) SELECT alias.column1, alias.column2, 'mynewid' FROM table alias WHERE alias.column3 = 'myoldid' ON DUPLICATE KEY UPDATE column1 = VALUES(column1), column2 = VALUES(column2)";

$sql = "INSERT INTO table (column1, column2, column3) SELECT alias.column1, alias.column2, 'mynewid' FROM table alias WHERE alias.column3 = 'myoldid'";

include_once 'vendor/autoload.php';
try {
  
  $parser = new \PHPSQLParser\PHPSQLParser($sql);
  $creator = new \PHPSQLParser\PHPSQLCreator();
  $q_array = $parser->parsed;
  $new_sql = $creator->create($q_array);

  echo "rebuilt sql: $new_sql";

} catch (\Exception $e) {
  echo "rebuilding sql <br>$sql<br><br>" . PHP_EOL;
  echo 'failed with exception ' . $e->getMessage() . '<br/><pre>';
  if (isset($q_array)) print_r($q_array);
}
*/
//$q = tep_db_query("select * from `products_options` where `products_options_name` is null");
$q = tep_db_query("select customers_id from orders group by customers_id");
//$q = tep_db_query('SELECT popt.products_options_name AS `option`, poval.products_options_values_name AS value, pa.options_id AS option_id, pa.options_values_id AS value_id, pa.price_prefix AS prefix, pa.options_values_price AS price FROM products_options popt INNER JOIN products_attributes pa ON pa.options_id = popt.products_options_id INNER JOIN products_options_values poval ON pa.options_values_id = poval.products_options_values_id AND popt.language_id = poval.language_id WHERE pa.products_id = 99921110 AND pa.options_id = 1 AND pa.options_values_id = 642 AND popt.language_id = 2');
while ($r = tep_db_fetch_array($q)) {
  print_r($r);
}
echo "path " . __DIR__;