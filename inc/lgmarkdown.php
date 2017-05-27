<?php

if(!defined("ABSPATH")) exit;

class LGMarkdown
{
	const MATCH_LIST = "/^\*\s([^\n]*)/";
	
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
					$line = "<pre>$line";
				$pre = true;
			}
			else if($pre)
			{
				$line = "</pre>$line";
				$pre = false;
			}
		}
		
		$output = implode("\n", $lines);
		if($pre)
			$output = "$output</pre>";
		
		return preg_replace("/\s+<\/pre>/", "</pre>", $output);
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
		// $str = self::doLists($str);
		// $str = self::doComments($str);
		// $str = self::doEmphases($str);
		
		// $str = self::doBlock($str);
		// $str = self::doInline($str);
		
		$output = "";
		
		$paras = explode("\n\n", str_replace("\r", "", $str));
		foreach($paras as $para)
		{
			if(preg_match(self::MATCH_LIST, $para))
			{
				$lines = explode("\n", $para);
				foreach($lines as &$line)
					if(preg_match(self::MATCH_LIST, $line, $matches))
						$line = "<li>$matches[1]</li>";
				$output .= "<ul>" . implode("\n", $lines) . "</ul>";
			}
			else
				$output .= "<p>$para</p>";
		}
		
		return $output;
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
