<?php

if(!defined("ABSPATH")) exit;

class LGMarkdown
{
	// standard markdown
	const MATCH_LIST	= "/^\*\s([^\n]*)/";
	const MATCH_COMMENT	= "/^>\s([^\n]*)/";
	const MATCH_PRE		= "/^\s{4}([^\n]*)/";
	const MATCH_H1		= "/^#\s([^\n]*)/";
	const MATCH_H2		= "/^#{2}\s([^\n]*)/";
	const MATCH_STRONG	= "/\*\*([^\*\n]*)\*\*/";
	const MATCH_EM		= "/\*([^\*\n]*)\*/";
	const MATCH_CODE	= "/`([^`\n]*)`/";
	
	// extended markdown for assigments
	const MATCH_RADIO	= "/^\(\*?\)\s([^\n]*)/";
	const MATCH_RADIO_C	= "/^\(\*\)\s([^\n]*)/";
	const MATCH_CHECK	= "/^\[\*?\]\s([^\n]*)/";
	const MATCH_CHECK_C	= "/^\[\*\]\s([^\n]*)/";
	const MATCH_TEXT	= "/^___(.*)___$/s";
	
	private static function parseRadioBlock($str, $expired, $questionId)
	{
		$key = "a";
		$lines = explode("\n", $str);
		foreach($lines as &$line)
		{
			if(preg_match(self::MATCH_RADIO, $line, $matches))
			{
				$id = "question-$questionId";
				$checked = isset($_POST[$id]) && $_POST[$id] == $key ? "checked" : "";
				$correct = preg_match(self::MATCH_RADIO_C, $line) ? "class=\"lgstudent-correct\"" : "";
				
				if($expired)
					$line = "<span $correct>$key) $matches[1]</span><br />";
				else
					$line = "<input type=\"radio\" name=\"$id\" value=\"$key\" $checked /> $key) $matches[1]<br />";
			}
			$key = chr(ord($key) + 1);
		}
		return "<p>" . implode("\n", $lines) . "</p>";
	}
	
	private static function parseCheckBlock($str, $expired, $questionId)
	{
		$key = "a";
		$lines = explode("\n", $str);
		foreach($lines as &$line)
		{
			if(preg_match(self::MATCH_CHECK, $line, $matches))
			{
				$id = "question-{$questionId}[]";
				$checked = (isset($_POST[$id]) && $_POST[$id] == $key) ? "checked" : "";
				$correct = preg_match(self::MATCH_CHECK_C, $line) ? "class=\"lgstudent-correct\"" : "";
				
				if($expired)
					$line = "<span $correct>$key) $matches[1]</span><br />";
				else
					$line = "<input type=\"checkbox\" name=\"$id\" value=\"$key\" $checked /> $key) $matches[1]<br />";
			}
			$key = chr(ord($key) + 1);
		}
		return "<p>" . implode("\n", $lines) . "</p>";
	}
	
	private static function parseTextarea($str, $expired, $questionId)
	{
		$id = "question-$questionId";
		preg_match(self::MATCH_TEXT, $str, $matches);
		if(!$expired)
		{
			$val = isset($_POST[$id]) ? $_POST[$id] : "";
			return "<textarea name=\"$id\">$val</textarea>";
		}
		else
		{
			$val = !empty($matches[1]) ? $matches[1] : "Open Response";
			return "<pre class=\"ignore:true\">$val</pre>";
		}
	}
	
	public static function parseBlock($str, $extended = false, $expired = false)
	{
		$output = "";
		$questionId = 1;
		
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
			else if(preg_match(self::MATCH_COMMENT, $para))
			{
				$lines = explode("\n", $para);
				foreach($lines as &$line)
					if(preg_match(self::MATCH_COMMENT, $line, $matches))
						$line = $matches[1];
				$output .= "<span class=\"lgstudent-comment\">" . implode("<br />\n", $lines) . "</span>";
			}
			else if(preg_match(self::MATCH_PRE, $para))
			{
				$lines = explode("\n", $para);
				foreach($lines as &$line)
					if(preg_match(self::MATCH_PRE, $line, $matches))
						$line = $matches[1];
				$output .= "<pre>" . implode("\n", $lines) . "</pre>";
			}
			else if(preg_match(self::MATCH_H1, $para, $matches))
				$output .= "<h1>" . ($extended ? "Question $questionId: " : "") . "$matches[1]</h1>";
			else if(preg_match(self::MATCH_H2, $para, $matches))
				$output .= "<h2>$matches[1]</h2>";
			else if($extended && preg_match(self::MATCH_RADIO, $para))
				$output .= self::parseRadioBlock($para, $expired, $questionId++);
			else if($extended && preg_match(self::MATCH_CHECK, $para))
				$output .= self::parseCheckBlock($para, $expired, $questionId++);
			else if($extended && preg_match(self::MATCH_TEXT, $para))
				$output .= self::parseTextarea($para, $expired, $questionId++);
			else
				$output .= "<p>$para</p>";
		}
		
		return $output;
	}
	
	public static function parseExtendedBlock($str, $expired = false)
	{
		return self::parseBlock($str, true, $expired);
	}
	
	public static function parseInline($str)
	{
		$str = preg_replace(self::MATCH_STRONG, "<strong>$1</strong>", $str);
		$str = preg_replace(self::MATCH_EM, "<em>$1</em>", $str);
		$str = preg_replace(self::MATCH_CODE, "<code>$1</code>", $str);
		return $str;
	}
	
	/// Regular markdown
	static function parse($str)
	{
		return self::parseInline(self::parseBlock($str));
	}
	
	/// Special markdown for assignments
	static function parseExtended($str, $expired = false)
	{
		return self::parseInline(self::parseExtendedBlock($str, $expired));
	}
}

?>
