SmppBox Log Parser for kannel that sends statistics to Graphite

[![Current build Status](https://api.travis-ci.org/tlenss/php-logparser.png)](https://travis-ci.org/tlenss/php-logparser)

Running the parser:
```shell
$ php parse.php --xml sample.xml
```
A simple cron script example parser:
```php
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
```
Where ../config/sample.xml looks like:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <!-- With debugging enabled no data will be send to Graphite but instead pushed to stdout -->
    <debug>false</debug>
    
    <!-- Push output to stdout -->
    <verbose>true</verbose>
    
    <!-- Location where we should store the state file -->
    <state_file>/state/file/location.state</state_file>
    
    <!-- Location of the logfile -->
    <log_file>/log/file/location.log</log_file>
    
    <!-- Location of the logtail2 binary -->
    <logtail_bin>/usr/sbin/logtail2</logtail_bin>
    
    <!-- Regular expression to parse logfile lines -->
    <regexp>/^(\S+) (\S+) (\S+) FAILED .*/</regexp>
    
    <!-- The regex field number to use for graphite stats -->
    <regex_num>3</regex_num>
    
    <!-- Graphite settings -->
    <graphite>
        <enabled>true</enabled>
        <label>graphite.stats.string</label>
        <host>localhost</host>
        <port>2003</port>
        <timeout>30</timeout>
    </graphite>

    <!-- SMS warning settings -->
    <sms_warning>
        <enabled>true</enabled>
        <!-- API URL for send alert SMS -->
        <api_url>API URL</api_url>
        <!-- Location where we should store the last message time state file -->
        <state_file>/state/file/location.state</state_file>
        <threshold>10</threshold>
        <!-- For which values of regexp_num result should be send a SMS -->
        <needles>
            <needle>bind_transceiver</needle>
            <needle>bind_receiver</needle>
        </needles>
        <message>Warning FAILED BINDS</message>
        <!-- Mollie SMS credentials -->
        <provider>
            <username>username</username>
            <password>password</password>
            <gateway>2</gateway>
            <originator>originator</originator>
        </provider>
        <!-- Phone numbers - multiple comma separated -->
        <recipients>+31123456789</recipients>
    </sms_warning>
</config>
```
