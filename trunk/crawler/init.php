<?php
include("config.php");

$db = mysql_connect($dbinfo["server"], $dbinfo["username"], $dbinfo["password"]);
mysql_select_db($dbinfo["database"], $db);
mysql_set_charset("utf8");
        
foreach (array(
    'kaigokensaku_business',
    'kaigokensaku_business_office',
    'kaigokensaku_business_other',
    'kaigokensaku_business_price',
    'kaigokensaku_business_service',
    'kaigokensaku_business_staff',
) as $t) {
    mysql_query("TRUNCATE TABLE $t");
}
mysql_close();