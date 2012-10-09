<?php
require_once '../lib/Parser.php';

try {
	$parser = LogParser::getInstance('../config/smpp-status.xml');
	$parser->run();
} catch(Exception $e) {
	echo $e->getMessage();
}