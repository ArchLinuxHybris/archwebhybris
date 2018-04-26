<?php

function print_header($title) {
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <title>Arch Linux 32 - <?php print $title; ?></title>
    <link rel="stylesheet" type="text/css" href="/static/archweb.css" media="screen, projection" />
    <link rel="icon" type="image/x-icon" href="/static/favicon.ico" />
    <link rel="shortcut icon" type="image/x-icon" href="/static/favicon.ico" />
  </head>
  <body class="">
    <div id="archnavbar" class="anb-packages">
      <div id="archnavbarlogo">
        <h1><a href="/" title="Return to the main page">Arch Linux</a></h1>
      </div>
      <div id="archnavbarmenu">
        <ul id="archnavbarlist">
          <li id="anb-home"><a href="https://www.archlinux32.org/">Home</a></li>
          <li id="anb-news"><a href="https://news.archlinux32.org/">News</a></li>
          <li id="anb-packages"><a href="https://packages.archlinux32.org/">Packages</a></li>
          <li id="anb-forums"><a href="https://bbs.archlinux32.org/">Forums</a></li>
          <li id="anb-bugs"><a href="https://bugs.archlinux32.org/" title="Report and track bugs">Bugs</a></li>
          <li id="anb-mailing-list"><a href="https://lists.archlinux.org/listinfo/arch-ports">Mailing List</a></li>
          <li id="anb-download"><a href="https://www.archlinux32.org/download/" title="Get Arch Linux">Download</a></li>
          <li id="anb-arch-linux-official"><a href="https://www.archlinux.org/">Arch Linux Official</a></li>
        </ul>
      </div>
    </div>
    <div id="content">
<?php
}

function print_footer($copyright = "Copyright © 2002-2018 <a href=\"mailto:jvinet@zeroflux.org\" title=\"Contact Judd Vinet\">Judd Vinet</a> and <a href=\"mailto:aaron@archlinux.org\" title=\"Contact Aaron Griffin\">Aaron Griffin</a>.") {
?>
      <div id="footer">
        <p>
<?php

print "          " . $copyright . "\n";

?>
        </p>
        <p>
          The Arch Linux name and logo are recognized <a href="https://wiki.archlinux.org/index.php/DeveloperWiki:TrademarkPolicy" title="Arch Linux Trademark Policy">trademarks</a>. Some rights reserved.
        </p>
        <p>
          The registered trademark Linux® is used pursuant to a sublicense from LMI, the exclusive licensee of Linus Torvalds, owner of the mark on a world-wide basis.
        </p>
      </div>
    </div>
    <script type="application/ld+json">
      {
        "@context": "http://schema.org",
        "@type": "WebSite",
        "url": "/",
        "potentialAction": {
          "@type": "SearchAction",
          "target": "/?q={search_term}",
          "query-input": "required name=search_term"
        }
      }
    </script>
  </body>
</html>
<?php
}
