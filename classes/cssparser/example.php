<?php
include("cssparser.php");

$css = new cssparser();
$css->ParseStr("b {font-weight: bold; color: #777777;} b.test{text-decoration: underline;}");
echo $css->Get("b","color");     // returns #777777
echo $css->Get("b.test","color");// returns #777777
echo $css->Get(".test","color"); // returns an empty string
?>