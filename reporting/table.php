<?php

use \Vanderbilt\CareerDevLibrary\NIHTables;
use \Vanderbilt\CareerDevLibrary\Download;
use \Vanderbilt\CareerDevLibrary\Application;

require_once(dirname(__FILE__)."/../charts/baseWeb.php");
require_once(dirname(__FILE__)."/../classes/NIHTables.php");
require_once(dirname(__FILE__)."/../classes/Download.php");
require_once(dirname(__FILE__)."/../Application.php");

$tableNum = @$_GET['table'];
if (!$tableNum || !NIHTables::getTableHeader($tableNum)) {
	die("Could not find $tableNum!");
}

$metadata = Download::metadata($token, $server);
$table = new NIHTables($token, $server, $pid, $metadata);

echo "<h1>Table ".NIHTables::formatTableNum($tableNum)."</h1>\n";
echo "<p class='centered'><a href='".Application::link("reporting/index.php")."'>Back to All Tables</a></p>";
$note = "";
if ($tableNum != "Common Metrics") {
    $note = " Its information must be re-keyed and uploaded through xTRACT.";
}
echo "<p class='centered max-width'>A tool to expedite reporting to the NIH, this table should be considered <b>preliminary</b> and requiring manual verification.$note</p>";
echo "<h2>".NIHTables::getTableHeader($tableNum)."</h2>\n";
echo $table->getHTML($tableNum);
