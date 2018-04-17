<?php

# do not include twice
if (isset($mysql))
  return;

include "lib/http.php";

$mysql = new mysqli("localhost", "webserver", "empty", "buildmaster");
if ( $mysql -> connect_error ) {
  die_500( "Connection failed: " . $mysql -> connect_error );
}

function mysql_run_query($query) {
  global $mysql;
  if ( ! $result = $mysql -> query($query) )
    die_500( "Query failed: " .  $mysql -> error );
  return $result;
}

function show_warning_on_offline_slave() {
  $result = mysql_run_query("SHOW STATUS LIKE \"Slave_running\"");
  if (($result -> num_rows == 0) ||
    ($result -> fetch_assoc() ["Value"] != "ON")) {
    print "<div><font color=\"fff0000\">The replication slave is currently not running. The database might be outdated.</font></div>";
  }
}
