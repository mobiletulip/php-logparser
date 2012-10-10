<?php
$basePath = dirname(__DIR__);
require_once $basePath . '/lib/Parser.php';

$script = array_shift($argv);
$switch = array_shift($argv);
$xmlConfig = array_shift($argv);

try {
	$parser = LogParser::getInstance($xmlConfig);
	$parser->run();
} catch(Exception $e) {
	echo $e->getMessage();
}