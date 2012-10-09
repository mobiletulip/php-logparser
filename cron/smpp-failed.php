<?php
require_once '../lib/Parser.php';

try {
	$parser = LogParser::getInstance('../config/smpp-failed.xml');
	$parser->run();
} catch(Exception $e) {
	echo $e->getMessage();
}