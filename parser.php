<?php
Class SmppBoxParser 
{
	public static function Parse($file) 
	{
		$codes = array();
		$cursor = self::GetCursor();

		if (file_exists($file)) {
			$handle = fopen($file, 'rb');
			if ($handle) {
                fseek($handle, $cursor, SEEK_SET);
				while (!feof($handle)) {
					$buffer = fgets($handle);
					$matches = array();
					preg_match_all('/^(\S+) (\S+) \[(\S+)\] \[(\S+)\] ERROR: \[.* (?P<code>\d{1,3}): .*\]: /', $buffer, $matches);
					if (isset($matches['code'][0])) {
						$codes[] = $matches['code'][0];
					}
				}
                $line = ftell($handle);
				fclose($handle);
			}
			self::Send($codes);
			self::SaveCursor($line);
		}
	}
	
	protected static function Send($codes)
	{
		foreach ($codes as $code) {
			self::SendToCurl($code);
		}
	}
	
	protected static function SendToCurl($code)
	{
		$fp = fsockopen("localhost", 2003, $errno, $errstr, 30);
		if (!$fp) {
			echo "$errstr ($errno)<br />\n";
		} else {
            echo "carbon.installation.test_{$code} 1 " . time() . "\n";
			fwrite($fp, "carbon.installation.test_{$code} 1 " . time() . "\n");
		}
		fclose($fp);
	}
	
	protected static function SaveCursor($num) 
	{
		$fp = fopen('./cursor', 'w+');
		fwrite($fp, $num);
		fclose($fp);
	}
	
	protected static function GetCursor()
	{
        $file = './cursor';
        if (!file_exists($file)) {
            return 0;
        }
		$data = file_get_contents('./cursor');
		return (strlen($data) == 0) ? 0 : $data;
	}
}

SmppBoxParser::Parse('./smppbox.log');
