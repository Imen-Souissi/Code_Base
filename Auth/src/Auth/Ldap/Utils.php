<?php
namespace Auth\Ldap;

class Utils {
	public static function normalizeGroups($groups) {
		$newgroups = array();
		
		foreach($groups as $group) {
			$group = strtolower($group);
			list($cn) = split(',', $group);
			
			if(preg_match("/^cn=/", $cn)) {
				list($cn, $name) = split('=', $cn);
				$newgroups[] = $name;
			} else {
				$newgroups[] = $cn;
			}
		}
		
		return $newgroups;
	}
	
	public static function normalizeMembers($members) {
		$newmembers = array(
			'users' => array(),
			'groups' => array()
		);
		
		foreach($members as $member) {
			list($cn, $remaining) = split(',', $member, 2);
			$remaining = strtolower($remaining);
			
			if(preg_match("/^cn=/i", $cn)) {
				list($cn, $name) = split('=', $cn);				
			} else {
				$name = $cn;
			}
			
			if(strpos($remaining, 'ou=groups') > -1) {
				$newmembers['groups'][] = strtolower($name);
			} else if(strpos($remaining, 'ou=accounts') > -1) {
				$newmembers['users'][] = $name;
			}
		}
		
		return $newmembers;
	}
}
?>