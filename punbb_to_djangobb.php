<?php
/**
* punBB to DjangoBB migration script
*
* This script will create Python script for importing punBB data to DjangoBB.
* 
* It currently supports importing the following data:
* - users
* - groups
* - categories
* - forums
* - posts
* - reports
*
* @version 1.0.0
* @author Tomaž Muraus
* @link http://www.tomaz-muraus.info
* @license GPL
*/

header('Content-type: text/html; charset=utf-8');

define('FORUM_ROOT', './');
require(FORUM_ROOT . 'include/common.php');

ob_start();

echo "# -*- coding: utf-8 -*-
import getopt
import sys
from os import environ

environ['DJANGO_SETTINGS_MODULE'] = 'settings'

from settings import *
from django.contrib.auth.models import User, Group
from forum.models import *
import datetime\n\n";

echo "def importData():\n";

// Groups
$forum_db->query("SET NAMES 'utf8'");
if ($result = $forum_db->query("SELECT * FROM " . $forum_db->prefix . "groups"))
{
    echo "   # Groups\n";
    echo "   print 'Importing groups'\n\n";
	echo "   Group.objects.all().delete()\n";
	
	while ($row = $forum_db->fetch_assoc($result))
	{   
	    echo "   group = Group(id = '{$row['g_id']}', name = '{$row['g_title']}')\n";
	    echo "   group.save()\n";
	}
}

// Users
$forum_db->query("SET NAMES 'utf8'");
if ($result = $forum_db->query("SELECT * FROM " . $forum_db->prefix . "users WHERE group_id != 0"))
{
    echo "   # Users\n";
    echo "   print 'Importing users and profiles'\n\n";
	echo "   User.objects.all().delete()\n";
	
	while ($row = $forum_db->fetch_assoc($result))
	{
	    $realname = explode(' ', $row['realname']);
	    
	    if (count($realname) == 2)
	    {
	        $firstname = $realname[0];
	        $lastname = $realname[1];
	    }
	    else
	    {
	        $firstname = '';
	        $lastname = '';
	    }
	    
	    echo "   user = User(id = '{$row['id']}', username = '{$row['username']}', password = '''sha1punbb\${$row['salt']}\${$row['password']}''', first_name = '{$firstname}', last_name = '{$lastname}', email = '{$row['email']}', date_joined = datetime.datetime.fromtimestamp({$row['registered']}))\n";
	    
	    // Administrator
	    if ($row['group_id'] == 1)
	    {
	       echo "   user.is_staff = True\n";
	       echo "   user.is_superuser = True\n"; 
	    }
	    
	    echo "   user.save()\n";
	    echo "   user.groups.add(Group.objects.get(id = {$row['group_id']}))\n";
	    echo "   profile = Profile(user = User.objects.get(id = {$row['id']}), location = '{$row['location']}', site = '{$row['url']}', jabber = '{$row['jabber']}', icq = '{$row['icq']}', msn = '{$row['msn']}', yahoo = '{$row['yahoo']}', signature = '''{$row['signature']}''', markup = 'bbcode', show_avatar = '{$row['show_avatars']}', show_signatures = '{$row['show_sig']}', post_count = '{$row['num_posts']}')\n";
	    echo "   profile.save()\n";
	}
}

// Categories
$forum_db->query("SET NAMES 'utf8'");
if ($result = $forum_db->query("SELECT * FROM " . $forum_db->prefix . "categories"))
{
    echo "   # Categories\n";
    echo "   print 'Importing categories'\n\n";
	echo "   Category.objects.all().delete()\n";
	
	while ($row = $forum_db->fetch_assoc($result))
	{
	    echo "   cat = Category(id = '{$row['id']}', name = '{$row['cat_name']}', position = '{$row['disp_position']}')\n";
	    echo "   cat.save()\n";
	}
}

// Forums
$forum_db->query("SET NAMES 'utf8'");
if ($result = $forum_db->query("SELECT * FROM " . $forum_db->prefix . "forums"))
{
    echo "   # Forums\n";
    echo "   print 'Importing forums'\n\n";
	echo "   Forum.objects.all().delete()\n";
	
	while ($row = $forum_db->fetch_assoc($result))
	{
	    $moderators = $row['moderators'];
	    
	    echo "   forum = Forum(id = '{$row['id']}', category = Category.objects.get(id = {$row['cat_id']}), name = '''{$row['forum_name']}''', position = '{$row['disp_position']}', description = '''{$row['forum_desc']}''', post_count = '{$row['num_posts']}', topic_count = '{$row['num_topics']}')\n";
	    echo "   forum.save()\n";
	    
	    // Forum moderators
	    if ($moderators != '')
	    {
	        $moderators = unserialize($moderators);
	        
	        foreach ($moderators as $username => $userId)
	        {
	            echo "   forum.moderators.add(User.objects.get(id = {$userId}))\n";
	        }
	    }
	}
}

// Topics
$forum_db->query("SET NAMES 'utf8'");
if ($result = $forum_db->query("SELECT * FROM " . $forum_db->prefix . "topics"))
{
    echo "   # Topics\n";
    echo "   print 'Importing topics'\n\n";
	echo "   Topic.objects.all().delete()\n";
	
	while ($row = $forum_db->fetch_assoc($result))
	{
	    if ($row['last_post'] != '')
    	{
    	    $updated = "datetime.datetime.fromtimestamp({$row['last_post']})";
    	}
    	else
    	{
    	    $updated = 'None';
    	}
    	
    	$result2 = $forum_db->query("SELECT id FROM " . $forum_db->prefix . "users WHERE username = '{$row['poster']}'");
    	
    	// If user with username still exists
    	if (mysql_num_rows($result2) == 1)
    	{
    	    $posterId = mysql_result($result2, 0);
    	}
    	else
    	{
    	    $posterId = 1; // Guest
    	}
    	
    	$sticky = ($row['sticky'] == 1) ? 'True' : 'False';
    	$closed = ($row['closed'] == 1) ? 'True' : 'False';
	
	    echo "   topic = Topic(id = '{$row['id']}', forum = Forum.objects.get(id = {$row['forum_id']}), name = '{$row['subject']}', created = datetime.datetime.fromtimestamp({$row['posted']}), updated = {$updated}, user = User.objects.get(id = $posterId), views = '{$row['num_views']}', sticky = $sticky, closed = {$closed}, post_count = '{$row['num_replies']}')\n";
	    echo "   topic.save()\n";
	}
}


// Posts
$forum_db->query("SET NAMES 'utf8'");
if ($result = $forum_db->query("SELECT * FROM " . $forum_db->prefix . "posts"))
{
    echo "   # Posts\n";
    echo "   print 'Importing posts'\n\n";
	echo "   Post.objects.all().delete()\n";

	while ($row = $forum_db->fetch_assoc($result))
	{
    	$result2 = $forum_db->query("SELECT id FROM " . $forum_db->prefix . "users WHERE id = '{$row['poster_id']}'");
    
    	// If user with id still exists
    	if (mysql_num_rows($result2) == 1)
    	{
    	    $posterId = $row['poster_id'];
    	}
    	else
    	{
    	    $posterId = 1; // Guest
    	}
	    
	    if ($row['edited'] != '')
    	{
    	    $updated = "datetime.datetime.fromtimestamp({$row['edited']})";
    	}
    	else
    	{
    	    $updated = 'None';
    	}
    	
    	$message = str_replace('\\', '\\\\', $row['message']);
	
	    echo "   post = Post(id = '{$row['id']}', topic = Topic.objects.get(id = {$row['topic_id']}), user = User.objects.get(id = {$posterId}), created = datetime.datetime.fromtimestamp({$row['posted']}), updated = {$updated}, markup = 'bbcode', body = '''{$message}''', user_ip = '{$row['poster_ip']}')\n";
	    echo "   post.save()\n";
	    echo "   post.save()\n";
	}
}

// Reports
$forum_db->query("SET NAMES 'utf8'");
if ($result = $forum_db->query("SELECT * FROM " . $forum_db->prefix . "reports"))
{
    echo "   # Reports\n";
    echo "   print 'Importing reports'\n\n";
	echo "   Report.objects.all().delete()\n";
	
	while ($row = $forum_db->fetch_assoc($result))
	{
	    $zapped = $row['zapped'];
	    $zappedBy = $row['zapped_by'];
	    
	    echo "   report = Report(id = '{$row['id']}', reported_by = User.objects.get(id = {$row['reported_by']}), post = Post.objects.get(id = {$row['post_id']}), created = datetime.datetime.fromtimestamp({$row['created']}), reason = '''{$row['message']}''')\n";
	    
	    // If report has been dealt with
	    if ($zapped != '' && $zappedBy != '')
	    {
	        echo "   report.zapped = datetime.datetime.fromtimestamp({$row['zapped']})\n";
	        echo "   report.zapped_by = User.objects.get(id = {$row['zapped_by']})\n";
	    }
	    
	    echo "   report.save()\n";
	}
}

// Topics (last_post)
$forum_db->query("SET NAMES 'utf8'");
if ($result = $forum_db->query("SELECT * FROM " . $forum_db->prefix . "topics WHERE last_post_id IS NOT NULL AND last_post_id != 0"))
{
    echo "   # Topics (last_post)\n";
    echo "   print 'Updating topics last post references'\n\n";
	
	while ($row = $forum_db->fetch_assoc($result))
	{
	    echo "   topic = Topic.objects.get(id = '{$row['id']}')\n";
	    echo "   topic.last_post = Post.objects.get(id = {$row['last_post_id']})\n";
	    echo "   topic.save()\n";
	}
}

// Forums (last_post)
$forum_db->query("SET NAMES 'utf8'");
if ($result = $forum_db->query("SELECT * FROM " . $forum_db->prefix . "forums WHERE last_post_id IS NOT NULL  AND last_post_id != 0"))
{
    echo "   # Forums (las_topic)\n";
    echo "   print 'Updating forum last post references'\n\n";
	
	while ($row = $forum_db->fetch_assoc($result))
	{
	    $lastPostId = $row['last_post_id'];

	    echo "   forum = Forum.objects.get(id = '{$row['id']}')\n";
	    echo "   forum.last_post = Post.objects.get(id = {$lastPostId})\n";
	    echo "   forum.save()\n";
	}
}

echo "   print '\\nData has ben successfully imported.'\n\n";

echo "def fixUnicode():
   print 'Re-saving all the posts (this will regenerate the body_html field and hopefully fix the problem with unicode characters in post bodies'
   posts = Post.objects.all()
   
   for post in posts:
      post.save()
      
   print 'Completed.'\n\n";

echo "if __name__ == '__main__':
   try:                                
      opts, args = getopt.getopt(sys.argv[1:], 'if', ['import', 'fix-unicode'])
   except getopt.GetoptError, err:
      print str(err)
      sys.exit(2)
      
   for opt, arg in opts:
      if opt in ('-i', '--import'):
         print 'Are you sure you want to import the punBB data - all previous data will be deleted (yes/no)?'
         input = sys.stdin.readline().strip()
      
         if input == 'yes':
            importData()
         else:
            print 'Import canceled'
       
      if opt in ('-f', '--fix-unicode'):
            fixUnicode()";

$buffer = ob_get_contents();

ob_end_clean();

file_put_contents('punbb_to_djangobb.py', $buffer);
echo 'Migration script was successfully created and saved as <strong>punbb_to_djangobb.py</strong>';

?>