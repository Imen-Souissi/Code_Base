<?php
namespace Auth\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Log\Logger;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Where;
use Zend\View\Model\ConsoleModel;
use Zend\Server\Reflection;
use Zend\Json\Json;

use Zend\Authentication\Adapter\Ldap as AdapterLdap;
use Zend\Ldap\Ldap as LdapLdap;
use Zend\Ldap\Filter as LdapFilter;

use Hr\Model\HrUsers;
use Hr\Model\HrGroups;
use Hr\Model\HrUserGroupLinks;
use Hr\Model\HrGroupGroupLinks;
use Hr\Model\HrDomains;
use Hr\Model\HrDepts;
use Hr\Model\HrUserPictures;

use Auth\Ldap\Utils;

class ScriptController extends AbstractActionController {
	protected $error;
	
	protected function getUserFields() {
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
	
	protected function getUserFlatFields() {
		return $this->authentication()->flattenField($this->getUserFields());
	}
	
	protected function getGroupFields() {
		$sm = $this->getServiceLocator();
		$config = $sm->get('Config');
		
		$fields = $config['auth']['ldap_group_field_mapping'];
		
		if(empty($fields)) {
			$fields = array(
				'display_name' => 'displayname',
				'name' => 'cn',
				'email' => 'mail',
				'members' => 'member',
				'created' => 'whencreated',
				'changed' => 'whenchanged'
			);
		}
		
		return $fields;
	}
	
	protected function getGroupFlatFields() {
		return $this->authentication()->flattenField($this->getGroupFields());
	}
	
	public function loadAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('console_logger');
		
		$user = $this->params()->fromRoute('user');
		$username = $this->params()->fromRoute('username');
		$password = $this->params()->fromRoute('password');
		
		$result = new ConsoleModel();		
		
		if(empty($user)) {
			$result->setResult("please specify a user to load\n");
			$result->setErrorLevel(1);
			return $result;
		}
		
		if(empty($username)) {
			$result->setResult("please specify a bind username\n");
			$result->setErrorLevel(1);
			return $result;
		}
		
		if(empty($password)) {
			$result->setResult("please specify a bind password\n");
			$result->setErrorLevel(1);
			return $result;
		}
		
		try {
			// grab the ldap configuration
			$config = $this->getServiceLocator()->get('Config');
			
			$ldapoptions = $config['authentication']['ldap'];
			
			$aldap = new AdapterLdap($ldapoptions);
			$aldap->setIdentity($username);
			$aldap->setCredential($password);
			
			$response = $aldap->authenticate();
			if($response->isValid()) {
				$ldap = $aldap->getLdap();
				
				$info = $this->load($ldap, $user);
				if($info === false) {
					$logger->log(Logger::WARN, "unable to locate username {$user} in Ldap");
					continue;
				}
				
				$logger->log(Logger::INFO, "importing user " . $info['username']);
				if(!$this->import($ldap, $info)) {
					$logger->log(Logger::ERR, "unable to import " . $info['username'] . " : " . $this->error);
					continue;
				}
			} else {
				throw new \Exception("invalid username/password");
			}
		} catch(\Exception $e) {
			$result->setResult("unable to process ldap load : " . $e->getMessage() . "\n");
			$result->setErrorLevel(1);
		}
		
		return $result;
	}
	
	public function loadAllAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('console_logger');
		
		$username = $this->params()->fromRoute('username');
		$password = $this->params()->fromRoute('password');
		
		$start = $this->params()->fromRoute('start');
		$end = $this->params()->fromRoute('end');
		
		if(empty($start) || !preg_match("/[A-Z]/", $start)) {
			$start = 'A';
		}
		
		if(empty($end) || !preg_match("/[A-Z]/", $end)) {
			$end = 'Z';
		}
		
		$cutoff = $this->params()->fromRoute('cutoff');
		if(!empty($cutoff)) {
			$cutoff = strtotime($cutoff);
			if($cutoff === false) {
				throw new \Exception("invalid cutoff time provided");
			}
			
			// format the cutoff time to be in the format 20160302121150.0Z
			//		YYYYmmddHHiiss.0Z
			
			$cutoff = date('YmdHis', $cutoff);
			$cutoff = "{$cutoff}.0Z";
		}
		
		$size = 1000;
		
		$result = new ConsoleModel();
		
		if(empty($username)) {
			$result->setResult("please specify a bind username\n");
			$result->setErrorLevel(1);
			return $result;
		}
		
		if(empty($password)) {
			$result->setResult("please specify a bind password\n");
			$result->setErrorLevel(1);
			return $result;
		}
		
		try {
			// grab the ldap configuration
			$config = $this->getServiceLocator()->get('Config');
			$db = $this->getServiceLocator()->get('db');
			
			$ldapoptions = $config['authentication']['ldap'];
			
			$aldap = new AdapterLdap($ldapoptions);
			$aldap->setIdentity($username);
			$aldap->setCredential($password);
			
			$response = $aldap->authenticate();
			if($response->isValid()) {
				$ldap = $aldap->getLdap();
				
				$total_processed = 0;
				
				// we have to process the names in alphabetical sets in order to get around the paging limitation
				foreach(range($start, $end) as $c) {
					$logger->log(Logger::INFO, "processing users starting with {$c}");
					
					// we will just query ldap for all account information
					$filter = \Zend\Ldap\Filter::andFilter(
						/*
						\Zend\Ldap\Filter::orFilter(
							// Employee
							//\Zend\Ldap\Filter::equals('employeeType', 'Y'),
							// Contractor
							//\Zend\Ldap\Filter::equals('employeeType', 'N')							
						),
						*/
						// Person
						\Zend\Ldap\Filter::equals('objectClass', 'person'),
						// Name starts with
						\Zend\Ldap\Filter::begins('givenName', $c)
					);
					
					if(!empty($cutoff)) {
						// only get groups that are newer
						$filter = $filter->addFilter(\Zend\Ldap\Filter::greaterOrEqual('whenchanged', $cutoff));
					}
					
					$logger->log(Logger::INFO, "setting filter to " . $filter->toString());
					
					$attempts = 0;
					
					do {
						try {
							$searches = $ldap->search(
								$filter,
								null,
								\Zend\Ldap\Ldap::SEARCH_SCOPE_SUB,
								$this->getUserFlatFields(),
								// sort
								'whencreated',
								// collectionClass
								null,
								// sizeLimit
								$size
							);
							
							$logger->log(Logger::INFO, "found " . $searches->count() . " users in {$c}");
							if($searches->count() == $size) {
								$logger->log(Logger::INFO, "processing incremental {$size} sets base on whenCreated");
								
								do {
									$found = $searches->count();
									$last_created_date_processed = null;
									
									foreach($searches as $entry) {
										$username = $entry['samaccountname'];
										if(is_array($username)) {
											$username = $username[0];
										}
										
										if(empty($username)) {
											continue;
										}
										
										$info = $this->load($ldap, $username, $entry);
										if($info === false) {
											$logger->log(Logger::WARN, "unable to locate username {$username} in Ldap");
											continue;
										}
										
										if(empty($info['username'])) {
											$logger->log(Logger::DEBUG, print_r($entry, true));
											continue;
										}
										
										$logger->log(Logger::INFO, "loading user " . $info['username']);
										if(!$this->import($ldap, $info)) {
											$logger->log(Logger::ERR, "unable to import " . $info['username'] . " : " . $this->error);
											continue;
										}								
										
										if(is_array($entry['whencreated'])) {
											$last_created_date_processed = $entry['whencreated'][0];
										} else {
											$last_created_date_processed = $entry['whencreated'];
										}
										$total_processed++;
									}
									
									// let's require for all records created after the last created date
									if(empty($last_created_date_processed)) {
										// we will just break since we have not date to base off
										break;
									}
									
									if($found < $size) {
										// we have already processed the last round, we need to stop
										break;
									}
									
									// NOTE: XXIONG(03/04/2016), we will use greaterOrEqual to make sure we get everything, including records with the same whencreated
									$newfilter = $filter->addFilter(
										// greater or equal
										\Zend\Ldap\Filter::greaterOrEqual('whencreated', $last_created_date_processed)
									);
									$logger->log(Logger::INFO, "setting next filter to " . $newfilter->toString());
									
									$searches = $ldap->search(
										$newfilter,
										null,
										\Zend\Ldap\Ldap::SEARCH_SCOPE_SUB,
										$this->getGroupFlatFields(),
										// sort
										'whencreated',
										// collectionClass
										null,
										// sizeLimit
										$size
									);
									
									$logger->log(Logger::INFO, "found another set of " . $searches->count() . " searches");
								} while($searches->count() > 0);
							} else {
								foreach($searches as $entry) {
									$username = $entry['samaccountname'];
									if(is_array($username)) {
										$username = $username[0];
									}
									
									if(empty($username)) {
										continue;							
									}
									
									$info = $this->load($ldap, $username, $entry);
									if($info === false) {
										$logger->log(Logger::WARN, "unable to locate username {$employee->username} in Ldap");
										continue;
									}
									
									$logger->log(Logger::INFO, "loading user " . $info['username']);
									if(!$this->import($ldap, $info)) {
										$logger->log(Logger::ERR, "unable to import " . $info['username'] . " : " . $this->error);
										continue;
									}
									
									$total_processed++;
								}
							}
							
							break;
						} catch(\Exception $e) {
							$attempts++;
							$logger->log(Logger::ERR, "failed attempt {$attempts}, retrying");
							sleep(10);
						}
						
						if($attempts > 5) {
							break;
						}
					} while(true);
				}
				
				$result->setResult("successfully loaded all {$total_processed} ldap users\n");
			} else {
				throw new \Exception("invalid username/password");
			}
		} catch(\Exception $e) {
			throw $e;
		
			$p = $e->getPrevious();
			if($p) {
				$e = $p;
			}			
			
			$result->setResult("unable to process ldap load : " . $e->getMessage() . "\n");
			$result->setErrorLevel(1);
		}
		
		return $result;
	}
	
	protected function load($ldap, $user, $entry = false) {
		$dn = null;
		
		if($entry === false) {
			try {
				$dn = $ldap->getCanonicalAccountName($user, LdapLdap::ACCTNAME_FORM_DN);
			} catch(\Exception $e) {
				return false;
			}
		}
		
		return $this->loadByDn($ldap, $dn, $entry);
	}
	
	protected function loadByDn($ldap, $dn, $entry = false) {
		$fields = $this->getUserFields();
		
		if($entry === false) {
			$entry = $ldap->getEntry($dn, $this->getUserFlatFields());
		}
		
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
	
	protected function import($ldap, $info, $recurse = true) {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		
		$hr_users_mdl = HrUsers::factory($sm);
		$hr_groups_mdl = HrGroups::factory($sm);
		$hr_user_group_links_mdl = HrUserGroupLinks::factory($sm);
		$hr_group_group_links_mdl = HrGroupGroupLinks::factory($sm);
		$hr_domains_mdl = HrDomains::factory($sm);
		$hr_depts_mdl = HrDepts::factory($sm);
		$hr_user_pictures_mdl = HrUserPictures::factory($sm);
		
		$hr_users_mdl->setAllowFilterExpression(false);
		$hr_groups_mdl->setAllowFilterExpression(false);
		$hr_domains_mdl->setAllowFilterExpression(false);
		$hr_depts_mdl->setAllowFilterExpression(false);
		
		$info['username'] = strtolower($info['username']);
		$info['email'] = strtolower($info['email']);
		$info['domain'] = $this->resolveDomain($info['email']);
		$info['groups'] = Utils::normalizeGroups($info['groups']);		
		
		try {
			$user_id = null;
			$domain_id = null;
			$domain = $info['domain'];
			
			// resolve the domain
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
			
			$row = $hr_users_mdl->get(array(
				'username' => $info['username'],
				'domain_id' => $domain_id
			));
			
			if($row === false) {
				// check if the department exists and needs to be loaded
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
					}
				}
				
				$supervisor_id = null;
				$supervisor = null;
				
				// let's resolve the supervisor
				if(!empty($info['supervisor_dn'])) {
					$supervisor_info = $this->loadByDn($ldap, $info['supervisor_dn']);
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
									$this->import($ldap, $supervisor_info, false);
									$attempts++;
								} else {
									break;
								}
							} while($attempts < 2);
						}
					}
				}
				
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
					$supervisor_id,
					// supervisor
					$supervisor,
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
				if (!empty($supervisor_id)) {
					$updates['supervisor_id'] = $supervisor_id;
					$updates['supervisor'] = $supervisor;
				}
				if (!empty($domain_id)) {
					$updates['domain_id'] = $domain_id;
				}
				
				$hr_users_mdl->update($row->id, $updates);
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
		} catch(\Exception $e) {
			$p = $e->getPrevious();
			if($p) {
				$e = $p;
			}
			
			$this->error = $e->getMessage();
			
			return false;
		}
		
		return true;
	}
	
	public function loadGroupAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('console_logger');
		
		$group = $this->params()->fromRoute('group');
		$username = $this->params()->fromRoute('username');
		$password = $this->params()->fromRoute('password');
		
		$result = new ConsoleModel();		
		
		if(empty($group)) {
			$result->setResult("please specify a group to load\n");
			$result->setErrorLevel(1);
			return $result;
		}
		
		if(empty($username)) {
			$result->setResult("please specify a bind username\n");
			$result->setErrorLevel(1);
			return $result;
		}
		
		if(empty($password)) {
			$result->setResult("please specify a bind password\n");
			$result->setErrorLevel(1);
			return $result;
		}
		
		try {
			// grab the ldap configuration
			$config = $this->getServiceLocator()->get('Config');
			
			$ldapoptions = $config['authentication']['ldap'];
			
			$aldap = new AdapterLdap($ldapoptions);
			$aldap->setIdentity($username);
			$aldap->setCredential($password);
			
			$response = $aldap->authenticate();
			if($response->isValid()) {
				$ldap = $aldap->getLdap();
				
				$info = $this->loadGroup($ldap, $group);
				if($info === false) {
					$logger->log(Logger::WARN, "unable to locate group {$group} in Ldap");
					return $result;
				}
				
				$logger->log(Logger::INFO, "importing group " . $info['name']);
				if(!$this->importGroup($info)) {
					$logger->log(Logger::ERR, "unable to import " . $info['name'] . " : " . $this->error);
					return $result;
				}
			} else {
				throw new \Exception("invalid username/password");
			}
		} catch(\Exception $e) {
			$result->setResult("unable to process ldap load group : " . $e->getMessage() . "\n");
			$result->setErrorLevel(1);
		}
		
		return $result;
	}
	
	public function loadAllGroupAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('console_logger');
		
		$hr_users_mdl = HrUsers::factory($sm);
		$hr_groups_mdl = HrGroups::factory($sm);
		
		$username = $this->params()->fromRoute('username');
		$password = $this->params()->fromRoute('password');
		
		$start = $this->params()->fromRoute('start');
		$end = $this->params()->fromRoute('end');
		
		if(empty($start) || !preg_match("/[A-Z]/", $start)) {
			$start = 'A';
		}
		
		if(empty($end) || !preg_match("/[A-Z]/", $end)) {
			$end = 'Z';
		}
		
		$cutoff = $this->params()->fromRoute('cutoff');
		if(!empty($cutoff)) {
			$cutoff = strtotime($cutoff);
			if($cutoff === false) {
				throw new \Exception("invalid cutoff time provided");
			}
			
			// format the cutoff time to be in the format 20160302121150.0Z
			//		YYYYmmddHHiiss.0Z
			
			$cutoff = date('YmdHis', $cutoff);
			$cutoff = "{$cutoff}.0Z";
		}
		
		$size = 1000;
		
		$result = new ConsoleModel();
		
		if(empty($username)) {
			$result->setResult("please specify a bind username\n");
			$result->setErrorLevel(1);
			return $result;
		}
		
		if(empty($password)) {
			$result->setResult("please specify a bind password\n");
			$result->setErrorLevel(1);
			return $result;
		}
		
		//$logger->log(Logger::DEBUG, "username: {$username}, password: {$password}, hex: " . bin2Hex($password));
		
		try {
			// grab the ldap configuration
			$config = $this->getServiceLocator()->get('Config');
			$db = $this->getServiceLocator()->get('db');
			
			$ldapoptions = $config['authentication']['ldap'];
			
			$aldap = new AdapterLdap($ldapoptions);
			$aldap->setIdentity($username);
			$aldap->setCredential($password);
			
			$response = $aldap->authenticate();
			if($response->isValid()) {
				$ldap = $aldap->getLdap();
				
				$total_processed = 0;
				
				// we have to process the names in alphabetical sets in order to get around the paging limitation
				foreach(range($start, $end) as $c) {
					$logger->log(Logger::INFO, "processing groups starting with {$c}");
					
					// we will just query ldap for all group information
					$filter = \Zend\Ldap\Filter::andFilter(
						// Group
						\Zend\Ldap\Filter::equals('objectClass', 'group'),
						// Name starts with
						\Zend\Ldap\Filter::begins('name', $c)
					);
					
					if(!empty($cutoff)) {
						// only get groups that are newer
						$filter = $filter->addFilter(\Zend\Ldap\Filter::greaterOrEqual('whenchanged', $cutoff));
					}
					
					$logger->log(Logger::INFO, "setting filter to " . $filter->toString());
					
					$attempts = 0;
					
					do {
						try {
							$searches = $ldap->search(
								$filter,
								null,
								\Zend\Ldap\Ldap::SEARCH_SCOPE_SUB,
								$this->getGroupFlatFields(),
								// sort
								'whencreated',
								// collectionClass
								null,
								// sizeLimit
								$size
							);
							
							$logger->log(Logger::INFO, "found " . $searches->count() . " groups in {$c}");
							if($searches->count() == $size) {
								$logger->log(Logger::INFO, "processing incremental {$size} sets base on whenCreated");
								
								do {
									$found = $searches->count();
									$last_created_date_processed = null;
									
									foreach($searches as $entry) {
										$name = $entry['cn'];
										if(is_array($name)) {
											$name = array_shift($name);
										}
										
										$info = $this->loadGroup($ldap, $name, $entry);
										if($info === false) {
											$logger->log(Logger::WARN, "unable to load group {$name} in Ldap");
											continue;
										}
										
										$logger->log(Logger::INFO, "loading group " . $info['name']);
										if(!$this->importGroup($info)) {
											$logger->log(Logger::ERR, "unable to import group " . $info['name'] . " : " . $this->error);
											continue;
										}
										
										if(is_array($entry['whencreated'])) {
											$last_created_date_processed = $entry['whencreated'][0];
										} else {
											$last_created_date_processed = $entry['whencreated'];
										}
										$total_processed++;
									}
									
									// let's require for all records created after the last created date
									if(empty($last_created_date_processed)) {
										// we will just break since we have not date to base off
										break;
									}
									
									if($found < $size) {
										// we have already processed the last round, we need to stop
										break;
									}
									
									// NOTE: XXIONG(03/04/2016), we will use greaterOrEqual to make sure we get everything, including records with the same whencreated
									$newfilter = $filter->addFilter(
										// greater or equal
										\Zend\Ldap\Filter::greaterOrEqual('whencreated', $last_created_date_processed)
									);
									$logger->log(Logger::INFO, "setting next filter to " . $newfilter->toString());
									
									$searches = $ldap->search(
										$newfilter,
										null,
										\Zend\Ldap\Ldap::SEARCH_SCOPE_SUB,
										$this->getGroupFlatFields(),
										// sort
										'whencreated',
										// collectionClass
										null,
										// sizeLimit
										$size
									);
									
									$logger->log(Logger::INFO, "found another set of " . $searches->count() . " searches");
								} while($searches->count() > 0);
							} else {
								foreach($searches as $entry) {
									$name = $entry['cn'];
									if(is_array($name)) {
										$name = array_shift($name);
									}
									
									$info = $this->loadGroup($ldap, $name, $entry);
									if($info === false) {
										$logger->log(Logger::WARN, "unable to load group {$name} in Ldap");
										continue;
									}
									
									$logger->log(Logger::INFO, "loading group " . $info['name']);
									if(!$this->importGroup($info)) {
										$logger->log(Logger::ERR, "unable to import group " . $info['name'] . " : " . $this->error);
										continue;
									}
									
									$total_processed++;
								}
							}
							
							break;
						} catch(\Exception $e) {
							$attempts++;
							$logger->log(Logger::ERR, "failed attempt {$attempts}, retrying");
							sleep(10);
						}
						
						if($attempts > 5) {
							break;
						}
					} while(true);
				}				
				
				$result->setResult("successfully loaded all {$total_processed} ldap groups\n");
			} else {
				throw new \Exception("invalid username/password");
			}
		} catch(\Exception $e) {
			throw $e;
		
			$p = $e->getPrevious();
			if($p) {
				$e = $p;
			}			
			
			$result->setResult("unable to process ldap load : " . $e->getMessage() . "\n");
			$result->setErrorLevel(1);
		}
		
		return $result;
	}
	
	protected function loadGroup($ldap, $group, $entry = false) {
		if($entry === false) {
			try {
				$filter = \Zend\Ldap\Filter::andFilter(
					// Group
					\Zend\Ldap\Filter::equals('objectClass', 'group'),
					// Name is equal
					\Zend\Ldap\Filter::equals('cn', $group)
				);
				
				$result = $ldap->search($filter, null, LdapLdap::SEARCH_SCOPE_SUB, $this->getGroupFlatFields());
				
				if($result->count() > 0) {
					$entry = $result->current();
				}
			} catch(\Exception $e) {
				echo $e->getMessage();
				return false;
			}
		}
		
		$rawinfo = new \stdClass();
		foreach($entry as $attr => $value) {
			if(is_array($value)) {
				$rawinfo->$attr = (count($value) > 1) ? $value : $value[0];
			} else {
				$rawinfo->$attr = $value;
			}
		}
		
		$info = array();
		foreach($this->getGroupFields() as $name => $field) {
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
	
	protected function importGroup($info) {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('console_logger');
		
		$hr_users_mdl = HrUsers::factory($sm);
		$hr_groups_mdl = HrGroups::factory($sm);
		$hr_user_group_links_mdl = HrUserGroupLinks::factory($sm);
		$hr_group_group_links_mdl = HrGroupGroupLinks::factory($sm);		
		$hr_domains_mdl = HrDomains::factory($sm);
		
		$hr_users_mdl->setAllowFilterExpression(false);
		$hr_groups_mdl->setAllowFilterExpression(false);
		$hr_domains_mdl->setAllowFilterExpression(false);
		
		$info['name'] = strtolower($info['name']);
		$info['email'] = strtolower($info['email']);
		$info['domain'] = $this->resolveDomain($info['email']);
		$result = Utils::normalizeMembers($info['members']);
		$info['users'] = $result['users'];
		$info['groups'] = $result['groups'];
		unset($info['members']);
		
		try {
			// we need to check if we have this group in our database
			$group_id = null;
			$domain_id = null;
			$domain = $this->resolveDomain($info['domain']);
			
			// resolve the domain
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
			
			$row = $hr_groups_mdl->get(array(
				'name' => $info['name'],
				'domain_id' => $domain_id
			));
			
			if($row === false) {
				$group_id = $hr_groups_mdl->insert(
					$info['name'],
					$domain_id
				);
			} else {
				$group_id = $row->id;
			}
			
			if(is_array($info['users'])) {
				$direct_users = array();
				
				foreach($info['users'] as $user) {
					// verify if this user exists
					$hr_user = $hr_users_mdl->get(array('full_name' => $user));
					if($hr_user) {
						// check if this user has already been linked
						$link = $hr_user_group_links_mdl->get(array(
							'user_id' => $hr_user->id,
							'group_id' => $group_id
						));
						
						if($link === false) {
							// insert this link
							$hr_user_group_links_mdl->insert($hr_user->id, $group_id);
						} else {
							// update the ctime for this link so that we can later use it for cleaning purposes
							$hr_user_group_links_mdl->update(array(
								'user_id' => $hr_user->id,
								'group_id' => $group_id
							), array(
								'ctime' => new Expression('NOW()')
							));
						}
						
						$direct_users[] = $hr_user->id;
						//$logger->log(Logger::INFO, "linked user {$user} to group {$info['name']}");
					} // otherwise we will ignore for now, when the user gets loaded it will get its linkage
				}
				
				if(count($direct_users) > 0) {
					$to_process = array($group_id);
					$processed_groups = array();
					
					// associate all direct users in this group to any parent groups
					while(count($to_process) > 0) {
						$parent_group_id = array_shift($to_process);
						if($processed_groups[$parent_group_id] === true) {
							continue;
						}
						
						$parent_group_links = $hr_group_group_links_mdl->filter(array(
							'child_group_id' => $parent_group_id
						), array(), array(), $total);
						
						foreach($parent_group_links as $parent_group_link) {
							foreach($direct_users as $user_id) {
								$link = $hr_user_group_links_mdl->get(array(
									'user_id' => $user_id,
									'group_id' => $parent_group_link->group_id
								));
								
								if($link === false) {
									// insert this link
									$hr_user_group_links_mdl->insert($user_id, $parent_group_link->group_id);
								} else {
									// update the ctime for this link so that we can later use it for cleaning purpose
									$hr_user_group_links_mdl->update(array(
										'user_id' => $user_id,
										'group_id' => $parent_group_link->group_id
									), array(
										'ctime' => new Expression('NOW()')
									));
								}
							}
							
							if($processed_groups[$parent_group_link->group_id] !== true) {
								$to_process[] = $parent_group_link->group_id;
							}
						}
						
						$processed_groups[$parent_group_id] = true;
					}
					
					//$logger->log(Logger::INFO, "processed " . count($processed_groups) . " parent groups");
				}
			}
			
			if(is_array($info['groups'])) {
				$processed_groups = array();
				
				foreach($info['groups'] as $group) {
					$hr_group = $hr_groups_mdl->get(array('name' => $group));
					if($hr_group) {
						$child_group_id = $hr_group->id;
					} else {
						$child_group_id = $hr_groups_mdl->insert($group);
					}
					
					if($group_id == $child_group_id) {
						// this is a circular reference, we do not need to process it
						continue;
					}
					
					// check if this group already has been linked
					$link = $hr_group_group_links_mdl->get(array(
						'group_id' => $group_id,
						'child_group_id' => $child_group_id
					));
					
					if($link === false) {
						// insert this link
						$hr_group_group_links_mdl->insert($group_id, $child_group_id);
					} else {
						// update the ctime for this link so that we can later use it for cleaning purposes
						$hr_group_group_links_mdl->update(array(
							'group_id' => $group_id,
							'child_group_id' => $child_group_id
						), array(
							'ctime' => new Expression('NOW()')
						));
					}
					
					$to_process = array($child_group_id);
					
					// associate all child groups and users in child groups
					while(count($to_process) > 0) {
						$child_group_id = array_shift($to_process);
						if($processed_groups[$child_group_id] === true) {
							continue;
						}
						
						$child_group_links = $hr_group_group_links_mdl->filter(array(
							'group_id' => $child_group_id
						), array(), array(), $total);
						
						foreach($child_group_links as $child_group_link) {
							// associate this child group
							$link = $hr_group_group_links_mdl->get(array(
								'group_id' => $group_id,
								'child_group_id' => $child_group_link->child_group_id
							));
							
							if($link === false) {
								// insert this link
								$hr_group_group_links_mdl->insert($group_id, $child_group_link->child_group_id);
							} else {
								// update the ctime for this link so that we can later use it for cleaning purposes
								$hr_group_group_links_mdl->update(array(
									'group_id' => $group_id,
									'child_group_id' => $child_group_link->child_group_id
								), array(
									'ctime' => new Expression('NOW()')
								));
							}
							
							// associate the users in this child group
							$users_links = $hr_user_group_links_mdl->filterUser(array(
								'group_id' => $child_group_link->child_group_id
							), array(), array(), $total);
							
							foreach($users_links as $user_link) {
								// check if this user has already been linked
								$link = $hr_user_group_links_mdl->get(array(
									'user_id' => $user_link->user_id,
									'group_id' => $group_id
								));
								
								if($link === false) {
									// insert this link
									$hr_user_group_links_mdl->insert($user_link->user_id, $group_id);
								} else {
									// update the ctime for this link so that we can later use it for cleaning purposes
									$hr_user_group_links_mdl->update(array(
										'user_id' => $user_link->user_id,
										'group_id' => $group_id
									), array(
										'ctime' => new Expression('NOW()')
									));
								}
							}
							
							if($processed_groups[$child_group_link->child_group_id] !== true) {
								$to_process[] = $child_group_link->child_group_id;
							}
						}
						
						$processed_groups[$child_group_id] = true;
					}
				}
				
				//$logger->log(Logger::INFO, "processed " . count($processed_groups) . " child groups");
			}
		} catch(\Exception $e) {
			$logger->log(Logger::DEBUG, $e->getTraceAsString());
			
			$p = $e->getPrevious();
			if($p) {
				$e = $p;
			}
			
			$this->error = $e->getMessage();
			
			return false;
		}
		
		return true;
	}
	
	public function cleanAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('console_logger');
		
		$cutoff = $this->params()->fromRoute('cutoff');
		if(!empty($cutoff)) {
			$cutoff = strtotime($cutoff);
			if($cutoff === false) {
				throw new \Exception("invalid cutoff time provided");
			}
			
			$cutoff = date('Y-m-d H:i:s', $cutoff);
		}
		
		$logger->log(Logger::INFO, "setting cutoff time to {$cutoff}");
		
		$result = new ConsoleModel();
		
		$hr_user_group_links_mdl = HrUserGroupLinks::factory($sm);
		$hr_group_group_links_mdl = HrGroupGroupLinks::factory($sm);
		
		try {
			// delete all user-group links that has not been updated within the given cutofff
			$where = new Where();
			$where->lessThan('ctime', $cutoff);
			
			$cnt = $hr_user_group_links_mdl->delete($where);
			
			$logger->log(Logger::INFO, "deleted {$cnt} outdated user-group links");
			
			$cnt = $hr_group_group_links_mdl->delete($where);
			
			$logger->log(Logger::INFO, "deleted {$cnt} outdated group-group links");
		} catch(\Exception $e) {
			$result->setResult("unable to process clean up : " . $e->getMessage() . "\n");
			$result->setErrorLevel(1);
		}
		
		return $result;
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
	
}
?>