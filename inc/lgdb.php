<?php

if(!defined("ABSPATH")) exit;

require_once "lgmailer.php";

class LGDB
{
	static function createTables()
	{
		global $wpdb;
		
		$students		= "{$wpdb->prefix}lgstudent_students";
		$assignments	= "{$wpdb->prefix}lgstudent_assignments";
		$grades			= "{$wpdb->prefix}lgstudent_grades";
		$charset		= $wpdb->get_charset_collate();
		
		$createStudents =
		"
		CREATE TABLE $students (
			email VARCHAR(100) NOT NULL,
			password VARCHAR(100) NOT NULL,
			firstName VARCHAR(100),
			lastName VARCHAR(100),
			preferredName VARCHAR(100),
			UNIQUE KEY email (email)
		) $charset
		";
		
		$createGrades =
		"
		CREATE TABLE $grades (
			user VARCHAR(100) NOT NULL,
			assignment VARCHAR(100) NOT NULL,
			grade INT(12) NOT NULL,
			UNIQUE KEY pkey (user,assignment)
		) $charset
		";
		
		require_once(ABSPATH . "wp-admin/includes/upgrade.php");
		dbDelta($createStudents);
		dbDelta($createAssignments);
		dbDelta($createGrades);
	}
	
	function checkPassword($email, $pass)
	{
		global $wpdb;
		$user = $wpdb->get_row($wpdb->prepare("SELECT password FROM {$wpdb->prefix}lgstudent_students WHERE email = %s", $email));
		if($user == NULL)
			return false;
		else
			return password_verify($pass, $user->password);
	}
	
	function setPassword($email, $pass)
	{
		global $wpdb;
		return $wpdb->update("{$wpdb->prefix}lgstudent_students", array("password" => password_hash($pass, PASSWORD_DEFAULT)), array("email" => $email)) !== false;
	}
	
	function resetPassword($email)
	{
		$pass = substr(md5(time()), 0, 10);
		if($this->setPassword($email, $pass))
		{
			LGMailer::mailer()
				->to($email)
				->subject("Reset Password")
				->message("Your password has been reset to $pass.")
				->send();
			return true;
		}
		return false;
	}
	
	function getAllStudents()
	{
		global $wpdb;
		return $wpdb->get_results("SELECT email, firstName, lastName FROM {$wpdb->prefix}lgstudent_students ORDER BY lastName");
	}
	
	function getStudentByEmail($email)
	{
		global $wpdb;
		return $wpdb->get_row($wpdb->prepare("SELECT email, firstName, lastName FROM {$wpdb->prefix}lgstudent_students WHERE email = %s", $email));
	}
	
	function getStudentByHashedEmail($email)
	{
		global $wpdb;
		return $wpdb->get_row($wpdb->prepare("SELECT email FROM {$wpdb->prefix}lgstudent_students WHERE MD5(email) = %s", $email));
	}
	
	function addStudent($email, $fname, $lname)
	{
		global $wpdb;
		
		$insert = $wpdb->insert("{$wpdb->prefix}lgstudent_students", array
		(
			"email"		=> $email,
			"password"	=> password_hash(substr(md5(time()), 0, 10), PASSWORD_DEFAULT),
			"firstName"	=> $fname,
			"lastName"	=> $lname
		));
		
		return $insert !== false;
	}
	
	function deleteStudent($email)
	{
		global $wpdb;
		
		$result = $wpdb->delete("{$wpdb->prefix}lgstudent_students", array("email" => $email));
		if($result !== false)
			$result = $wpdb->delete("{$wpdb->prefix}lgstudent_grades", array("user" => $email));
		
		return $result !== false;
	}
}

?>
