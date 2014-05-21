<?php
require_once dirname(__DIR__) . '/lib/Parser.php';

if (!isset($_SERVER['argv']))
{
	echo "ARGV is not available\n";
	exit;
}

$script = array_shift($_SERVER['argv']);
$switch = array_shift($_SERVER['argv']);
$xmlConfig = array_shift($_SERVER['argv']);

try {
	$parser = LogParser::getInstance($xmlConfig);
	$parser->run();
} catch(Exception $e) {
	echo $e->getMessage();
}