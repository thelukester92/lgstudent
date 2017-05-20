<?php

if(!defined("ABSPATH")) exit;

class LGUtil
{
	static function adminData()
	{
		return get_userdata(intval(get_user_by("email", get_bloginfo("admin_email"))->ID));
	}
}

?>
