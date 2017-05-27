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
			if(preg_match("/^\*\s(.*)/", $line, $matches))
			{
				$line = "<li>$matches[1]</li>";
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
	
	private static function doPre($str)
	{
		$pre = false;
		
		$lines = explode("\n", $str);
		foreach($lines as &$line)
		{
			if(preg_match("/^\s{4}(.*)/", $line, $matches))
			{
				$line = "$matches[1]";
				if(!$pre)
					$line = "<pre>\n$line";
				$pre = true;
			}
			else if($pre)
			{
				$line = "</pre>\n$line";
				$pre = false;
			}
		}
		
		$output = implode("\n", $lines);
		if($pre)
			$output = "$output\n</pre>";
		
		return $output;
	}
	
	private static function doComments($str)
	{
		$comment = false;
		
		$lines = explode("\n", $str);
		foreach($lines as &$line)
		{
			if(preg_match("/^>\s(.*)/", $line, $matches))
			{
				$line = "$matches[1]";
				if(!$comment)
					$line = "<span class=\"lgstudent-comment\">\n$line";
				$comment = true;
			}
			else if($comment)
			{
				$line = "</span>\n$line";
				$comment = false;
			}
		}
		
		$output = implode("\n", $lines);
		if($comment)
			$output = "$output\n</span>";
		
		return $output;
	}
	
	public static function doEmphases($str)
	{
		$lines = explode("\n", $str);
		
		foreach($lines as &$line)
		{
			$line = preg_replace("/\*\*([^\*\n]+)\*\*/", "<strong>$1</strong>", $line);
			$line = preg_replace("/\*([^\*\n]+)\*/", "<em>$1</em>", $line);
			$line = preg_replace("/`([^`\n]+)`/", "<code>$1</code>", $line);
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
		$str = strip_tags($str);
		
		if(preg_match("/\*\s/", $str))
		{
			$str = "[lgradio]\n{$str}\n[/lgradio]";
		}
		else if(preg_match("/\[\]\s/", $str))
		{
			$str = "[lgcheckbox]\n{$str}\n[/lgcheckbox]";
		}
		
		$str = self::doPre($str);
		$str = self::doComments($str);
		$str = self::doEmphases($str);
		
		return $str;
	}
}

?>
