<?php
namespace Auth\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Log\Logger;
use Zend\View\Model\ViewModel;
use Zend\EventManager\EventManager;
use Zend\Db\Sql\Expression;
use Zend\Crypt\BlockCipher;

use Zend\Ldap\Ldap as LdapLdap;
use Zend\Ldap\Filter as LdapFilter;

use Els\Mvc\Controller\PublicInterface;
use Els\Log\Logger as ElsLogger;

use Auth\Ldap\Utils;

use Hr\Model\HrAccounts;
use Hr\Model\HrUsers;
use Hr\Model\HrDepts;
use Hr\Model\HrDomains;
use Hr\Model\HrGroups;
use Hr\Model\HrUserGroupLinks;
use Hr\Model\HrGroupGroupLinks;
use Hr\Model\HrUserBypass;
use Hr\Model\HrUserPictures;

use Els\Model\Els\LdapCache as ElsLdapCache;
use Els\Model\Els\LdapGroups as ElsLdapGroups;

class LoginController extends AbstractActionController implements PublicInterface {
    public function indexAction() {
		$sm = $this->getServiceLocator();
		$logger = ElsLogger::getLogger($sm);
		$config = $sm->get('Config');
		$logger->log(Logger::INFO, "Hit the indexaction of login controller");
		// make sure we use a very plain layout
		$this->layout('layout/base.phtml');
		$view = new ViewModel();
		
		$hr_accounts_mdl = HrAccounts::factory($sm);
		$hr_users_mdl = HrUsers::factory($sm);
		$hr_user_bypass_mdl = HrUserBypass::factory($sm);				
		
		if($config['auth']['decommission_url']) {
			$view->setTemplate('auth/login/decommission.phtml');
			$view->setVariables(array(
				'message' => $config['auth']['decommission_message'],
				'redirect_url' => $config['auth']['decommission_url']
			));
			
			return $view;
		}
		
		if($_REQUEST['username'] && $_REQUEST['password']) {
			// make sure the username is not prepended with brocade
			$username = str_replace("/", "\\", $_REQUEST['username']);
			$username = trim(array_pop(explode("\\", $username)));
			$_REQUEST['username'] = $username;
			
			// check if this is a bypass attempt
			$bypass_username = false;
			
			if(preg_match("/([a-zA-Z0-9]+)_([a-zA-Z0-9]+)/", trim($_REQUEST['username']), $matches)) {
				list($full, $username, $cur_bypass_username) = $matches;
				$_REQUEST['username'] = $username;
				$can_bypass = false;
				
				// the users matching the given username
				$users = $hr_users_mdl->filter(array(
					'username' => $username
				), array(), array(), $total);
				
				if($total > 0) {
					$users = $users->toArray();
					
					// the bypass users matching the given username
					$bypass_users = $hr_users_mdl->filter(array(
						'username' => $cur_bypass_username
					), array(), array(), $total);
					
					if($total > 0) {
						$bypass_users = $bypass_users->toArray();
						
						foreach($users as $user) {
							foreach($bypass_users as $bypass_user) {
								// verify if we have a valid bypass in our system for this user				
								$bypass = $hr_user_bypass_mdl->getBypass($user['id'], $bypass_user['id']);
								if($bypass !== false) {
									$bypass_username = $cur_bypass_username;
									break 2;
								}
							}
						}
					}
				}
				
				if($bypass_username === false) {
					$view->setVariables(array(
						'errors' => array('invalid bypass username')
					));
					return $view;
				}
			}
			
			// XXIONG: bypass for broadcom login
			if($_REQUEST['username'] == 'read' && $_REQUEST['password'] == 'only') {
				$logger->log(Logger::INFO, "attempting read only authentication");
				try {
					$info = array(
						'username' => 'read',
						'display_name' => 'Read Only',
						'full_name' => 'Read Only',
						'first_name' => 'Read',
						'last_name' => 'Only',
						'department_number' => '1',
						'department' => 'Read Only',
						'email' => 'read-only@brocade.com',
						'picture' => 'images/employees-static/unknown.jpg',
						'groups' => '',
						'employee_id' => 'ELS003',
						'supervisor_dn' => '',
						'organization' => '',
						'domain' => 'brocade.com'
					);
					
					$auth_storage = $sm->get('auth_storage');
					
					list($user_id, $department_id) = $this->storeUser($info);
					$info['user_id'] = $user_id;
					$info['department_id'] = $department_id;
					
					// grab the session container
					$session = $sm->get('session');
					
					// 10/30/2017 - XXIONG, swap the username with the user-id
					$auth_storage->write($info['user_id']);
					
					// set the user info in session
					$session->userinfo = $info;
				} catch(\Exception $e) {
					$previous = $e->getPrevious();
					if(!empty($previous)) {
						$e = $previous;
					}
					
					// do nothing
					$logger->log(Logger::ERR, "unable to cache read-only ldap user : " . $e->getMessage());
				}
				
				// log the permission server for sso
				$this->sso()->setPermissionServer();
				
				// trigger loginSuccess event here
				$results = $this->getEventManager()->trigger('login-success', $this, array(
					'user_id' => $info['user_id'],
					'info' => $info
				));
				
				$redirected = false;
				$redirect_url = $this->params()->fromQuery('url');
				if(!empty($redirect_url)) {
					// we should redirect here instead
					$this->authentication()->redirectToLandingPage(null, $redirect_url);
					$redirected = true;
				}
				
				if(!$redirected) {
					// now let's see if anyone wants us to redirect to their page first, if not we will go to our default landing page
					foreach($results as $result) {
						if(is_array($result) && $result['landing']) {
							$this->authentication()->redirectToLandingPage($result['landing']['route'], $result['landing']['url']);
							$redirected = true;
						}
					}
				}
				
				if(!$redirected) {
					// if we are still here, we will just redirect to the main page
					$this->authentication()->redirectToLandingPage();
				}
			}
			// local db login
			else if($this->localLogin($_REQUEST['username'], $_REQUEST['password'])) {
				try {
					$account = $hr_accounts_mdl->get(array(
						'username' => $_REQUEST['username']
					));
					
					$info = array(
						'username' => $account->username,
						'display_name' => "{$account->first_name} {$account->last_name}",
						'full_name' => "{$account->first_name} {$account->last_name}",
						'first_name' => $account->first_name,
						'last_name' => $account->last_name,
						'department_number' => '100000',
						'department' => 'Local Account',
						'email' => $account->email,
						'picture' => 'images/employees-static/unknown.jpg',
						'groups' => '',
						'employee_id' => '',
						'supervisor_dn' => '',
						'organization' => '',
						'domain' => 'local.local'
					);
					
					$info['groups'] = Utils::normalizeGroups($info['groups']);
					$info['username'] = strtolower($info['username']);
					$info['email'] = strtolower($info['email']);
					
					list($user_id, $department_id) = $this->storeUser($info);
					$info['user_id'] = $user_id;
					$info['department_id'] = $department_id;
					
					// grab the session container
					$session = $sm->get('session');
					// grab the authentication storage
					$auth_storage = $sm->get('auth_storage');
					
					// 10/30/2017 - XXIONG, swap the username with the user-id
					$auth_storage->write($info['user_id']);
					
					// set the user info in session
					$session->userinfo = $info;
				} catch(\Exception $e) {
					$previous = $e->getPrevious();
					if(!empty($previous)) {
						$e = $previous;
					}
					
					// do nothing
					$logger->log(Logger::ERR, "unable to cache local user : " . $e->getMessage());
					$logger->log(Logger::ERR, $e->getTraceAsString());
				}
				
				// log the permission server for sso
				$this->sso()->setPermissionServer();
				
				// trigger loginSuccess event here
				$results = $this->getEventManager()->trigger('login-success', $this, array(					
					'user_id' => $info['user_id'],
					'info' => $info
				));
				
				$redirected = false;
				$redirect_url = $this->params()->fromQuery('url');
				if(!empty($redirect_url)) {
					// we should redirect here instead
					$this->authentication()->redirectToLandingPage(null, $redirect_url);
					$redirected = true;
				}
				
				if(!$redirected) {
					// now let's see if anyone wants us to redirect to their page first, if not we will go to our default landing page
					foreach($results as $result) {
						if(is_array($result) && $result['landing']) {
							$this->authentication()->redirectToLandingPage($result['landing']['route'], $result['landing']['url']);
							$redirected = true;
						}
					}
				}
				
				if(!$redirected) {
					// if we are still here, we will just redirect to the main page
					$this->authentication()->redirectToLandingPage();
				}
			}
			// defaut login
			else if($this->authentication()->authenticate($_REQUEST['username'], $_REQUEST['password'])) {
				try {
					$info = $this->authentication()->getAuthenticatedLdapUserInfo($this->getLdapFields());
					
					$info['groups'] = Utils::normalizeGroups($info['groups']);
					$info['username'] = strtolower($info['username']);
					$info['email'] = strtolower($info['email']);
					$info['domain'] = $this->resolveDomain($info['email']);
					
					list($user_id, $department_id) = $this->storeUser($info);
					$info['user_id'] = $user_id;
					$info['department_id'] = $department_id;
					$info['picture'] = $this->storePicture($info['picture'], $info['user_id']);
					
					// grab the session container
					$session = $sm->get('session');
					// grab the authentication storage
					$auth_storage = $sm->get('auth_storage');
					
					// now if we had a bypass, we need to load this user's information as well
					if(!empty($bypass_username)) {
						try {
							$info = $this->loadBypassUser($bypass_username);
						} catch(\Exception $e) {
							// unable to load the bypass user, we must fake some of the info
							$hr_user = $hr_users_mdl->get(array(
								'username' => $bypass_username
							));
							
							if ($hr_user !== false) {
								$info = $this->sso()->loadUserInfo($hr_user->id);
								
								if($info === false) {
									// invalid user provided
									$view->setVariables(array(
										'errors' => array("Invalid bypass user")
									));
									return $view;
								}
							}
						}
						
						$info['groups'] = Utils::normalizeGroups($info['groups']);
						$info['email'] = strtolower($info['email']);
						$info['domain'] = $this->resolveDomain($info['email']);
						list($user_id, $department_id) = $this->storeUser($info);
						$info['user_id'] = $user_id;
						$info['department_id'] = $department_id;
						$info['picture'] = $this->storePicture($info['picture'], $info['user_id']);	
					}
					
					// 10/30/2017 - XXIONG, swap the username with the user-id
					$auth_storage->write($info['user_id']);
					
					// set the user info in session
					$session->userinfo = $info;
				} catch(\Exception $e) {
					$previous = $e->getPrevious();
					if(!empty($previous)) {
						$e = $previous;
					}
					
					// do nothing
					$logger->log(Logger::ERR, "unable to cache ldap user : " . $e->getMessage());
					$logger->log(Logger::ERR, $e->getTraceAsString());
				}
				
				// log the permission server for sso
				$this->sso()->setPermissionServer();
				
				// trigger loginSuccess event here
				$results = $this->getEventManager()->trigger('login-success', $this, array(					
					'user_id' => $info['user_id'],
					'info' => $info
				));
				
				$logger->log(Logger::DEBUG, print_r($results, true));
				
				$redirected = false;
				$redirect_url = $this->params()->fromQuery('url');
				if(!empty($redirect_url)) {
					// we should redirect here instead
					$this->authentication()->redirectToLandingPage(null, $redirect_url);
					$redirected = true;
				}
				
				if(!$redirected) {
					// now let's see if anyone wants us to redirect to their page first, if not we will go to our default landing page
					foreach($results as $result) {
						if(is_array($result) && $result['landing']) {
							$this->authentication()->redirectToLandingPage($result['landing']['route'], $result['landing']['url']);
							$redirected = true;
						}
					}
				}
				
				if(!$redirected) {
					// if we are still here, we will just redirect to the main page
					$this->authentication()->redirectToLandingPage();
				}
			} else {
				$errors = $this->authentication()->errorMessages();
				if(count($errors) > 0) {
					$view->setVariables(array(
						'errors' => array($errors[0])
					));
				}
			}
		}
        
        return $view;
    }
	
	protected function getLdapFields() {
		$sm = $this->getServiceLocator();
		$config = $sm->get('Config');
		
		$fields = $config['auth']['ldap_user_field_mapping'];
		
		if(empty($fields)) {
			$fields = array(
				'display_name' => 'displayname',
				'first_name' => 'givenname',
				'last_name' => 'sn',
				'full_name' => 'name',
				'username' => 'samaccountname',
				'email' => 'mail',
				'phone' => 'telephonenumber',
				'department_number' => 'extensionattribute2',
				'department' => 'department',
				'groups' => 'memberof',
				'picture' => 'thumbnailphoto',
				'created' => 'whencreated',
				'changed' => 'whenchanged',
				'employee_id' => 'extensionattribute1',
				'supervisor_dn' => 'manager',
				'organization' => 'extensionattribute6'
			);
		}
		
		return $fields;
	}
	
	protected function getLdapFlatFields() {
		return $this->authentication()->flattenField($this->getLdapFields());
	}
	
	protected function storeUser($info, $recurse = true) {
		$sm = $this->getServiceLocator();
		$config = $sm->get('Config');
		$logger = $sm->get('logger');
		
		$hr_users_mdl = HrUsers::factory($sm);
		$hr_depts_mdl = HrDepts::factory($sm);
		$hr_domains_mdl = HrDomains::factory($sm);
		$hr_groups_mdl = HrGroups::factory($sm);
		$hr_user_group_links_mdl = HrUserGroupLinks::factory($sm);
		$hr_group_group_links_mdl = HrGroupGroupLinks::factory($sm);
		$hr_user_pictures_mdl = HrUserPictures::factory($sm);
		
		$user_id = null;
		$domain_id = null;
		$domain = $info['domain'];
		
		if(!empty($domain)) {
			$row = $hr_domains_mdl->get(array('name' => $domain));
			if($row === false) {
				$domain_id = $hr_domains_mdl->insert(
					$domain
				);
			} else {
				$domain_id = $row->id;
			}
		}
		
		$department_id = null;
		$department = null;
		
		if(!empty($info['department_number']) || !empty($info['department'])) {
			$filter = array(
				'domain_id' => $domain_id
			);
			if(!empty($info['department_number'])) {
				$filter['number'] = $info['department_number'];
			}
			if(!empty($info['department'])) {
				$filter['name'] = $info['department'];
			}
			
			$row = $hr_depts_mdl->get($filter);
			if($row !== false) {
				$department_id = $row->id;
				$department = $row->display_name;
			} else {
				$department = $info['department'];
				if(!empty($info['department_number'])) {
					$department = "{$info['department_number']} {$department}";
				}
				
				$department_id = $hr_depts_mdl->insert(
					// number
					$info['department_number'],
					// name
					$info['department'],
					// display_name
					$department,
					// dept_head_id
					null,
					// dept_head
					null,
					// dept_director_id
					null,
					// dept_director
					null,
					// dept_vp_id
					null,
					// dept_vp
					null,
					// executive_vp_id
					null,
					// executive_vp
					null,
					// aor_id
					null,
					// aor
					null,
					// organization
					$info['organization'],
					// domain_id
					$domain_id
				);
			}
		}
		
		$row = $hr_users_mdl->get(array(
			'username' => $info['username'],
			'domain_id' => $domain_id
		));
		
		$need_supervisor = false;
		
		if($row === false) {
			$need_supervisor = true;
			
			$user_id = $hr_users_mdl->insert(
				// number
				$info['employee_id'],
				// username
				$info['username'],
				// email
				$info['email'],
				// phone
				$info['phone'],
				// display_name
				$info['display_name'],
				// full_name
				$info['full_name'],
                // imdb_name,
                "{$info['last_name']}, {$info['first_name']}",
				// first_name
				$info['first_name'],
				// last_name
				$info['last_name'],
				// department_id
				$department_id,
				// department
				$department,
				// supervisor_id
				null,
				// supervisor
				null,
				// dept_head_id
				null,
				// dept_head
				null,
				// dept_director_id
				null,
				// dept_director
				null,
				// dept_vp_id
				null,
				// dept_vp
				null,
				// executive_vp_id
				null,
				// executive_vp
				null,
				// aor_id
				null,
				// aor
				null,
				// organization
				$info['organization'],
				// domain_id
				$domain_id
			);
		} else {
			$user_id = $row->id;
			
			if(empty($row->supervisor_id)) {
				$need_supervisor = true;
			}
			
			$updates = array(
				'number' => $info['employee_id'],
				'username' => $info['username'],
				'email' => $info['email'],
				'phone' => $info['phone'],
				'display_name' => $info['display_name'],
				'full_name' => $info['full_name'],
				'first_name' => $info['first_name'],
				'last_name' => $info['last_name'],
				'organization' => $info['organization']
			);
			
			if (!empty($department_id)) {
				$updates['department_id'] = $department_id;
				$updates['department'] = $department;
			}
			if (!empty($domain_id)) {
				$updates['domain_id'] = $domain_id;
			}
			
			$hr_users_mdl->update($row->id, $updates);
		}
		
		if (!empty($user_id) && !empty($info['supervisor_dn']) && $need_supervisor) {
			$supervisor_id = null;
			$supervisor = null;
			
			// let's resolve the supervisor
			if(!empty($info['supervisor_dn'])) {
				$supervisor_info = $this->loadUserByDn($info['supervisor_dn']);
				if(!empty($supervisor_info)) {
					if(!empty($supervisor_info['username'])) {
						$attempts = 0;
						do {
							// check if the supervisor exists in our system
							$supervisor_row = $hr_users_mdl->get(array(
								'username' => $supervisor_info['username'],
								'domain_id' => $domain_id
							));
							
							if ($supervisor_row) {
								$supervisor_id = $supervisor_row->id;
								$supervisor = $supervisor_row->display_name;
								break;
							} else if ($recurse) {
								// store the supervisor's info, without recursing into the supervisor's supervisor
								$supervisor_info['domain'] = $this->resolveDomain($supervisor_info['email']);
								$supervisor_info['groups'] = Utils::normalizeGroups($supervisor_info['groups']);
								$this->storeUser($supervisor_info, false);
								$attempts++;
							} else {
								break;
							}
						} while($attempts < 2);
					}
				}
			}
			
			if(!empty($supervisor_id)) {
				$hr_users_mdl->update($user_id, array(
					'supervisor_id' => $supervisor_id,
					'supervisor' => $supervisor
				));
			}
		}
		
		if (!empty($user_id) && !empty($info['picture'])) {
			$picture_hash = md5($info['picture']);
			
			$row = $hr_user_pictures_mdl->get(array(
				'user_id' => $user_id,
				'hash' => $picture_hash
			));
			
			if($row === false) {
				$hr_user_pictures_mdl->insert(
					// user_id
					$user_id,
					// picture
					$info['picture'],
					// hash
					$picture_hash
				);
			}
		}
		
		// for backward compatibility with Elstools V1 we should insert it here too
		/*
		$els_ldap_cache_mdl = new ElsLdapCache($sm->get('db'));
		$els_ldap_groups_mdl = new ElsLdapGroups($sm->get('db'));
		$row = $els_ldap_cache_mdl->get(array(
			'username' => $info['username'], 
			'email' => $info['email']
		));
		
		if($row === false) {
			$els_ldap_cache_mdl->insert(
				$info['username'],
				$info['groups'],
				$info['display_name'],
				$info['full_name'],
				$info['email'],
				$info['phone'],
				$info['department'],
				intval($info['department_number'])
			);
		} else {
			$els_ldap_cache_mdl->update(array(
				'username' => $info['username'],
				'email' => $info['email']
			), array(
				'groups' => $info['groups'],
				'displayname' => $info['display_name'],
				'name' => $info['full_name'],
				'phone' => $info['phone'],
				'department' => $info['department'],
				'ext_attr2' => intval($info['department_number'])
			));
		}
		*/
		
		// process the ldap groups
		$processed_groups = array();
		$to_process = array();
		
		if(is_array($info['groups'])) {
			// insert these groups
			foreach($info['groups'] as $group) {
				$hr_group = $hr_groups_mdl->get(array(
					'name' => $group,
					'domain_id' => $domain_id
				));
				if($hr_group === false) {
					$group_id = $hr_groups_mdl->insert(
						// name
						$group,
						// domain_id
						$domain_id
					);
				} else {
					$group_id = $hr_group->id;
				}
				
				$to_process[] = $group_id;
				
				if(!$hr_user_group_links_mdl->exists(array(
					'user_id' => $user_id,
					'group_id' => $group_id
				))) {
					$hr_user_group_links_mdl->insert(
						$user_id,
						$group_id
					);
				} else {
					$hr_user_group_links_mdl->update(array(
						'user_id' => $user_id,
						'group_id' => $group_id
					), array(
						'ctime' => new Expression('NOW()')
					));
				}
			}
		}
		
		while(count($to_process) > 0) {
			$parent_group_id = array_shift($to_process);
			if($processed_groups[$parent_group_id] === true) {
				continue;
			}
			
			$parent_group_links = $hr_group_group_links_mdl->filter(array(
				'child_group_id' => $parent_group_id
			), array(), array(), $total);
			
			foreach($parent_group_links as $parent_group_link) {
				$link = $hr_user_group_links_mdl->get(array(
					'user_id' => $user_id,
					'group_id' => $parent_group_link->group_id
				));
				
				if($link === false) {
					// insert this link
					$hr_user_group_links_mdl->insert(
						// user_id
						$user_id,
						// group_id
						$parent_group_link->group_id
					);
				} else {
					// update the ctime for this link so that we can later use it for cleaning purpose
					$hr_user_group_links_mdl->update(array(
						'user_id' => $user_id,
						'group_id' => $parent_group_link->group_id
					), array(
						'ctime' => new Expression('NOW()')
					));
				}
				
				if($processed_groups[$parent_group_link->group_id] !== true) {
					$to_process[] = $parent_group_link->group_id;
				}
			}
			
			$processed_groups[$parent_group_id] = true;
		}
		
		return array($user_id, $department_id);
	}
		
	protected function storePicture($picture, $user_id) {
		// save the picture in public/images/employees
		if(!empty($picture)) {
			$dir = "{$_SERVER['DOCUMENT_ROOT']}/images/employees";
			if(!is_dir($dir)) {
				@mkdir($dir, 0777, true);
			}
			$imgfile = "{$dir}/{$user_id}.jpg";
			file_put_contents($imgfile, $picture);
			return "images/employees/{$user_id}.jpg";
		} else {
			return "images/employees-static/unknown.jpg";
		}
	}
	
	protected function resolveDomain($email) {
		$sm = $this->getServiceLocator();
		$config = $sm->get('Config');
		
		$domain = 'unknown';
		if(!empty($email) && strpos($email, '@') > -1) {
			list($ignore, $domain) = explode('@', $email);
		}
		
		if($domain == 'unknown') {
			if(!empty($config['auth']['default_domain'])) {
				$domain = $config['auth']['default_domain'];
			}
		}
		
		return $domain;
	}
	
	protected function loadBypassUser($username) {
		$sm = $this->getServiceLocator();
		$config = $sm->get('Config');
		
		$aldap = $sm->get($config['auth']['adapter']);		
		$ldap = $aldap->getLdap();
		
		$dn = $ldap->getCanonicalAccountName($username, LdapLdap::ACCTNAME_FORM_DN);
		return $this->loadUserByDn($dn);
	}
	
	protected function loadUserByDn($dn) {
		$sm = $this->getServiceLocator();
		$config = $sm->get('Config');
		
		$fields = $this->getLdapFields();
		$aldap = $sm->get($config['auth']['adapter']);
		
		$ldap = $aldap->getLdap();		
		$entry = $ldap->getEntry($dn, $this->getLdapFlatFields());
		
		$rawinfo = new \stdClass();
		foreach($entry as $attr => $value) {
			if(is_array($value)) {
				$rawinfo->$attr = (count($value) > 1) ? $value : $value[0];
			} else {
				$rawinfo->$attr = $value;
			}
		}
		
		$info = array();
		foreach($fields as $name => $field) {
			if(is_array($field)) {
				foreach($field as $f) {
					$v = $rawinfo->$f;
					if(!empty($v)) {
						$info[$name] = $v;
						break;
					}
				}
			} else {
				$info[$name] = $rawinfo->$field;
			}
		}
		
		return $info;
	}
	
	protected function localLogin($username, $password) {
		$sm = $this->getServiceLocator();
		$config = $sm->get('Application\Config');
		
		$hr_accounts_mdl = HrAccounts::factory($sm);
		
		$salt = $config['salt'];
		$blockCipher = BlockCipher::factory('mcrypt', array(
			'algo' => 'aes'
		));
		$blockCipher->setKey($salt);
		
		$account = $hr_accounts_mdl->get(array(
			'username' => $username
		));
		
		if ($account !== false) {
			$decrypted_password = $blockCipher->decrypt($account->password);
			if ($decrypted_password == $password) {
				return true;
			}
		}
		
		return false;
	}
}
