<?php

# do not include twice
if (function_exists("export_as_requested"))
  return;

require_once "../init.php";
include_once BASE . "/lib/http.php";

function export_as_requested($content) {
  if (isset($content["json"]) && isset($_GET["json"])) {
    header ("Content-type: application/json");
    print json_encode(
      $content["json"],
      JSON_UNESCAPED_SLASHES
    );
  } elseif (isset($content["tsv"]) && isset($_GET["tsv"])) {
    header ("Content-type: text/tab-separated-values");
    if (! isset($_GET["no-headers"]))
      print implode("\t",array_keys($content["tsv"][0])) . "\n";
    print implode(
      "\n",
      array_map(
        function($row){
          return implode("\t",$row);
        },
        $content["tsv"]
      )
    );
  } else {
    throw_http_error(406,"Not Acceptable","Unknown output format.");
  }
}
