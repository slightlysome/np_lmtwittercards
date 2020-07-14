<?php
/*
    LMTwitterCards Nucleus plugin
    Copyright (C) 2014 Leo (http://nucleus.slightlysome.net/leo)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
	(http://www.gnu.org/licenses/gpl-2.0.html)
	
	See lmtwittercards/help.html for plugin description, install, usage and change history.
*/
class NP_LMTwitterCards extends NucleusPlugin
{
	// name of plugin 
	function getName()
	{
		return 'LMTwitterCards';
	}

	// author of plugin
	function getAuthor()
	{
		return 'Leo (http://nucleus.slightlysome.net/leo)';
	}

	// an URL to the plugin website
	// can also be of the form mailto:foo@bar.com
	function getURL()
	{
		return 'http://nucleus.slightlysome.net/plugins/lmtwittercards';
	}

	// version of the plugin
	function getVersion()
	{
		return '1.0.0';
	}

	// a description to be shown on the installed plugins listing
	function getDescription()
	{
		return 'Add Twitter Cards metadata to the items in your blog. Can also add Open Graph metadata.';
	}

	function supportsFeature ($what)
	{
		switch ($what)
		{
			case 'SqlTablePrefix':
				return 1;
			case 'SqlApi':
				return 1;
			case 'HelpPage':
				return 1;
			default:
				return 0;
		}
	}
	
	function hasAdminArea()
	{
		return 1;
	}
	
	function getMinNucleusVersion()
	{
		return '360';
	}
	
	function getEventList() 
	{ 
		return array('AdminPrePageFoot'); 
	}
	
	function install()
	{
		$sourcedataversion = $this->getDataVersion();

		$this->upgradeDataPerform(1, $sourcedataversion);
		$this->setCurrentDataVersion($sourcedataversion);
		$this->upgradeDataCommit(1, $sourcedataversion);
		$this->setCommitDataVersion($sourcedataversion);					
	}
	
	function event_AdminPrePageFoot(&$data)
	{
		// Workaround for missing event: AdminPluginNotification
		$data['notifications'] = array();
			
		$this->event_AdminPluginNotification($data);
			
		foreach($data['notifications'] as $aNotification)
		{
			echo '<h2>Notification from plugin: '.htmlspecialchars($aNotification['plugin'], ENT_QUOTES, _CHARSET).'</h2>';
			echo $aNotification['text'];
		}
	}
	
	////////////////////////////////////////////////////////////
	//  Events
	function event_AdminPluginNotification(&$data)
	{
		global $member, $manager, $CONF;
		
		$actions = array('overview', 'pluginlist', 'plugin_LMTwitterCards');
		$text = "";
		
		if(in_array($data['action'], $actions))
		{			
			$sourcedataversion = $this->getDataVersion();
			$commitdataversion = $this->getCommitDataVersion();
			$currentdataversion = $this->getCurrentDataVersion();
		
			if($currentdataversion > $sourcedataversion)
			{
				$text .= '<p>An old version of the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' plugin files are installed. Downgrade of the plugin data is not supported. The correct version of the plugin files must be installed for the plugin to work properly.</p>';
			}
			
			if($currentdataversion < $sourcedataversion)
			{
				$text .= '<p>The version of the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' plugin data is for an older version of the plugin than the version installed. ';
				$text .= 'The plugin data needs to be upgraded or the source files needs to be replaced with the source files for the old version before the plugin can be used. ';

				if($member->isAdmin())
				{
					$text .= 'Plugin data upgrade can be done on the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' <a href="'.$this->getAdminURL().'">admin page</a>.';
				}
				
				$text .= '</p>';
			}
			
			if($commitdataversion < $currentdataversion && $member->isAdmin())
			{
				$text .= '<p>The version of the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' plugin data is upgraded, but the upgrade needs to commited or rolled back to finish the upgrade process. ';
				$text .= 'Plugin data upgrade commit and rollback can be done on the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' <a href="'.$this->getAdminURL().'">admin page</a>.</p>';
			}

			$cardimagesrc = $this->getOption('globalcardimagesrc');
			
			if(!$cardimagesrc)
			{
				if($member->isAdmin())
				{
					$text .= '<p>It \'s recommended to set the default image url <a href="'.$CONF['AdminURL'].'index.php?action=pluginoptions&plugid='.$this->getID().'">plugin option</a> ';
					$text .= 'for the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' plugin as the image metadata property is should always be included in a Twitter Card.</p>';
				}
				else
				{
					$text .= '<p>It \'s recommended to set the default image url plugin option for the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET);
					$text .= ' plugin as the image metadata property is should always be included in a Twitter Card.</p>';
				}
			}
		}
		
		if($text)
		{
			array_push(
				$data['notifications'],
				array(
					'plugin' => $this->getName(),
					'text' => $text
				)
			);
		}
	}

////////////////////////////////////////////////////////////
//  Handle vars

	function doSkinVar($skinType, $vartype, $templatename = '')
	{
		global $manager;

		$aArgs = func_get_args(); 
		$num = func_num_args();

		$aSkinVarParm = array();
		
		for($n = 3; $n < $num; $n++)
		{
			$parm = explode("=", func_get_arg($n));
			
			if(is_array($parm))
			{
				$aSkinVarParm[$parm['0']] = $parm['1'];
			}
		}

		if($templatename)
		{
			$template =& $manager->getTemplate($templatename);
		}
		else
		{
			$template = array();
		}

		switch (strtoupper($vartype))
		{
			case 'TWITTERCARD':
				$this->doSkinVar_TwitterCard($skinType, $template, $aSkinVarParm);
				break;
			default:
				echo "Unknown vartype: ".$vartype;
		}
	}
	
	function doSkinVar_TwitterCard($skinType, $template, $aSkinVarParm)
	{
		global $blogid, $itemid, $CONF;
		
		if($skinType == 'item')
		{
			$cardtype = $this->getBlogOption($blogid, 'blogcardtype');
			
			if($cardtype == 'default')
			{
				$cardtype = $this->getOption('globalcardtype');
			}
			
			if($cardtype <> 'none')
			{
				$aaItem = $this->_getItemByItemId($itemid);
				if($aaItem === false) { return false; }
				
				if($aaItem)
				{
					$aItem = $aaItem[0];
					
					if(!$aItem) { return false; }
				}
				
				$cardsite = $this->getBlogOption($blogid, 'blogcardsite');
				
				if(!$cardsite)
				{
					$cardsite = $this->getOption('globalcardsite');
				}
			
				$cardimagesrc = $this->getItemOption($itemid, 'itemcardimagesrc');
				
				if(!$cardimagesrc)
				{
					$res = preg_match('/<%image\((.*?)\)%>/', $aItem['body'], $matches);
					
					if(!$res)
					{
						$res = preg_match('/<%image\((.*?)\)%>/', $aItem['more'], $matches);
					}
					
					if($res)
					{
						$imageparams = explode('|', $matches[1]);
						
						$filename = $imageparams[0];
						
						if(!strstr($filename,'/')) 
						{
							$filename = $aItem['memberid'].'/'.$filename;
						}
						
						$cardimagesrc = $CONF['MediaURL'].$filename;
					}
				}

				if(!$cardimagesrc)
				{
					$cardimagesrc = $this->getBlogOption($blogid, 'blogcardimagesrc');
				}
				
				if(!$cardimagesrc)
				{
					$cardimagesrc = $this->getOption('globalcardimagesrc');
				}
				
				$memberid = $aItem['memberid'];
				
				$cardcreator = $this->getMemberOption($memberid, 'membercardcreator');
				
				if(!$cardcreator)
				{
					$cardcreator = $this->getOption('globalcardcreator');
				}
				
				$opengraph = $this->getOption('globalopengraph');
				
				echo '<meta name="twitter:card" content="'.htmlspecialchars($cardtype ,ENT_QUOTES,_CHARSET).'" />'."\n";
				
				if($cardsite)
				{
					echo '<meta name="twitter:site" content="'.htmlspecialchars($cardsite ,ENT_QUOTES,_CHARSET).'" />'."\n";
				}
				
				if($cardcreator)
				{
					echo '<meta name="twitter:creator" content="'.htmlspecialchars($cardcreator ,ENT_QUOTES,_CHARSET).'" />'."\n";
				}
				
				$title = trim($aItem['title']);
				
				if(strlen($title) > 70)
				{
					$title = substr($title, 0, 67);
					$tmp = substr($title, 0, strrpos($title, ' '));
					
					if($tmp)
					{
						$title = $title.'...';
					}
				}

				if($title)
				{
					if($opengraph == 'yes')
					{
						echo '<meta property="og:title" content="'.htmlspecialchars($title ,ENT_QUOTES,_CHARSET).'" />'."\n";
					}
					else
					{
						echo '<meta name="twitter:title" content="'.htmlspecialchars($title ,ENT_QUOTES,_CHARSET).'" />'."\n";
					}
				}
				
				$description = trim(preg_replace('/\s+/', ' ', strip_tags($aItem['body'])));
				
				if(strlen($description) > 200)
				{
					$description = substr($description, 0, 197);
					$tmp = substr($description, 0, strrpos($description, ' '));
					
					if($tmp)
					{
						$description = $tmp.'...';
					}
				}
				
				if($description)
				{
					if($opengraph == 'yes')
					{
						echo '<meta property="og:description" content="'.htmlspecialchars($description ,ENT_QUOTES,_CHARSET).'" />'."\n";
					}
					else
					{
						echo '<meta name="twitter:description" content="'.htmlspecialchars($description ,ENT_QUOTES,_CHARSET).'" />'."\n";
					}
				}

				if($cardimagesrc)
				{
					if($opengraph == 'yes')
					{
						echo '<meta property="og:image:src" content="'.htmlspecialchars($cardimagesrc ,ENT_QUOTES,_CHARSET).'" />'."\n";
					}
					else
					{
						echo '<meta name="twitter:image:src" content="'.htmlspecialchars($cardimagesrc ,ENT_QUOTES,_CHARSET).'" />'."\n";
					}
				}
				
				if($opengraph == 'yes')
				{
					echo '<meta property="og:type" content="article" />'."\n";
					echo '<meta property="og:url" content="'.createItemLink($itemid).'" />'."\n";
				}
			}
		}
	}

	////////////////////////////////////////////////////////////////////////
	// Internal functions: Data access Item	
	function _getItemByItemId($itemid)
	{
		return $this->_getItem($itemid);
	}

	function _getItem($itemid)
	{
		$ret = array();
		
		$query = "SELECT inumber AS itemid, ititle AS title, ibody AS body, imore AS more, iauthor as memberid FROM ".sql_table('item')." ";
		
		if($itemid)
		{
			$query .= "WHERE inumber = ".$itemid." ";
		}

		$res = sql_query($query);
		
		if($res)
		{			
			while ($item = sql_fetch_assoc($res)) 
			{
				array_push($ret, $item);
			}
		}
		else
		{
			return false;
		}
		return $ret;
	}

	////////////////////////////////////////////////////////////////////////
	// Plugin Upgrade handling functions
	function getCurrentDataVersion()
	{
		$currentdataversion = $this->getOption('currentdataversion');
		
		if(!$currentdataversion)
		{
			$currentdataversion = 0;
		}
		
		return $currentdataversion;
	}

	function setCurrentDataVersion($currentdataversion)
	{
		$res = $this->setOption('currentdataversion', $currentdataversion);
		$this->clearOptionValueCache(); // Workaround for bug in Nucleus Core
		
		return $res;
	}

	function getCommitDataVersion()
	{
		$commitdataversion = $this->getOption('commitdataversion');
		
		if(!$commitdataversion)
		{
			$commitdataversion = 0;
		}

		return $commitdataversion;
	}

	function setCommitDataVersion($commitdataversion)
	{	
		$res = $this->setOption('commitdataversion', $commitdataversion);
		$this->clearOptionValueCache(); // Workaround for bug in Nucleus Core
		
		return $res;
	}

	function getDataVersion()
	{
		return 1;
	}
	
	function upgradeDataTest($fromdataversion, $todataversion)
	{
		// returns true if rollback will be possible after upgrade
		$res = true;
				
		return $res;
	}
	
	function upgradeDataPerform($fromdataversion, $todataversion)
	{
		// Returns true if upgrade was successfull
		
		for($ver = $fromdataversion; $ver <= $todataversion; $ver++)
		{
			switch($ver)
			{
				case 1:
					$this->createOption('currentdataversion', 'currentdataversion', 'text','0', 'access=hidden');
					$this->createOption('commitdataversion', 'commitdataversion', 'text','0', 'access=hidden');

					$this->createOption('globalcardtype', 'Default card type', 'select','summary', 'Summary|summary|Summary with large image|summary_large_image');
					$this->createOption('globalcardsite', 'Default Twitter @username of website', 'text');
					$this->createOption('globalcardcreator', 'Default Twitter @username of content creator', 'text');
					$this->createOption('globalcardimagesrc', 'Default image URL to use', 'text');
					$this->createOption('globalopengraph', 'Use Open Graph meta tags', 'yesno','no');
					
					$this->createBlogOption('blogcardtype', 'Card type to use for this blog', 'select','default', 'Default|default|Summary|summary|Summary with large image|summary_large_image|None|none');
					$this->createBlogOption('blogcardsite', 'Twitter @username of website for this blog', 'text');
					$this->createBlogOption('blogcardimagesrc', 'Default image URL to use for this blog', 'text');

					$this->createMemberOption('membercardcreator', 'Twitter @username of content creator for this member', 'text');
			
					$this->createItemOption('itemcardimagesrc', 'Image URL to use for this item', 'text');
					
					$res = true;
					break;
				default:
					$res = false;
					break;
			}
			
			if(!$res)
			{
				return false;
			}
		}
		
		return true;
	}
	
	function upgradeDataRollback($fromdataversion, $todataversion)
	{
		// Returns true if rollback was successfull
		for($ver = $fromdataversion; $ver >= $todataversion; $ver--)
		{
			switch($ver)
			{
				case 1:
					$res = true;
					break;
				default:
					$res = false;
					break;
			}
			
			if(!$res)
			{
				return false;
			}
		}

		return true;
	}

	function upgradeDataCommit($fromdataversion, $todataversion)
	{
		// Returns true if commit was successfull
		for($ver = $fromdataversion; $ver <= $todataversion; $ver++)
		{
			switch($ver)
			{
				case 1:
					$res = true;
					break;
				default:
					$res = false;
					break;
			}
			
			if(!$res)
			{
				return false;
			}
		}
		return true;
	}
}
?>
