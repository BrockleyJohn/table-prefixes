<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2020 osCommerce

  Released under the GNU General Public License
*/

  function tep_db_connect($server = DB_SERVER, $username = DB_SERVER_USERNAME, $password = DB_SERVER_PASSWORD, $database = DB_DATABASE, $link = 'db_link') {
    global $$link;

    $$link = mysqli_connect($server, $username, $password, $database);

    if ( !mysqli_connect_errno() ) {
      mysqli_set_charset($$link, 'utf8');
    }

    @mysqli_query($$link, 'SET SESSION sql_mode=""');

    return $$link;
  }

  function tep_db_close($link = 'db_link') {
    global $$link;

    return mysqli_close($$link);
  }

  function tep_db_error($query, $errno, $error) {
    global $logger;

    if (defined('STORE_DB_TRANSACTIONS') && (STORE_DB_TRANSACTIONS == 'true')) {
      $logger->write('[' . $errno . '] ' . $error, 'ERROR');
    }

    die('<font color="#000000"><strong>' . $errno . ' - ' . $error . '<br /><br />' . $query . '<br /><br /><small><font color="#ff0000">[TEP STOP]</font></small><br /><br /></strong></font>');
  }

  function bb_arr_table(&$value, $key, &$map) {
    if ($key === 'table') {
      if (! array_key_exists(trim($value, '`'), $map)) {
        exit("TABLE PREFIX ERROR - <b>$key $value</b> not found");
      }
      $value = $map[trim($value, '`')];
    }
  }

  function bb_db_table($sql) {
    static $replace, $direct, $parser, $creator;
    global $dbug;
    if (! isset($dbug)) $dbug = false;
    if (! isset($replace)) {
      // set up list of core_names:
      $core = [
'categories',
'categories_description',
'products',
'products_attributes',
'products_attributes_download',
'products_attributes_quantities',
'products_availability',
'products_delivery',
'products_description',
'products_guaranty',
'products_images',
'products_notifications',
'products_options',
'products_options_values',
'products_options_values_to_products_options',
'products_related_products',
'products_stock',
'products_tags',
'products_to_categories',
'hanfhaus_action_recorder',
'action_recorder',
'address_book',
'address_format',
'administrators',
'articles',
'articles_description',
'articles_images',
'articles_to_folders',
'authors',
'authors_info',
'banners',
'banners_history',
'configuration',
'configuration_group',
'content',
'content_description',
'content_images',
'countries',
'currencies',
'customer_data_groups',
'customers',
'customers_basket',
'customers_basket_attributes',
'customers_info',
'customers_to_discount_codes',
'database_optimizer',
'discount_codes',
'featured',
'folders',
'folders_description',
'geo_zones',
'hooks',
'information',
'information_group',
'klarna_ordernum',
'languages',
'magiczoomplus_configuration',
'manufacturers',
'manufacturers_info',
'mm_bulkmail',
'mm_newsletters',
'mm_responsemail',
'mm_responsemail_backup',
'mm_responsemail_reset',
'mm_templates',
'newsletters',
'orders',
'orders_products',
'orders_products_attributes',
'orders_products_download',
'orders_status',
'orders_status_history',
'orders_total',
'oscom_app_paypal_log',
'pages',
'reviews',
'reviews_description',
'secupay_iframe_url',
'secupay_transactions',
'secupay_transaction_order',
'secupay_transaction_tracking',
'sec_directory_whitelist',
'sessions',
'sessions_save',
'slides',
'slides_description',
'specials',
'stripe_event_log',
'tax_class',
'tax_rates',
'testimonials',
'testimonials_description',
'usu_cache',
'whos_online',
'zones',
'zones_to_geo_zones'
      ];
      $replace = [];
      foreach ($core as $tab) {
//        $rep = $tab == 'products' || $tab == 'categories' ? DB_COMMON_TABLE_PREFIX . $tab : DB_TABLE_PREFIX . $tab;
        $rep = strpos($tab, 'products') === 0 || strpos($tab, 'categories') === 0 ? DB_COMMON_TABLE_PREFIX . $tab : DB_TABLE_PREFIX . $tab;
        $replace['/(\s|,|`)' . $tab . '(\s|,|.|`|$)/'] = '${1}' . $rep . '$2';
        $direct[$tab] = $rep;
      }
    }
//    echo "sql before '$sql'<br>\n";
    switch (true) {
      case (substr_count(strtoupper($sql), 'SELECT') > 1) : // select with subselect
      case ((stripos($sql, 'insert ') !== false || stripos($sql, 'update ') !== false || stripos($sql, 'delete ') !== false) && stripos($sql, 'select ') !== false) : // insert/update/delete with subselect
      //case true :
        if ($dbug) echo 'using parser<br>';
        $sql_tail = '';
        // ON DUPLICATE KEY UPDATE breaks it so...
        if (stripos($sql, 'ON DUPLICATE KEY UPDATE') !== false) {
          $sql_tail = substr($sql, stripos($sql, 'ON DUPLICATE KEY UPDATE'));
          $sql = substr($sql, 0, stripos($sql, 'ON DUPLICATE KEY UPDATE'));
        }
        try {
          if (! isset($parser)) {
            include_once DIR_FS_CATALOG . 'vendor/autoload.php';
            $parser = new \PHPSQLParser\PHPSQLParser($sql);
            $creator = new \PHPSQLParser\PHPSQLCreator();
          } else {
            $parser->parse($sql);
          }
          $q_array = $parser->parsed;
          if ($dbug) (print_r($q_array));
          array_walk_recursive($q_array, 'bb_arr_table', $direct);
          if ($dbug) (print_r($q_array));
          $new_sql = $creator->create($q_array);
        } catch (\Exception $e) {
          exit('TABLE PREFIX EXCEPTION: ' . $e->getMessage() . "<br>processing query<br>$sql");
        }
        $new_sql .= $sql_tail;
        break;
      default :
        if ($dbug) echo 'using regex<br>';
        switch (true) { // what kind of statement?
          case (stripos($sql, DB_TABLE_PREFIX) !== false) : // skip the sessions ones already edited...
            $new_sql = $sql;
            break;
          case stripos($sql, 'create table ') !== false :
          case stripos($sql, 'create temporary table ') !== false :
            $to_left = stripos($sql, ' table ');
            $from_right = stripos($sql, '(');
            break;
          case stripos($sql, 'alter table ') !== false :
            $to_left = stripos($sql, 'alter table ');
            if (stripos($sql, ' add ') !== false) {
              $from_right = stripos($sql, ' add ');
            } elseif (stripos($sql, ' modify ') !== false) {
              $from_right = stripos($sql, ' modify ');
            } elseif (stripos($sql, ' change ') !== false) {
              $from_right = stripos($sql, ' change ');
            } elseif (stripos($sql, ' alter ') !== false) {
              $from_right = stripos($sql, ' alter ');
            } else {
              $from_right = strlen($sql);
            }
            break; 
          case stripos($sql, 'update ') !== false :
            $to_left = stripos($sql, 'update ');
            if (stripos($sql, 'set') !== false) {
              $from_right = stripos($sql, 'set');
            } else {
              $from_right = strlen($sql);
            }
            break;
          case stripos($sql, 'insert ') !== false :
            $to_left = stripos($sql, 'insert ');
            if (stripos($sql, '(') !== false) {
              $from_right = stripos($sql, '(');
            } else {
              $from_right = strlen($sql);
            }
            break;
          case stripos($sql, 'select ') !== false || stripos($sql, 'delete ') !== false :
          default : // lets assume it was really a select and try that approach
            $to_left = stripos($sql, 'from ');
            if (stripos($sql, 'where') !== false) {
              $from_right = stripos($sql, 'where');
            } elseif (stripos($sql, 'like') !== false) {
              $from_right = stripos($sql, 'like');
            } elseif (stripos($sql, 'order by') !== false) {
              $from_right = stripos($sql, 'order by');
            } elseif (stripos($sql, 'group by') !== false) {
              $from_right = stripos($sql, 'group by');
            } else {
              $from_right = strlen($sql);
            }
            break;
        }
        $tb_bit = substr($sql, $to_left, $from_right - $to_left);
    //    echo "bit before '$tb_bit'<br>\n";
        $tb_bit = preg_replace(array_keys($replace), array_values($replace), $tb_bit);
    //    echo "bit after '$tb_bit'<br>\n";
        $new_sql = substr($sql, 0, $to_left) . $tb_bit . ($from_right < strlen($sql) ? substr($sql, $from_right, strlen($sql) - $from_right) : '');
    //    exit;
    }
    return $new_sql;
  }

  function tep_db_query($query, $link = 'db_link') {
    global $$link;

    if (strpos($query, ':table_') !== false) {
$query = str_replace(':table_products', DB_COMMON_TABLE_PREFIX . 'products', $query);
	$query = str_replace(':table_categories', DB_COMMON_TABLE_PREFIX . 'categories', $query);
    $query = str_replace(':table_', DB_TABLE_PREFIX, $query);
      
    } else {
      $query = bb_db_table($query);
    }

    if (defined('STORE_DB_TRANSACTIONS') && (STORE_DB_TRANSACTIONS == 'true')) {
      error_log('QUERY: ' . $query . "\n", 3, STORE_PAGE_PARSE_TIME_LOG);
    }

    $result = mysqli_query($$link, $query) or tep_db_error($query, mysqli_errno($$link), mysqli_error($$link));

    return $result;
  }

  function tep_db_perform($table, $data, $action = 'insert', $parameters = '', $link = 'db_link') {
    if ($action == 'insert') {
      $query = 'INSERT INTO ' . $table . ' (' . implode(', ', array_keys($data)) . ') VALUES (';

      foreach ($data as $value) {
        switch ((string)$value) {
          case 'NOW()':
          case 'now()':
            $query .= 'NOW(), ';
            break;
          case 'NULL':
          case 'null':
            $query .= 'NULL, ';
            break;
          default:
            $query .= '\'' . tep_db_input($value) . '\', ';
            break;
        }
      }
      $query = substr($query, 0, -strlen(', ')) . ')';
    } elseif ($action == 'update') {
      $query = 'UPDATE ' . $table . ' SET ';
      foreach ($data as $column => $value) {
        switch ((string)$value) {
          case 'NOW()':
          case 'now()':
            $query .= $column . ' = NOW(), ';
            break;
          case 'NULL':
          case 'null':
            $query .= $column . ' = NULL, ';
            break;
          default:
            $query .= $column . ' = \'' . tep_db_input($value) . '\', ';
            break;
        }
      }
      $query = substr($query, 0, -strlen(', ')) . ' WHERE ' . $parameters;
    }

    return tep_db_query($query, $link);
  }

  function tep_db_copy($db, $key, $value) {
    $key_value = false;
    foreach ($db as $table => $columns) {
      $values = [];
      foreach ($columns as $name => $v) {
        if ($key_value && ($name === $key) && is_null($v)) {
          $v = $key_value;
        }

        $values[] = ($v ?? $name);
      }

      tep_db_query('INSERT INTO ' . $table
        . ' (' . implode(', ', array_keys($columns))
        . ') SELECT ' . implode(', ', $values)
        . ' FROM ' . $table . ' WHERE ' . $key . ' = ' . $value);

      if (!$key_value) {
        $key_value = tep_db_insert_id();
      }
    }

    return $key_value;
  }

  function tep_db_fetch_array($db_query) {
    return mysqli_fetch_array($db_query, MYSQLI_ASSOC);
  }

  function tep_db_result($result, $row, $field = '') {
    if ( $field === '' ) {
      $field = 0;
    }

    tep_db_data_seek($result, $row);
    $data = tep_db_fetch_array($result);

    return $data[$field];
  }

  function tep_db_num_rows($db_query) {
    return mysqli_num_rows($db_query);
  }

  function tep_db_data_seek($db_query, $row_number) {
    return mysqli_data_seek($db_query, $row_number);
  }

  function tep_db_insert_id($link = 'db_link') {
    global $$link;

    return mysqli_insert_id($$link);
  }

  function tep_db_free_result($db_query) {
    return mysqli_free_result($db_query);
  }

  function tep_db_fetch_fields($db_query) {
    return mysqli_fetch_field($db_query);
  }

  function tep_db_output($string) {
    return htmlspecialchars($string);
  }

  function tep_db_input($string, $link = 'db_link') {
    global $$link;

    return mysqli_real_escape_string($$link, $string);
  }

  function tep_db_prepare_input($string) {
    if (is_string($string)) {
      return trim(stripslashes($string));
    }

    if (is_array($string)) {
      foreach ($string as $key => $value) {
        $string[$key] = tep_db_prepare_input($value);
      }
    }

    return $string;
  }

  function tep_db_affected_rows($link = 'db_link') {
    global $$link;

    return mysqli_affected_rows($$link);
  }

  function tep_db_get_server_info($link = 'db_link') {
    global $$link;

    return mysqli_get_server_info($$link);
  }
