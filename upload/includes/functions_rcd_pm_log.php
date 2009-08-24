<?php
/*======================================================================*\
|| #################################################################### ||
|| # PM Log 2.2                                                       # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright © 2009 Dmitry Titov, Vitaly Puzrin.                    # ||
|| # All Rights Reserved.                                             # ||
|| # This file may not be redistributed in whole or significant part. # ||
|| #################################################################### ||
\*======================================================================*/


if (!isset($GLOBALS['vbulletin']->db))
{
  exit;
}


#
#   Define additional functions
#

if (!function_exists('can_administer_pm_log'))
{
  function can_administer_pm_log()
  {
    global $vbulletin;

    if ($vbulletin->userinfo['userid'] < 1)
    {
      // user is a guest - definitely not an administrator
      return false;
    }

    static $admin;

    require_once(DIR . '/includes/adminfunctions.php');

    $return_value = false;

    // use this check only for admins, but not superadmins
    if (can_administer() /*AND !can_administer('adminviewpmlog')*/)
    {
      if (!isset($admin))
      {
        // query specific admin permissions from the administrator
        // table and assign them to $adminperms
        $getperms = $vbulletin->db->query_first("
          SELECT `admin_view_pm_log`
          FROM `" . TABLE_PREFIX . "administrator`
          WHERE `userid` = " . $vbulletin->userinfo['userid']
        );

        $admin = $getperms;
      }

      $return_value = $admin['admin_view_pm_log'] ? true : false;
    }

    return $return_value;
  }
}


#
#   Cache templates
#

function rcd_pm_log_CacheTemplates (&$globaltemplates)
{
  global $vbulletin;

  if ($vbulletin->options['rcd_pm_log_show_link'])
  {
    if (THIS_SCRIPT == 'showthread')
      $globaltemplates[] = 'rcd_log_pm_link';

    if (THIS_SCRIPT == 'member')
      $globaltemplates[] = 'rcd_log_pm_link_memberinfo';
  }
}


#
#   Check permissions
#

function rcd_pm_log_CheckPermissions (&$do, &$admin, &$return_value)
{
  global $vbulletin;

  if ($vbulletin->options['rcd_pm_log_show_link'])
  {
    foreach($do AS $field)
    {
      if ($field == 'adminviewpmlog')
      {
        $return_value = $admin['admin_view_pm_log'] ? true : false;

        break;
      }
    }
  }
}


#
#   Memberinfo PM Log link
#

function rcd_pm_log_MemberinfoPMLoglink ()
{
  require_once(DIR . '/includes/adminfunctions.php');

  $rcd_pm_log_link = '';

  if (THIS_SCRIPT == 'member'
      AND (can_administer('adminviewpmlog') OR can_administer_pm_log()))
  {
    global $admincpdir, $vbphrase, $session, $stylevar, $userinfo;

    eval('$rcd_pm_log_link .= "' . fetch_template('rcd_log_pm_link_memberinfo') . '";');
  }

  return $rcd_pm_log_link;
}


#
#   Modify template
#

function rcd_pm_log_ModifyTemplate ()
{
  global $vbulletin;

  if ($vbulletin->options['rcd_pm_log_show_link'])
  {
    $rcd_pm_search  = '$vbphrase[edit_user_profile]</a></li>';
    $rcd_pm_replace = $rcd_pm_search . '$rcd_pm_log_link';

    $vbulletin->templatecache['MEMBERINFO'] = str_replace(
        $rcd_pm_search  ,
        $rcd_pm_replace ,
        $vbulletin->templatecache['MEMBERINFO']
      );

    unset( $rcd_pm_search, $rcd_pm_replace );
  }
}


#
#   Log PM
#

function rcd_pm_log_LogPM (&$obj, &$user, &$pmtextid)
{
  if ($obj->dbobject->insert_id())
  {
    global $vbulletin;

    $obj->dbobject->query_write( "
      REPLACE INTO
        `" . TABLE_PREFIX . "rcd_log_pm`
      SET
        `pmid`          =  " . intval($obj->dbobject->insert_id()) . ",
        `pmtextid`      =  " . intval($pmtextid) . ",
        `fromuserip`    = '" . $obj->dbobject->escape_string($vbulletin->ipaddress) . "',
        `fromuserid`    =  " . $obj->dbobject->escape_string($vbulletin->userinfo['userid']) . ",
        `fromusername`  = '" . $obj->dbobject->escape_string($vbulletin->userinfo['username']) . "',
        `fromuseremail` = '" . $obj->dbobject->escape_string($vbulletin->userinfo['email']) . "',
        `touserid`      =  " . $obj->dbobject->escape_string($user[userid]) . ",
        `tousername`    = '" . $obj->dbobject->escape_string($user['username']) . "',
        `touseremail`   = '" . $obj->dbobject->escape_string($user['email']) . "',
        `title`         = '" . $obj->dbobject->escape_string($obj->pmtext['title']) . "',
        `message`       = '" . $obj->dbobject->escape_string($obj->pmtext['message']) . "',
        `iconid`        =  " . intval($obj->pmtext['iconid']) . ",
        `dateline`      =  " . intval(TIMENOW) . ",
        `showsignature` =  " . intval($obj->pmtext['showsignature']) . ",
        `allowsmilie`   =  " . intval($obj->pmtext['allowsmilie']) . "
    " );
  }
}


#
#   Show link to user PM log
#

function rcd_pm_log_ShowlinkToUserPMLog ()
{
  require_once(DIR . '/includes/adminfunctions.php');

  $rcd_log_pm_link = '';

  if (THIS_SCRIPT == 'showthread'
      AND (can_administer('adminviewpmlog') OR can_administer_pm_log()))
  {
    global $admincpdir, $session, $post, $vbphrase;

    eval('$rcd_log_pm_link .= "' . fetch_template('rcd_log_pm_link') . '";');
  }

  return $rcd_log_pm_link;
}


#
#   Log outbound email
#

function rcd_pm_log_LogOutboundEmail (&$userinfo)
{
  global $vbulletin;

  $db =& $vbulletin->db;

  $db->query_write( "
    REPLACE INTO
      `" . TABLE_PREFIX . "rcd_log_pm`
    SET
      `fromuserip`    = '" . $db->escape_string($vbulletin->ipaddress) . "',
      `fromuserid`    =  " . $db->escape_string($vbulletin->userinfo['userid']) . ",
      `fromusername`  = '" . $db->escape_string($vbulletin->userinfo['username']) . "',
      `fromuseremail` = '" . $db->escape_string($vbulletin->userinfo['email']) . "',
      `touserid`      =  " . $db->escape_string($userinfo['userid']) . ",
      `tousername`    = '" . $db->escape_string($userinfo['username']) . "',
      `touseremail`   = '" . $db->escape_string($userinfo['email']) . "',
      `title`         = '" . "mail: " . $db->escape_string(fetch_censored_text($vbulletin->GPC['emailsubject'])) . "',
      `message`       = '" . $db->escape_string(fetch_censored_text($vbulletin->GPC['message'])) . "',
      `dateline`      =  " . intval(TIMENOW) . "
  " );
}


#
#   Update username
#

function rcd_pm_log_UpdateUsername (&$obj, &$username, &$userid)
{
  // pm log recepient 'tousername'
  $obj->dbobject->query_write("
    UPDATE `" . TABLE_PREFIX . "rcd_log_pm` SET
    `tousername` = '" . $obj->dbobject->escape_string($username) . "'
    WHERE `touserid` = $userid
  ");

  // pm log sender 'fromusername'
  $obj->dbobject->query_write("
    UPDATE `" . TABLE_PREFIX . "rcd_log_pm` SET
    `fromusername` = '" . $obj->dbobject->escape_string($username) . "'
    WHERE `fromuserid` = $userid
  ");
}