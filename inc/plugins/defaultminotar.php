<?php
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

function defaultminotar_info()
{
    global $mybb, $db, $lang;
    
    return array(
        'name'          => 'MyBB Minotar Avatars',
        'description'   => 'Simple MyBB 1.8 plugin for Minotar Avatars',
        'website'       => 'https://github.com/Ascii/MyBB_Minotar',
        'author'        => 'Ascii',
        'authorsite'    => 'https://github.com/Ascii',
        'version'       => '0.2',
        'codename'      => 'defaultminotar',
        'compatibility' => '18*'
    );
}

function defaultminotar_activate()
{
   global $db;

   $template = '<img src="{$user[\'avatar\']}" alt="{$user[\'username\']}" title="{$user[\'username\']}" width="{$user[\'width\']}" height="{$user[\'height\']}" />';

   $insert_array = array(
       'title' => 'minotar_avatar_template',
       'template' => $db->escape_string($template),
       'sid' => '-1',
       'version' => '',
       'dateline' => time()
   );

}


function defaultminotar_deactivate()
{
   global $db;

   $db->delete_query("templates", "title = 'minotar_avatar_template'");

}

$plugins->add_hook('forumdisplay_thread', 'defaultminotar_forumdisplay');
$plugins->add_hook('build_forumbits_forum', 'defaultminotar_forumbits', 0);

#### done ####
$plugins->add_hook('member_profile_end','defaultminotar_profile'); //profile.php
$plugins->add_hook('showthread_linear', 'defaultminotar_postbit_avatar'); //showthread.php
$plugins->add_hook('memberlist_user', 'defaultminotar_memberlist_user'); //memberlist.php
$plugins->add_hook('misc_buddypopup_end', 'defaultminotar_buddypopup_end'); //misc.php buddylist
$plugins->add_hook('private_read_end', 'defaultminotar_read_end');

function defaultminotar_read_end()
{
   global $mybb, $pm, $message;
   $pm = defaultminotar_getavatar($pm);
   $message = build_postbit($pm, 2);
}

//doneish
function defaultminotar_buddypopup_end()
{
   global $mybb, $buddys, $db, $lang, $templates, $buddies;

   if($mybb->user['buddylist'] != "")
	{
		$buddys = array('online' => '', 'offline' => '');
      
      $timecut = TIME_NOW - $mybb->settings['wolcutoff'];

		$query = $db->simple_select("users", "*", "uid IN ({$mybb->user['buddylist']})", array('order_by' => 'lastactive'));

		while($buddy = $db->fetch_array($query))
		{
         $buddy = defaultminotar_getavatar($buddy);
			$buddy_name = format_name($buddy['username'], $buddy['usergroup'], $buddy['displaygroup']);
			$profile_link = build_profile_link($buddy_name, $buddy['uid'], '_blank', 'if(window.opener) { window.opener.location = this.href; return false; }');

			$send_pm = '';
			if($mybb->user['receivepms'] != 0 && $buddy['receivepms'] != 0 && $groupscache[$buddy['usergroup']]['canusepms'] != 0)
			{
				eval("\$send_pm = \"".$templates->get("misc_buddypopup_user_sendpm")."\";");
			}

			if($buddy['lastactive'])
			{
				$last_active = $lang->sprintf($lang->last_active, my_date('relative', $buddy['lastactive']));
			}
			else
			{
				$last_active = $lang->sprintf($lang->last_active, $lang->never);
			}

			$buddy['avatar'] = format_avatar(htmlspecialchars_uni($buddy['avatar']), $buddy['avatardimensions'], '44x44');

			if($buddy['lastactive'] > $timecut && ($buddy['invisible'] == 0 || $mybb->user['usergroup'] == 4) && $buddy['lastvisit'] != $buddy['lastactive'])
			{
				$bonline_alt = alt_trow();
				eval("\$buddys['online'] .= \"".$templates->get("misc_buddypopup_user_online")."\";");
			}
			else
			{
				$boffline_alt = alt_trow();
				eval("\$buddys['offline'] .= \"".$templates->get("misc_buddypopup_user_offline")."\";");
			}
         
         $colspan = ' colspan="2"';
         if(empty($buddys['online']))
         {
            $error = $lang->online_none;
            eval("\$buddys['online'] = \"".$templates->get("misc_buddypopup_user_none")."\";");
         }

         if(empty($buddys['offline']))
         {
            $error = $lang->offline_none;
            eval("\$buddys['offline'] = \"".$templates->get("misc_buddypopup_user_none")."\";");
         }
      }
      
      eval("\$buddies = \"".$templates->get("misc_buddypopup_user")."\";");
      }
      else
      {
         // No buddies? :(
         $colspan = '';
         $error = $lang->no_buddies;
         eval("\$buddies = \"".$templates->get("misc_buddypopup_user_none")."\";");
      }
}

//done
function defaultminotar_memberlist_user($_user)
{
   global $mybb;
   
   return defaultminotar_getavatar($_user);
}

//done
function defaultminotar_postbit_avatar()
{
    global $mybb;
    global $posts;
    global $templates;
    global $pids;
    global $db;
    global $thread;
    
	    $posts = ''; //clear posts
		$query = $db->query("
			SELECT u.*, u.username AS userusername, p.*, f.*, eu.username AS editusername
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
			LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid)
			LEFT JOIN ".TABLE_PREFIX."users eu ON (eu.uid=p.edituid)
			WHERE $pids
			ORDER BY p.dateline
		");
		while($post = $db->fetch_array($query))
		{
			if($thread['firstpost'] == $post['pid'] && $thread['visible'] == 0)
			{
				$post['visible'] = 0;
			}
         
         		$post_ = defaultminotar_getavatar($post);
         
			$posts .= build_postbit($post_);
			$post_ = '';
		}
}

function defaultminotar_forumbits(&$f)
{
	$f['avatar'] = '';
	static $userscache = null;
	if($userscache === null)
	{
		global $settings, $templates, $forum_cache;
		$forum_cache or cache_forums();

		global $db;
		$query = $db->query('
			SELECT u.uid, u.username, u.avatar, u.avatardimensions, f.fid, f.lastpost
			FROM '.TABLE_PREFIX.'forums f
			LEFT JOIN '.TABLE_PREFIX.'users u ON (u.uid=f.lastposteruid)
			WHERE f.active=\'1\' AND f.type=\'f\'
			ORDER BY f.lastpost DESC
		');
		// Build the cache
		$userscache = array();
		while($user = $db->fetch_array($query))
		{
			$userscache[$user['fid']] = $user;
			unset($userscache[$user['fid']]['fid']);
		}
		foreach($userscache as $fid => &$user)
		{
			$forum = $forum_cache[$fid];
			if($forum['pid'].','.$forum['fid'] != $forum['parentlist'])
			{
				$parent_time = $userscache[$forum['pid']]['lastpost'];
				if($userscache[$forum['fid']]['lastpost'] >= $parent_time)
				{
					$userscache[$forum['pid']] = $userscache[$forum['fid']];
				}
			}
		}
	}
	if(isset($userscache[$f['fid']]))
	{
		unset($userscache[$f['fid']]['lastpost']);
		$f['avatar'] = defaultminotar_getavatar($userscache[$f['fid']]);
	}
	else
	{
		$f['avatar'] = defaultminotar_getavatar();
	}
}

//done
function defaultminotar_profile()
{
    global $mybb;
    global $memprofile;
    global $templates;
    global $avatar;
    
    $memprofile = defaultminotar_getavatar($memprofile);
    $useravatar = format_avatar($memprofile['avatar'], $memprofile['avatardimensions']);
	 eval("\$avatar = \"".$templates->get("member_profile_avatar")."\";");   
}

// Show the avatar in forum threads list.
function defaultminotar_forumdisplay()
{
	global $settings, $thread, $tids;
	$thread['avatar'] = $thread['lastpostavatar'] = '';
	static $userscache = null;
	if($userscache === null)
	{
		global $templates; 
      global $db;
		$userscache = array();

		$query = $db->query('
			SELECT u.uid, u.username, u.avatar, u.avatardimensions, lu.uid, lu.username, lu.avatar, lu.avatardimensions
			FROM '.TABLE_PREFIX.'threads t
			LEFT JOIN '.TABLE_PREFIX.'users u ON (u.uid=t.uid)
			LEFT JOIN '.TABLE_PREFIX.'users lu ON (lu.uid=t.lastposteruid)
			WHERE t.tid IN ('.implode(',', array_unique(array_map('intval', explode(',', $tids)))).')
		');
		while($user = $db->fetch_array($query))
		{
			$userscache[$user['uid']] = $user;
		}
	}
	if(isset($userscache[$thread['uid']]))
	{
		$thread['avatar'] = defaultminotar_getavatar($userscache[$thread['uid']]);

	}
	else
	{
		$thread['avatar'] = defaultminotar_getavatar();

	}
	if(isset($userscache[$thread['lastposteruid']]))
	{
		$thread['lastpostavatar'] = defaultminotar_getavatar($userscache[$thread['lastposteruid']]);
	}
	else
	{
		$thread['lastpostavatar'] = defaultminotar_getavatar();
	}
}

// Save us time getting the data
function defaultminotar_getavatar($user=array('uid' => 0))
{
	//static $cache = array();
	
	//	if(!isset($cache[$user['uid']]))
	//{
	global $settings, $templates;
	$user['uid'] = (int)$user['uid'];
	if(empty($user['username']))
	{
		global $lang;
		$user['username'] = $lang->guest;
	}
	
	$user['username'] = htmlspecialchars_uni($user['username']);
	$user['profilelink'] = get_profile_link($user['uid']);
	
	if(empty($user['avatar']) or ($user['avatar'] == 'images/avatars/invalid_url.gif'))
	{
		$user['avatar'] = 'https://minotar.net/helm/' . $user['username'] . '/100.png';
		$user['avatardimensions'] = $settings['useravatardims'];
	}
	
	$user['avatar'] = htmlspecialchars_uni($user['avatar']);
	$dimensions = explode('|', $user['avatardimensions']);
	if(isset($dimensions[0]) && isset($dimensions[1]))
	{
		list($maxwidth, $maxheight) = explode('x', my_strtolower($settings['ougc_showavatar_maxwh']));
		if($dimensions[0] > (int)$maxwidth || $dimensions[1] > (int)$maxheight)
		{
			require_once MYBB_ROOT.'inc/functions_image.php';
			$scale = scale_image($dimensions[0], $dimensions[1], (int)$maxwidth, (int)$maxheight);
		}
		$user['width'] = (int)(isset($scale['width']) ? $scale['width'] : $dimensions[0]);
		$user['height'] = (int)(isset($scale['height']) ? $scale['height'] : $dimensions[1]);
	}
	//eval('$cache[$user[\'uid\']] = "'.$templates->get('minotar_avatar_template').'";');//puts items in cache
	eval('$user[\'template\'] = "'.$templates->get('minotar_avatar_template').'";');//puts items in cache

   //}
   
	//return $cache[$user['uid']];
   return $user;
}

/*
function defaultminotar()
{
	 global $mybb, $thread, $post;
	 
    $_uname = ''
    
    if (!empty($post)) 
    {
      $_uname = $post['username'];
    }elseif(!empty($thread)){
      $_uname = $thread['username'];
    }else{
      $_uname = $mybb->user['username'];
    }
    
	 if(!$mybb->user['avatar'] or ($mybb->user['avatar'] == 'images/avatars/invalid_url.gif'))
	 {
      $mybb->user['avatar'] = 'https://minotar.net/helm/' . $_uname . '/100.png';
    }
}
$plugins->add_hook("global_start", "defaultminotar");*/
?>