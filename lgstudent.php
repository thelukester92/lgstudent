<?php

/*
 * Plugin Name:	LG Student
 * Plugin URI:	http://www.lukesterwebdesign.com/
 * Description:	This plugin includes a simple way to manage grades.
 * Version:		1.0.0
 * Author:		Luke Godfrey
 * Author URI:	http://www.lukesterwebdesign.com
 */

if(!defined("ABSPATH")) exit;

require_once "inc/lgdb.php";
require_once "inc/lgmailer.php";
require_once "inc/lgmarkdown.php";
require_once "inc/lgutil.php";

class LGStudent
{
	static function activate()
	{
		LDGB::createTables();
		$p = new LGStudent();
		$p->init();
		flush_rewrite_rules();
	}
	
	private $currentId = 1;
	private $currentExp;
	private $db;
	
	function __construct()
	{
		$this->db = new LGDB();
		
		$actions = array(
			"init",
			"plugins_loaded",
			array("save_post", 10, 2),
			"wp_head",
			"admin_head",
			"add_meta_boxes_post",
			"add_meta_boxes_lgstudent_assignment",
			array("admin_menu", 9)
		);
		
		$shortcode_prefix = "lg";
		$shortcodes = array(
			"grades",
			"form",
			"textarea",
			"radio",
			"checkbox",
			"file"
		);
		
		foreach($actions as $action)
		{
			if(is_array($action))
			{
				if(count($action) == 2)
					add_action($action[0], array(&$this, $action[0]), $action[1]);
				else
					add_action($action[0], array(&$this, $action[0]), $action[1], $action[2]);
			}
			else
				add_action($action, array(&$this, $action));
		}
		
		foreach($shortcodes as $shortcode)
		{
			add_shortcode("${shortcode_prefix}${shortcode}", array(&$this, "shortcode_$shortcode"));
		}
	}
	
	// MARK: Shortcodes
	
	function shortcode_grades()
	{
		global $wpdb;
		
		$login = false;
		$message = false;
		$error = false;
		$die = false;
		
		if(isset($_POST["email"], $_POST["pass"]))
		{
			if($this->db->checkPassword($_POST["email"], $_POST["pass"]))
				$login = true;
			else
				$error = "Login Failed!";
		}
		
		if(isset($_POST["action"]) && $_POST["action"] == "reset_password" && filter_var($_POST["email"], FILTER_VALIDATE_EMAIL))
		{
			$user = $this->db->getStudentByEmail($_POST["email"]);
			if($user !== NULL)
			{
				LGMailer::mailer()
					->to($user->email)
					->subject("Reset Password")
					->message("Please visit the following link to finish resetting your password:\n\nhttp://$_SERVER[HTTP_HOST]" . strtok($_SERVER["REQUEST_URI"], "?") . "?reset_password=" . md5($_POST["email"]))
					->send();
			}
			$message = "Please check your email to finish resetting your password.";
			$die = true;
		}
		
		if(isset($_GET["reset_password"]))
		{
			$user = $this->db->getStudentByHashedEmail($_GET["reset_password"]);
			if($user !== NULL)
				$this->db->resetPassword($user->email);
			$message = "Your password has been reset. Please check your email for your new password.";
			$die = true;
		}
		
		if($login && isset($_POST["new_pass"]))
		{
			$this->db->setPassword($_POST["email"], $_POST["new_pass"]);
			$message = "Your new password has been saved.";
		}
		
		ob_start();
		?>
		
		<?php if($error) : ?>
			<span class="lgstudent-error"><?=$error?></span>
		<?php endif; ?>
		
		<?php if($message) : ?>
			<span class="lgstudent-message"><?=$message?></span>
		<?php endif; ?>
		
		<?php if($die) return ob_get_clean(); ?>
		
		<?php if($login) : ?>
			<?php
			$assignments = get_posts(array(
				"post_type"			=> "lgstudent_assignment",
				"meta_key"			=> "lgstudent_assignment_type",
				"orderby"			=> "meta_value",
				"order"				=> "ASC",
				"posts_per_page"	=> -1
			));
			$grades = array();
			$counts = array();
			$averages = array("homework" => NULL, "quiz" => NULL, "exam" => NULL, "lab" => NULL, "bonus" => 0);
			$weights = array("homework" => 0.4, "quiz" => 0.1, "exam" => 0.4, "lab" => 0.1, "bonus" => 0.08);
			?>
			<form method="post" action="">
				<div class="lgstudent-field">
					<label>Current Password</label><br />
					<input type="password" name="pass" />
				</div>
				<div class="lgstudent-field">
					<label>New Password</label><br />
					<input type="password" name="new_pass" />
				</div>
				<div class="lgstudent-field">
					<label>&nbsp;</label><br />
					<input type="hidden" name="email" value="<?=$_POST["email"]?>" />
					<input type="submit" value="Change" />
				</div>
			</form>
			<div style="clear: both;"></div><br />
			
			<div class="lgstudent-field">
				<label>Grades</label><br />
				<table class="lgstudent-table">
					<tr>
						<th>Assignment</th>
						<th>Grade</th>
					</tr>
					<?php foreach($assignments as $assignment)
					{
						$grade = $wpdb->get_row("SELECT grade FROM {$wpdb->prefix}lgstudent_grades WHERE user = '$_POST[email]' AND assignment = '$assignment->post_name'");
						if($grade === NULL)
						{
							$grade = "-";
						}
						else
						{
							$grade = intval($grade->grade);
							$type = get_post_meta($assignment->ID, "lgstudent_assignment_type", true);
							$grades[$type] = isset($grades[$type]) ? $grades[$type] + $grade : $grade;
							$counts[$type] = isset($counts[$type]) ? $counts[$type] + 1 : 1;
						}
						?>
						<tr>
							<td><?=$assignment->post_title?></td>
							<td><?=($grade == "-" ? $grade : "$grade%")?></td>
						</tr>
					<?php
					}
					foreach($grades as $type => $sum)
					{
						$averages[$type] = $weights[$type] * ($sum / $counts[$type]);
					}
					$total = 0;
					$refund = 1;
					if($averages["homework"] !== NULL)	$total += $averages["homework"];	else $refund -= $weights["homework"];
					if($averages["exam"] !== NULL)		$total += $averages["exam"];		else $refund -= $weights["exam"];
					if($averages["quiz"] !== NULL)		$total += $averages["quiz"];		else $refund -= $weights["quiz"];
					if($averages["lab"] !== NULL)		$total += $averages["lab"];			else $refund -= $weights["lab"];
					if($refund > 0)
						$total /= $refund;
					$total += $averages["bonus"];
					?>
					<tr>
						<td><strong>Weighted Total</strong></td>
						<td><strong><?=$total?>%</strong></td>
					</tr>
				</table>
			</div>
			<div style="clear: both;"></div>
		<?php elseif(isset($_GET["forgot_password"])) : ?>
			<p>Enter your email address to reset your password.</p>
			<form method="post" action="">
				<input type="hidden" name="action" value="reset_password" />
				<div class="lgstudent-field">
					<label for="email">Email</label><br />
					<input type="text" name="email" />
				</div>
				<div class="lgstudent-field">
					<label>&nbsp;</label><br />
					<input type="submit" value="Reset Password" />
				</div>
				<div style="clear: both"></div>
			</form>
		<?php else : ?>
			<form method="post" action="">
				<div class="lgstudent-field">
					<label for="email">Email</label><br />
					<input type="text" name="email" />
				</div>
				
				<div class="lgstudent-field">
					<label for="pass">Password</label><br />
					<input type="password" name="pass" />
				</div>
				
				<div class="lgstudent-field">
					<label>&nbsp;</label><br />
					<input type="submit" value="View Grades" />
				</div>
				
				<div style="clear: both"></div>
			</form>
			
			<a href="<?=strtok($_SERVER["REQUEST_URI"], "?")?>?forgot_password">Forgot password?</a>
		<?php endif;
		
		return ob_get_clean();
	}
	
	function shortcode_form($atts, $content = null)
	{
		global $post;
		global $wpdb;
		ob_start();
		
		$meta	= get_post_custom($post->ID);
		$exp	= isset($meta["lgstudent_assignment_expdate"]) ? date("D Y-m-d H:i:s", $meta["lgstudent_assignment_expdate"][0]) : "";
		
		$this->currentExp = false;
		if(!empty($exp))
		{
			if(current_time("timestamp") > strtotime($exp)) : $this->currentExp = true; ?>
				<strong style="display: block;">No longer accepting submissions; was due <?=date('l, F j, Y \a\t g:ia', $meta["lgstudent_assignment_expdate"][0])?></strong><br />
			<?php else : ?>
				<strong style="display: block;">Due <?=date('l, F j, Y \a\t g:ia', $meta["lgstudent_assignment_expdate"][0])?></strong><br />
			<?php endif;
		}
		
		if(!$this->currentExp && isset($_POST["email"], $_POST["pass"]))
		{
			if($this->db->checkPassword($_POST["email"], $_POST["pass"]))
			{
				$user = $this->db->getStudentByEmail($_POST["email"]);
				$student_email = "$user->firstName $user->lastName <$user->email>";
				
				$submission = "";
				foreach($_POST as $key => $val)
				{
					if(is_array($val))
						$val = implode(",", $val);
					
					if($key != "email" && $key != "pass")
					{
						$submission .= "$key\n$val\n\n";
					}
				}
				
				$success = LGMailer::mailer()
					->cc($student_email)
					->subject("$post->post_title Submission for $student_email")
					->message($submission)
					->attachUploadedFiles()
					->send();
				
				$_POST = array();
				if($success) : ?>
					<span class="lgstudent-message">Your assignment has been submitted!</span><br />
				<?php else : ?>
					<span class="lgstudent-error">Something went wrong! Your assignment has NOT been submitted!</span><br />
				<?php endif;
			}
			else
			{
				?><span class="lgstudent-error">Incorrect email or password! Your assignment has NOT been submitted!</span><br /><?php
			}
		}
		
		if(!$this->currentExp) : ?>
			<form class="lgstudent-form" method="post" enctype="multipart/form-data" action="">
		<?php else : ?>
			<div class="lgstudent-form">
		<?php endif;
		
		$questions = explode("# ", $content);
		$content = "";
		
		$n = count($questions);
		for($i = 0; $i < $n; ++$i)
		{
			$lines		= explode("\n", $questions[$i]);
			$question	= array_shift($lines);
			/* $lines		= array_map(function($line)
			{
				return preg_replace("/^(<p>)?>\s([^\n]+)/", '<span class="lgstudent-comment">$2</span>', $line);
			}, $lines); */
			
			$inside		= implode("\n", $lines);
			$inside		= preg_replace("/\n\n+/", "\n", $inside);
			
			if($i > 0)
			{
				$inside = LGMarkdown::parseExtended($inside);
				if($n > 2)
					$content .= "<h2>Question $i: $question</h2>$inside";
				else
					$content .= "<h2>$question</h2>$inside";
			}
			else
			{
				$content .= LGMarkdown::parse($inside);
			}
		}
		?>
		
		<?=do_shortcode($content)?>
		
		<?php
		if(!$this->currentExp) :
		?>
			<div class="lgstudent-field">
				<label for="email">Email</label><br />
				<input type="text" name="email" />
			</div>
			
			<div class="lgstudent-field">
				<label for="pass">Password</label><br />
				<input type="password" name="pass" />
			</div>
			
			<div class="lgstudent-field">
				<label>&nbsp;</label><br />
				<input type="submit" value="Submit" />
			</div>
		</form>
		<?php else : ?>
		</div>
		<?php endif;
		
		return ob_get_clean();
	}
	
	function shortcode_textarea($atts, $content = null)
	{
		ob_start();
		$a = shortcode_atts(array(
			"class" => "",
			"pre-class" => "ignore:true"
		), $atts);
		?>
		<div class="lgstudent-assignment-field">
			<br />
			<?php if(!$this->currentExp) : ?>
				<textarea name="question<?=$this->currentId?>" class="<?=$a["class"]?>"><?=$_POST["question{$this->currentId}"]?></textarea>
			<?php else : ?>
				<?=CrayonWP::highlight("<pre class='" . $a["pre-class"] . "'>" . preg_replace("/<br\s?\/?>/", "", !empty($content) ? $content : "Open response") . "</pre>")?>
			<?php endif; ?>
		</div>
		<div style="clear: both;"></div><br />
		<?php
		$this->currentId++;
		return ob_get_clean();
	}
	
	function shortcode_radio($atts, $content = null)
	{
		ob_start();
		$lines = array_values(array_filter(explode("\n", strip_tags($content, "<code><span>")), function($line) { return trim($line) != false; }));
		?>
		<div class="lgstudent-assignment-field">
			<?php
			$i = 0;
			foreach($lines as $line)
			{
				$correct = false;
				if(preg_match("/^\*\*\s/", trim($line)))
				{
					$correct = true;
					$line = substr(strstr(trim($line), "*"), 1);
				}
				
				if(preg_match("/^\*\s/", trim($line)))
				{
					$val = substr(strstr($line, "*"), 2);
					$key = chr(ord('a') + $i); 
					
					if(!$this->currentExp) : ?>
						<input type="radio" name="question<?=$this->currentId?>" value="<?=$key?>" <?=(isset($_POST["question{$this->currentId}"]) && $_POST["question{$this->currentId}"] == $key ? "checked" : "")?> /> <?=$key?>) <?=$val?><br />
					<?php elseif($correct) : ?>
						<span class="lgstudent-correct"><?=$key?>) <?=$val?></span><br />
					<?php else : ?>
						<?=$key?>) <?=$val?><br />
					<?php endif;
					
					$i++;
				}
				else
				{
					echo $line;
				}
			}
			?>
		</div><br />
		<?php
		$this->currentId++;
		return ob_get_clean();
	}
	
	function shortcode_checkbox($atts, $content = null)
	{
		ob_start();
		$lines = array_values(array_filter(explode("\n", strip_tags($content, "<code><span>")), function($line) { return trim($line) != false; }));
		?>
		<div class="lgstudent-assignment-field">
			<?php
			$i = 0;
			foreach($lines as $line)
			{
				$correct = false;
				if(preg_match("/^\[\*\]\s/", trim($line)))
				{
					$correct = true;
					$line = "[]" . substr(strstr(trim($line), "[*]"), 3);
				}
				
				if(preg_match("/^\[\]\s/", trim($line)))
				{
					$val = substr(strstr($line, "[]"), 2);
					$key = chr(ord('a') + $i);
					
					if(!$this->currentExp) : ?>
						<input type="checkbox" name="question<?=$this->currentId?>[]" value="<?=$key?>" <?=(isset($_POST["question{$this->currentId}"]) && in_array($key, $_POST["question{$this->currentId}"]) ? "checked" : "")?> /> <?=$key?>) <?=$val?><br />
					<?php elseif($correct) : ?>
						<span class="lgstudent-correct"><?=$key?>) <?=$val?></span><br />
					<?php else : ?>
						<?=$key?>) <?=$val?><br />
					<?php endif;
					
					$i++;
				}
				else
				{
					echo $line;
				}
			}
			?>
		</div><br />
		<?php
		$this->currentId++;
		return ob_get_clean();
	}
	
	function shortcode_file()
	{
		ob_start();
		if(!$this->currentExp) : ?>
			<div class="lgstudent-assignment-field">
				<input type="file" name="file[]" />
			</div><br />
		<?php endif;
		return ob_get_clean();
	}
	
	// MARK: Wordpress actions
	
	function init()
	{
		$name	= "Assignment";
		$lcname	= "assignment";
		register_post_type("lgstudent_assignment", array
		(
			"labels"			=> array
			(
				"name"					=> "{$name}s",
				"singular_name"			=> "{$name}",
				"all_items"				=> "{$name}s",
				"add_new"				=> "Add New",
				"add_new_item"			=> "Add New {$name}",
				"name_admin_bar"		=> "{$name}",
				"new_item"				=> "New {$name}",
				"edit_item"				=> "Edit {$name}",
				"view_item"				=> "View {$name}",
				"search_items"			=> "Search {$name}s",
				"not_found"				=> "No {$lcname}s found",
				"not_found_in_trash"	=> "No {$lcname}s found in Trash"
			),
			"public"			=> true,
			"has_archive"		=> true,
			"rewrite"			=> array("slug" => "{$lcname}"),
			"show_in_menu"		=> "lgstudent_grades"
		));
	}
	
	function plugins_loaded()
	{
		if(current_user_can("edit_posts") && isset($_POST["action"]) && $_POST["action"] == "download")
		{
			global $wpdb;
			
			header("Content-type: text/csv");
			header("Content-disposition: attachment; filename=grades.csv");
			
			$students = $this->db->getAllStudents();
			$assignments = get_posts(array(
				"post_type"			=> "lgstudent_assignment",
				"meta_key"			=> "lgstudent_assignment_type",
				"orderby"			=> "meta_value",
				"posts_per_page"	=> -1
			));
			
			echo "Last Name,First Name,Email";
			foreach($assignments as $assignment)
				echo ",$assignment->post_name";
			echo "\n";
			foreach($students as $student)
			{
				echo "$student->lastName,$student->firstName,$student->email";
				foreach($assignments as $assignment)
				{
					$grade = $wpdb->get_row("SELECT grade FROM {$wpdb->prefix}lgstudent_grades WHERE user = '$student->email' AND assignment = '$assignment->post_name'");
					$grade = $grade === NULL ? "" : "$grade->grade";
					echo ",$grade";
				}
				echo "\n";
			}
			
			die();
		}
	}
	
	function save_post($post_id, $post)
	{
		global $wpdb;
		
		if(!is_null($post) && $post->post_type == "lgstudent_assignment" && current_user_can("edit_posts", $post->ID))
		{
			update_post_meta($post->ID, "lgstudent_assignment_type", sanitize_text_field($_POST["lgstudent_assignment_type"]));
			update_post_meta($post->ID, "lgstudent_assignment_expdate", !empty($_POST["lgstudent_assignment_expdate"]) ? strtotime($_POST["lgstudent_assignment_expdate"]) : "");
		}
		
		if(isset($_POST["notify"]))
		{
			$author = LGUtil::adminData();
			
			$message  = apply_filters("the_content", $post->post_content);
			$message .= "Sincerely,<br /><br />";
			$message .= "$author->first_name $author->last_name";
			
			$mailer = new LGMailer();
			
			$students = $this->db->getAllStudents();
			foreach($students as $student)
			{
				$mailer->bcc("$student->firstName $student->lastName <$student->email>");
			}
			
			$mailer
				->subject("Announcement: $post->post_title")
				->message($message)
				->send();
			
			unset($_POST["notify"]);
		}
	}
	
	function wp_head()
	{
		?>
		<style type="text/css">
			.lgstudent-field
			{
				float: left;
				margin-right: 10px;
			}
			.lgstudent-field input[type="text"]
			{
				width: 200px;
			}
			.lgstudent-assignment-field
			{
				width: 100%;
			}
			.lgstudent-field label, .lgstudent-assignment-field label
			{
				margin-left: 2px;
			}
			.lgstudent-field textarea, .lgstudent-assignment-field textarea
			{
				width: 100%;
				height: 100px !important;
			}
			.lgstudent-assignment-field .code
			{
				font-family: monospace;
			}
			.lgstudent-message, .lgstudent-error
			{
				display: block;
				font-weight: bold;
			}
			.lgstudent-message
			{
				color: #0c0;
			}
			.lgstudent-error
			{
				color: #c00;
			}
			.lgstudent-table
			{
				border-collapse: collapse;
			}
			.lgstudent-table th, .lgstudent-table td
			{
				text-align: left;
				border: 1px solid #000;
				padding: 5px;
			}
			.lgstudent-comment
			{
				display:		block;
				font-size:		small;
				padding-left:	10px;
				border-left:	3px solid #666;
				color:			#666;
			}
			.lgstudent-form h2
			{
				font-size:		1.2em;
				margin:			0;
				padding:		10px 0 0 0;
				border-top:		3px solid #666;
			}
			.lgstudent-form code
			{
				background:		none;
				color:			#484;
			}
			.lgstudent-correct
			{
				font-weight:	bold;
				background:		#ffff00;
			}
		</style>
		<?php
	}
	
	function admin_head()
	{
		$this->wp_head();
	}
	
	function add_meta_boxes_post()
	{
		add_meta_box("lgstudent_notify", "Notify Students", array(&$this, "add_meta_boxes_post_callback"), "post");
	}
	
	function add_meta_boxes_post_callback()
	{
		?><input type="checkbox" name="notify" /> Notify Students
		<?php
	}
	
	function add_meta_boxes_lgstudent_assignment()
	{
		add_meta_box("lgstudent_assignment_type", "Assignment Info", array(&$this, "add_meta_boxes_lgstudent_assignment_callback"), "lgstudent_assignment", "normal", "high");
	}
	
	function add_meta_boxes_lgstudent_assignment_callback()
	{
		global $post, $wpdb;
		$meta		= get_post_custom($post->ID);
		$val		= isset($meta["lgstudent_assignment_type"]) ? $meta["lgstudent_assignment_type"][0] : "";
		$exp		= (isset($meta["lgstudent_assignment_expdate"]) && !empty($meta["lgstudent_assignment_expdate"][0])) ? date("D Y-m-d H:i:s", intval($meta["lgstudent_assignment_expdate"][0])) : "";
		$options	= array("lab" => "Lab","quiz" => "Quiz", "exam" => "Exam", "homework" => "Homework", "bonus" => "Bonus");
		$students	= $this->db->getAllStudents();
		?>
		
		<div class="lgstudent-field">
			<label for="lgstudent_assignment_type">Type</label><br />
			<select name="lgstudent_assignment_type">
			<?php foreach($options as $opt => $name) : ?>
				<option value="<?=$opt?>"<?=($opt == $val ? " selected" : "")?>><?=$name?></option>
			<?php endforeach; ?>
			</select>
		</div>
		<div style="clear: both;"</div><br />
		
		<div class="lgstudent-field">
			<label for="lgstudent_assignment_expdate">Due Date</label><br />
			<input type="text" name="lgstudent_assignment_expdate" value="<?=$exp?>" />
		</div>
		<div style="clear: both;"</div><br />
		
		<div class="lgstudent-field">
			<label>Grades</label><br />
			<table class="lgstudent-table">
				<tr>
					<th>Last Name</th>
					<th>First Name</th>
					<th>Email</th>
					<th>Grade</th>
				</tr>
				<?php foreach($students as $student) : $grade = $wpdb->get_row("SELECT grade FROM {$wpdb->prefix}lgstudent_grades WHERE user = '$student->email' AND assignment = '$post->post_name'"); $grade = $grade === NULL ? "-" : "$grade->grade"; ?>
				<tr>
					<td><?=$student->lastName?></td>
					<td><?=$student->firstName?></td>
					<td><?=$student->email?></td>
					<td><?=$grade?></td>
				</tr>
				<?php endforeach; ?>
			</table>
		</div>
		<div style="clear: both;"</div>
		
		<?php
	}
	
	function admin_menu()
	{
		add_menu_page("Grades", "Grades", "manage_options", "lgstudent_grades", array(&$this, "admin_menu_grades"), "dashicons-welcome-learn-more", 25);
		add_submenu_page("lgstudent_grades", "Students", "Students", "manage_options", "lgstudent_students", array(&$this, "admin_menu_students"));
	}
	
	function admin_menu_grades()
	{
		global $wpdb;
		
		$message = false;
		$error = false;
		if(current_user_can("edit_posts") && isset($_POST["action"]) && $_POST["action"] == "upload_confirm")
		{
			global $wpdb;
			
			$changes = array();
			if($_POST["changes"] != "")
				$changes = array_map(function($change) { $p = explode(",", $change); return array("user" => $p[0], "assignment" => $p[1], "grade" => $p[2]); }, explode("|", $_POST["changes"]));
			$successes = 0;
			$fails = 0;
			
			foreach($changes as $change)
			{
				if($change["grade"] == "-")
				{
					unset($change["grade"]);
					if($wpdb->delete("{$wpdb->prefix}lgstudent_grades", $change, "%s"))
						$successes++;
					else
						$fails++;
				}
				else
				{
					$change["grade"] = intval($change["grade"]);
					if($wpdb->replace("{$wpdb->prefix}lgstudent_grades", $change, array("%s", "%s", "%d")) !== false)
						$successes++;
					else
						$fails++;
				}
			}
			
			$message = "$successes grade(s) uploaded successfully!";
			$error = $fails > 0 ? "$fails error(s) occurred! {$wpdb->last_error}" : false;
		}
		
		?>
		<div class="wrap">
			<?php if($message) : ?>
				<span class="lgstudent-message"><?=$message?></span>
			<?php endif; ?>
			<?php if($error) : ?>
				<span class="lgstudent-error"><?=$error?></span>
			<?php endif; ?>
			
			<h2>Grades</h2>
			
			<?php if(isset($_POST["action"]) && $_POST["action"] == "upload") : ?>
				<h3>Preview Changes</h3>
				<?php
				global $wpdb;
				
				$rows			= preg_split("/\r\n|\n|\r/", file_get_contents($_FILES["file"]["tmp_name"]));
				$keys			= explode(",", array_shift($rows));
				$emailKey		= array_search("Email", $keys);
				$firstNameKey	= array_search("First Name", $keys);
				$lastNameKey	= array_search("Last Name", $keys);
				$changes		= array();
				
				foreach($rows as $row)
				{
					$cols	= explode(",", $row);
					$email	= $cols[$emailKey];
					for($i = 0; $i < count($cols); $i++)
					{
						if($i != $emailKey && $i != $firstNameKey && $i != $lastNameKey)
						{
							$oldGrade = $wpdb->get_row("SELECT grade FROM {$wpdb->prefix}lgstudent_grades WHERE user = '$email' AND assignment = '$keys[$i]'");
							if(($oldGrade === NULL && $cols[$i] != "") || $oldGrade->grade != $cols[$i])
								array_push($changes, (object) array(
									"user" => $email,
									"assignment" => $keys[$i],
									"grade" => $cols[$i] != "" ? $cols[$i] : "-",
									"oldGrade" => $oldGrade === NULL ? "-" : $oldGrade->grade)
								);
						}
					}
				}
				?>
				<?php if(!empty($changes)) : ?>
					<table class="lgstudent-table">
						<tr>
							<th>User</th>
							<th>Assignment</th>
							<th>New Grade</th>
							<th>Old Grade</th>
						</tr>
						<?php foreach($changes as $change) : ?>
						<tr>
							<td><?=$change->user?></td>
							<td><?=$change->assignment?></td>
							<td><?=$change->grade?></td>
							<td><?=$change->oldGrade?></td>
						</tr>
						<?php endforeach; ?>
					</table>
					<form method="post" action="">
						<input type="hidden" name="action" value="upload_confirm" />
						<input type="hidden" name="changes" value="<?=implode("|", array_map(function($change) { return "$change->user,$change->assignment,$change->grade"; }, $changes));?>" />
						<input type="submit" value="Confirm Changes" />
					</form>
				<?php else : ?>
					<span class="lgstudent-error">There are no grade changes!</span>
					<form method="post" action="">
						<input type="hidden" name="action" value="upload_confirm" />
						<input type="hidden" name="changes" value="" />
						<input type="submit" value="Confirm No Changes" />
					</form>
				<?php endif ?>
			<?php else : ?>
				<h3>Upload Grades</h3>
				<form method="post" action="" enctype="multipart/form-data">
					<div class="lgstudent-field">
						<input type="hidden" name="action" value="upload" />
						<input type="file" name="file" /><br />
						<input type="submit" value="Upload" />
					</div>
					<div style="clear: both"></div>
				</form>
				
				<h3>Download Grades</h3>
				<form method="post" action="">
					<div class="lgstudent-field">
						<input type="hidden" name="action" value="download" />
						<input type="submit" value="Download" />
					</div>
					<div style="clear: both"></div>
				</form>
				
				<h3>View Grades</h3>
				<?php
				$students = $this->db->getAllStudents();
				$assignments = get_posts(array(
					"post_type"			=> "lgstudent_assignment",
					"meta_key"			=> "lgstudent_assignment_type",
					"orderby"			=> "meta_value",
					"posts_per_page"	=> -1
				));
				?>
				<table class="lgstudent-table">
					<tr>
						<th>Last Name</th>
						<th>First Name</th>
						<th>Email</th>
						<?php foreach($assignments as $assignment) : ?>
						<th><?=$assignment->post_title?></th>
						<?php endforeach; ?>
					</tr>
					<?php foreach($students as $student) : ?>
					<tr>
						<td><?=$student->lastName?></td>
						<td><?=$student->firstName?></td>
						<td><?=$student->email?></td>
						<?php foreach($assignments as $assignment) : $grade = $wpdb->get_row("SELECT grade FROM {$wpdb->prefix}lgstudent_grades WHERE user = '$student->email' AND assignment = '$assignment->post_name'"); $grade = $grade === NULL ? "-" : "$grade->grade"; ?>
							<td><?=$grade?></td>
						<?php endforeach; ?>
					</tr>
					<?php endforeach; ?>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
	
	function admin_menu_students()
	{
		global $wpdb;
		
		$message = false;
		$error = false;
		if(isset($_POST["action"]) && $_POST["action"] == "add")
		{
			if($this->db->addStudent($_POST["email"], $_POST["fname"], $_POST["lname"]))
				$message = "Student $_POST[email] added successfully!";
			else
				$error = "Student add failed! " . $wpdb->last_error;
		}
		else if(isset($_POST["action"]) && $_POST["action"] == "delete")
		{
			if($this->db->deleteStudent($_POST["email"]))
				$message = "Student $_POST[email] deleted successfully!";
			else
				$error = "Student delete failed! " . $wpdb->last_error;
		}
		
		?>
		<div class="wrap">
			<?php if($message) : ?>
				<span class="lgstudent-message"><?=$message?></span>
			<?php endif; ?>
			<?php if($error) : ?>
				<span class="lgstudent-error"><?=$error?></span>
			<?php endif; ?>
			<h2>Students</h2>
			
			<h3>Add Student</h3>
			<form method="post" action="">
				<form method="post" action="">
				<div class="lgstudent-field">
					<label for="email">Email</label><br />
					<input type="text" name="email" />
				</div>
				<div class="lgstudent-field">
					<label for="fname">First Name</label><br />
					<input type="text" name="fname" />
				</div>
				<div class="lgstudent-field">
					<label for="lname">Last Name</label><br />
					<input type="text" name="lname" />
				</div>
				<div class="lgstudent-field">
					<label>&nbsp;</label><br />
					<input type="hidden" name="action" value="add" />
					<input type="submit" value="Add" />
				</div>
				<div style="clear: both"></div>
			</form>
			
			<h3>View Students</h3>
			<table class="lgstudent-table">
				<tr>
					<th>Email</th>
					<th>Last Name</th>
					<th>First Name</th>
					<th>Actions</th>
				</tr>
			<?php
			$students = $this->db->getAllStudents();
			foreach($students as $student) : ?>
				<tr>
					<td><?=$student->email?></td>
					<td><?=$student->lastName?></td>
					<td><?=$student->firstName?></td>
					<td>
						<form method="post" action="">
							<input type="hidden" name="action" value="delete" />
							<input type="hidden" name="email" value="<?=$student->email?>" />
							<input type="submit" value="Delete" />
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</div>
		<?php
	}
}

{
	$plugin = new LGStudent();
	register_activation_hook(__FILE__, array("LGStudent", "activate"));
}

?>
