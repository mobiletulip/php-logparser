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
Class LogParser 
{
	protected static $_instance = null;
	protected $_config = null;
	protected $_stateFile = null;
	
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
	
	public static function getInstance($config = null) 
	{
		if (is_null(self::$_instance)) {
			self::$_instance = new self($config);
		}
		return self::$_instance;
	}
	
	public function run() 
	{
		$stats = array();
		if (file_exists($this->_config->log_file)) {
			$logOutput = shell_exec($this->_config->logtail_bin . " -f " . $this->_config->log_file . " -o " . $this->_stateFile);
			$outputArray = explode("\n", $logOutput);
			
			foreach ($outputArray as $line) {
				preg_match_all((string) $this->_config->regexp, $line, $matches);
				if (isset($matches[0][0])) {
					if ($this->_config->regexp_match == $this->_config->regexp_store) {
						$stats[] = $matches[(integer) $this->_config->regexp_match][0];
					}
					else {
						$stats[] = $matches[(integer) $this->_config->regexp_store][0];
					}
				}
			}
		}

		if ($this->_isEnabled($this->_config->graphite->enabled)) {
			$this->_sendToGraphite($stats);
		}
		return $stats;
	}
	
	private function _setStateFile() 
	{
		if (is_null($this->_stateFile)) {
			$this->_stateFile = "/tmp/{$this->_config->name}.state";
			if (!file_exists($this->_stateFile)) {
				touch($this->_stateFile);
			}
		}
	}
	
	private function _sendToGraphite($stats) 
	{
		$socket = fsockopen($this->_config->graphite->host, (integer) $this->_config->graphite->port, $errno, $errstr, (integer) $this->_config->graphite->timeout);
		if (!$socket) {
			throw new Exception($errstr, $errno);
		}
		foreach ($stats as $row) {
			fwrite($socket, $this->_config->graphite->label . $row . " {$this->_config->graphite->increment} " . time() . "\n");
		}
		fclose($socket);
	}
	
	private function _isEnabled($state) 
	{
		switch ($state) {
			case 'true': return true;
			case 'false': return false;
		}
	}
}