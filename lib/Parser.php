<?php
/**
 * SmppBox Log Parser (http://mobiletulip.com/)
 *
 * @link https://github.com/mobiletulip/php-logparser
 * @copyright Copyright (c) 2012 Mobile Tulip (http://mobiletulip.com/)
 * @license https://github.com/mobiletulip/php-logparser/license/new-bsd
 * @author Thijs Lensselink <thijs.lensselink@mobiletulip.com>
 * @package SmppBoxParser
 */

/**
 * Class to parse Kannel SMPP logs and send stats to Graphite
 *
 * @category MT
 * @package SmppBoxParser
 */
Class LogParser 
{
	/**
	 * Parser instance
	 * 
	 * @var LogParser
	 */
	private static $_instance = null;
	
	/**
	 * SimpleXmlElement config
	 * 
	 * @var SimpleXmlElement
	 */
	private $_config = null;
	
	/**
	 * State file path
	 * 
	 * @var string
	 */
	private $_stateFile = null;
	
	/**
	 * Debug switch
	 * 
	 * @var boolean
	 */
	private $_debug = false;
	
	/**
	 * Setup some basic stuff here
	 * 
	 * @param string $config
	 * @throws Exception
	 * @return void
	 */
	private function __construct($config = null) 
	{
		if (is_null($config) || !file_exists($config)) {
			throw new Exception("Could not find config file");
		}
		
		$this->_config = new SimpleXMLElement(file_get_contents($config));
		if (!$this->_config->logtail_bin || !file_exists($this->_config->logtail_bin)) {
			throw new Exception("Logtail not found ({$this->_config->logtail_bin}) or not set");
		}
		
		if ($this->_isEnabled($this->_config->debug)) {
			$this->_debug = true;
		}
		$this->_setStateFile();
	}
	
	/**
	 * Return an instance of LogParser
	 * 
	 * @param string $config
	 * @return LogParser
	 */
	public static function getInstance($config = null) 
	{
		if (is_null(self::$_instance)) {
			$configPath = dirname(__DIR__)."/config/".$config;
			self::$_instance = new self($configPath);
		}
		return self::$_instance;
	}
	
	/**
	 * Run the parser and use logtail to fetch data from the logfile
	 * 
	 * @return array
	 */
	public function run() 
	{
		$stats = array();
		if (!file_exists($this->_config->log_file)) {
			throw new Exception("Logfile not found");
		}

		$logOutput = shell_exec($this->_config->logtail_bin . " -f " . $this->_config->log_file . " -o " . $this->_stateFile);
		$outputArray = explode("\n", $logOutput);
		
		$statCounter = 0;
		foreach ($outputArray as $line) {
			preg_match_all((string) $this->_config->regexp, $line, $matches);
			if (isset($matches[0][0])) {
				$index = $matches[(integer) $this->_config->regex_num][0];
				if (!isset($stats[$index])) {
					$stats[$index] = 1;
				}
				else {
					$stats[$index]++;
				}
			}
		}
		
		if ($this->_isEnabled($this->_config->graphite->enabled)) {
			$this->_sendToGraphite($stats);
		}
		return $stats;
	}
	
	/**
	 * Create a stats file in /tmp if it does not exist
	 * 
	 * @return void
	 */
	private function _setStateFile() 
	{
		if (is_null($this->_stateFile)) {
			$this->_stateFile = $this->_config->state_file;
			if (!file_exists($this->_stateFile)) {
				touch($this->_stateFile);
			}
		}
	}
	
	/**
	 * Use sockets to send data to a graphite server
	 * 
	 * @param array $stats
	 * @throws Exception
	 * @return void
	 */
	private function _sendToGraphite($stats) 
	{
		$socket = fsockopen($this->_config->graphite->host, (integer) $this->_config->graphite->port, $errno, $errstr, (integer) $this->_config->graphite->timeout);
		if (!$socket) {
			throw new Exception($errstr, $errno);
		}
		foreach ($stats as $name => $value) {
			if ($this->_debug) {
				echo date('Y-m-d H:i:s') ." : " . $this->_config->graphite->label . $name . " {$value} " . time() . "\n";
			}
			else {
				fwrite($socket, $this->_config->graphite->label . $name . " {$value} " . time() . "\n");
			}
		}
		fclose($socket);
	}
	
	/**
	 * Becasue the config is XML. And XML consists of strings. We use thi smethod
	 * to check for boolean states
	 * 
	 * @param string $state
	 * @return boolean
	 */
	private function _isEnabled($state) 
	{
		switch ($state) {
			case 'true': return true;
			case 'false': return false;
		}
	}
}