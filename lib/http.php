<?php

# do not include twice
if (function_exists("throw_http_error"))
  return;

function throw_http_error($error_number, $error_message, $extra_message = "") {
  header("Status: " . $error_number . " " . $error_message);
  print "Error " . $error_number . ": " . $error_message . "\n";
  if ($extra_message != "")
    print "<br>\n" . $extra_message;
  die();
};

function die_500($message) {
  throw_http_error(500, "Internal Server Error", $message);
};
