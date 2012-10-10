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
	protected static $_instance = null;
	
	/**
	 * SimpleXmlElement config
	 * 
	 * @var SimpleXmlElement
	 */
	protected $_config = null;
	
	/**
	 * State file path
	 * 
	 * @var string
	 */
	protected $_stateFile = null;
	
	/**
	 * Setup some basic stuff here
	 * 
	 * @param string $config
	 * @throws Exception
	 * @return void
	 */
	protected function __construct($config = null) 
	{
		if (is_null($config)) {
			throw new Exception("No config file supplied");
		}
		
		if (!file_exists($config)) {
			throw new Exception("Config file not found");
		}
		
		$this->_config = new SimpleXMLElement(file_get_contents($config));
		if (!$this->_config->logtail_bin || !file_exists($this->_config->logtail_bin)) {
			throw new Exception("Logtail not found ({$this->_config->logtail_bin}) or not set");
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
		
		foreach ($outputArray as $line) {
			preg_match_all((string) $this->_config->regexp, $line, $matches);
			if (isset($matches[0][0])) {
				$stats[] = array(
					'stat' => $matches[(integer) $this->_config->regex_num][0],
					'time' => $matches[1][0] . " " . $matches[2][0]
				);
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
		foreach ($stats as $row) {
			$timestamp = strtotime($row['time']);
			if ($timestamp === false) {
				$timestamp = time();
			}
			fwrite($socket, $this->_config->graphite->label . $row['stat'] . " {$this->_config->graphite->increment} " . $timestamp . "\n");
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