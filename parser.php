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
	 * @var unknown_type
	 */
	private static $_logtailBin = '/usr/sbin/logtail2';

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
				preg_match_all('/^(\S+) (\S+) \[(\S+)\] \[(\S+)\] ERROR: \[.* (?P<code>\d{1,3}): .*\]: /', $line, $matches);
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
		$fp = fsockopen("localhost", 2023, $errno, $errstr, 30);
		if (!$fp) {
			echo "$errstr ($errno)<br />\n";
		} else {
			fwrite($fp, "server.dc1-sms-1.smppbox.errors.status 1");
			fclose($fp);
		}
	}
}

SmppBoxParser::Parse('./smppbox.log');