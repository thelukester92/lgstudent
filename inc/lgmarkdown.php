<?php

if(!defined("ABSPATH")) exit;

class LGMarkdown
{
	private static function doLists($str)
	{
		$list = false;
		
		$lines = explode("\n", $str);
		foreach($lines as &$line)
		{
			if(preg_match("/^(<p>)?\*\s(.*)/", $line, $matches))
			{
				$line = "<li>$matches[2]</li>";
				if(!$list)
					$line = "<ul>$line";
				$list = true;
			}
			else if($list)
			{
				$line = "</ul>$line";
				$list = false;
			}
		}
		
		$output = implode("\n", $lines);
		if($list)
			$output = "$output</ul>";
		
		return $output;
	}
	
	private static function doComments($str)
	{
		$comment = false;
		
		$lines = explode("\n", $str);
		foreach($lines as &$line)
		{
			if(preg_match("/^(<p>)?>\s(.*)/", $line, $matches))
			{
				$line = "$matches[2]";
				if(!$comment)
					$line = "<span class=\"lgstudent-comment\">$line";
				$comment = true;
			}
			else if($comment)
			{
				$line = "</span>$line";
				$comment = false;
			}
		}
		
		$output = implode("\n", $lines);
		if($comment)
			$output = "$output</span>";
		
		return $output;
	}
	
	private static function doEmphases($str)
	{
		$lines = explode("\n", $str);
		
		foreach($lines as &$line)
		{
			$line = preg_replace("/\*\*([^\*]*)\*\*/", "<strong>$1</strong>", $line);
			$line = preg_replace("/\*([^\*]*)\*/", "<em>$1</em>", $line);
			$line = preg_replace("/`([^`]*)`/", "<code>$1</code>", $line);
		}
		
		return implode("\n", $lines);
	}
	
	/// Regular markdown
	static function parse($str)
	{
		$str = self::doLists($str);
		$str = self::doComments($str);
		$str = self::doEmphases($str);
		return $str;
	}
	
	/// Special markdown for assignments
	static function parseExtended($str)
	{
		if(preg_match("/\*\s/", $str))
		{
			$str = "[lgradio]{$str}[/lgradio]";
		}
		else if(preg_match("/\[\]\s/", $str))
		{
			$str = "[lgcheckbox]{$str}[/lgcheckbox]";
		}
		
		$str = self::doComments($str);
		$str = self::doEmphases($str);
		
		return $str;
	}
}

?>
