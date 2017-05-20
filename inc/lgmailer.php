<?php

if(!defined("ABSPATH")) exit;

require_once "lgutil.php";

class LGMailer
{
	function __construct($to = null, $subject = "", $message = "")
	{
		$admin					= LGUtil::adminData();
		$this->m_from			= "$admin->first_name $admin->last_name <$admin->user_email>";
		$this->m_to				= is_array($to) ? $to : (is_null($to) ? array() : array($to));
		$this->m_cc				= array();
		$this->m_bcc			= array();
		$this->m_subject		= $subject;
		$this->m_message		= $message;
		$this->m_attachments	= array();
	}
	
	function __destruct()
	{
		foreach($this->m_attachments as $attachment)
		{
			unlink($attachment);
		}
	}
	
	static function mailer($to = null, $subject = "", $message = "")
	{
		return new LGMailer($to, $subject, $message);
	}
	
	function to($to, $append = true)
	{
		if(is_array($to))
			$this->m_to = $append ? array_merge($this->m_to, $to) : $to;
		else if($append)
			$this->m_to[] = $to;
		else
			$this->m_to = array($to);
		return $this;
	}
	
	function copyToSender()
	{
		return $this->to($this->m_from);
	}
	
	function cc($cc, $append = true)
	{
		if(is_array($cc))
			$this->m_cc = $append ? array_merge($this->m_cc, $cc) : $cc;
		else if($append)
			$this->m_cc[] = $cc;
		else
			$this->m_cc = array($cc);
		return $this;
	}
	
	function bcc($bcc, $append = true)
	{
		if(is_array($bcc))
			$this->m_bcc = $append ? array_merge($this->m_bcc, $bcc) : $bcc;
		else if($append)
			$this->m_bcc[] = $bcc;
		else
			$this->m_bcc = array($bcc);
		return $this;
	}
	
	function subject($subject)
	{
		$this->m_subject = $subject;
		return $this;
	}
	
	function message($message)
	{
		$this->m_message = $message;
		return $this;
	}
	
	function attachUploadedFiles()
	{
		if(!empty($this->m_attachments))
			return $this;
		
		if(!empty($_FILES) && !empty($_FILES["file"]) && is_array($_FILES["file"]["tmp_name"]))
		{
			for($i = 0; $i < count($_FILES["file"]["tmp_name"]); $i++)
			{
				$dir = wp_upload_dir()["path"];
				
				$filename = "$dir/" . basename($_FILES["file"]["name"][$i]);
				$id = 1;
				
				while(file_exists($filename))
				{
					$filename = "$dir/$id-" . basename($_FILES["file"]["name"][$i]);
					++$id;
				}
				
				if(move_uploaded_file($_FILES["file"]["tmp_name"][$i], $filename))
				{
					$this->m_attachments[] = $filename;
				}
			}
		}
		
		return $this;
	}
	
	function send()
	{
		$to = !empty($this->m_to) ? $this->m_to : array($this->m_from);
		
		$headers	= array();
		$headers[]	= "From: $this->m_from";
		$headers[]	= "Reply-To: $this->m_from";
		$headers[]	= "MIME-Version: 1.0";
		$headers[]	= "Content-type: text/html; charset=UTF-8";
		if(!empty($this->m_cc))
			$headers[] = "CC: " . implode(",", $this->m_cc);
		if(!empty($this->m_bcc))
			$headers[] = "BCC: " . implode(",", $this->m_bcc);
		
		$subject = get_bloginfo("name") . (!empty($this->m_subject) ? " - $this->m_subject" : "");
		$message = (!empty($this->m_message) ? $this->m_message : "(Empty Message)") . "<br />";
		
		return wp_mail($to, $subject, $message, $headers, $this->m_attachments);
	}
	
	private $m_from;
	private $m_to;
	private $m_cc;
	private $m_bcc;
	private $m_subject;
	private $m_message;
	private $m_attachments;
}

?>
