<?php
/**
 * SmppBox Log Parser (http://mobiletulip.com/)
 *
 * @link https://github.com/mobiletulip/php-logparser
 * @copyright Copyright (c) 2012 Mobile Tulip (http://mobiletulip.com/)
 * @license https://github.com/mobiletulip/php-logparser/license/new-bsd
 * @package SmppBoxParser
 */

/**
 * Class to parse Kannel SMPP logs and send stats to Graphite
 *
 * @category MT
 * @package SmppBoxParser
 */
Class SmppBoxParser 
{
	/**
	 * State file for Apache2 logtail
	 * 
	 * @var string
	 */
	private static $_stateFile = './state';
	
	/**
	 * Location of the Apache2 logtail2 binary
	 * 
	 * @var string
	 */
	private static $_logtailBin = '/usr/sbin/logtail2';
	
	/**
	 * Logfile line parsing regular expression
	 * 
	 * @var string
	 */
	private static $_lineRegexp = '/^(\S+) (\S+) \[(\S+)\] \[(\S+)\] ERROR: \[.* (?P<code>\d{1,3}): .*\]: /';
	
	/**
	 * Graphit elogging string
	 * 
	 * @var string
	 */
	private static $_graphiteString = 'server.dc1-sms-1.smppbox.errors.status_';
	
	/**
	 * Graphite host address
	 * 
	 * @var string
	 */
	private static $_graphiteHost = 'localhost';
	
	/**
	 * Graphite host port
	 * 
	 * @var integer
	 */
	private static $_graphitePort = 2023;
	
	/**
	 * Graphite socket timeout in seconds
	 * 
	 * @var integer
	 */
	private static $_graphiteTimeout = 30;
	
	/**
	 * Trigger the parsing process
	 * 
	 * @param string $logfile
	 * @param string $stateFile
	 * @param string $logtail
	 * @return void
	 */
	public static function Parse($logfile, $stateFile = null, $logtail = null) 
	{
		$codes = array();
		if (is_null($stateFile)) {
			$stateFile = self::$_stateFile;
		}
		if (is_null($logtail)) {
			$logtail = self::$_logtailBin;
		}
		if (file_exists($logfile)) {
			$logOutput = shell_exec($logtail . " -f " . $logfile . " -o " . $stateFile);
			$outputArray = explode("\n", $logOutput);
			foreach ($outputArray as $line) {
				preg_match_all(self::$_lineRegexp, $line, $matches);
				if (isset($matches['code'][0])) {
					$codes[] = $matches['code'][0];
				}
			}
			self::Send($codes);
		}
	}
	
	/**
	 * Loop parse results and call SendToCurl
	 * 
	 * @param array $codes
	 * @return void
	 */
	protected static function Send($codes)
	{
		foreach ($codes as $code) {
			self::SendToCurl($code);
		}
	}
	
	/**
	 * Send the stats to graphite
	 * 
	 * @param integer $code
	 */
	protected static function SendToCurl($code)
	{
		$fp = fsockopen(self::$_graphiteHost, self::$_graphitePort, $errno, $errstr, self::$_graphiteTimeout);
		if (!$fp) {
			echo "$errstr ($errno)\n";
		} else {
			fwrite($fp, self::$_graphiteString . $code . " 1 " . time() . "\n");
			fclose($fp);
		}
	}
}

SmppBoxParser::Parse('./smppbox.log');