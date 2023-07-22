<?php
namespace Auth\Controller\Script;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Log\Logger;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Where;
use Zend\View\Model\ConsoleModel;
use Zend\Server\Reflection;
use Zend\Json\Json;
use Zend\Crypt\BlockCipher;

use Auth\Model\SecurityRoles;
use Auth\Model\SecurityUserRoleLinks;
use Auth\Model\HrUsers;
use Auth\Model\HrAccounts;

class SecurityController extends AbstractActionController {
	public function assignRoleAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('console_logger');
		$db = $sm->get('db');
		
        $domain = $this->params()->fromRoute('domain');
		$username = $this->params()->fromRoute('username');
		$role = $this->params()->fromRoute('role');
		
        $hr_users_mdl = HrUsers::factory($sm);
        $security_roles_mdl = SecurityRoles::factory($sm);
        $security_user_role_links = SecurityUserRoleLinks::factory($sm);
        
		$result = new ConsoleModel();
		
		$con = $db->getDriver()->getConnection();
		
		try {
			$con->beginTransaction();
			
            $user_row = $hr_users_mdl->get(array(
                'username' => $username,
                'domain' => $domain
            ));
            
            if($user_row === false) {
                $error = "The user with username {$username} does not exists in the domain {$domain} yet, please login through the Web UI first";
                throw new \Exception($error);
            }
            
            $role_row = $security_roles_mdl->get(array(
                'name' => $role
            ));
            
            if($role_row === false) {
                $error = "The role with name {$role} does not exists";
                throw new \Exception($error);
            }
            
            // check and see if this user and role have already been linked
            $link = $security_user_role_links->get(array(
                'role_id' => $role_row->id,
                'user_id' => $user_row->id
            ));
            
            if($link === false) {
                // add this link
                $security_user_role_links->insert(
                    $user_row->id,
                    $role_row->id
                );
                
                $result->setResult("The user {$username} has been assigned role {$role}\n");
            } else {
                $result->setResult("The user {$username} is already assigned to the role {$role}\n");
            }
            
			$con->commit();
		} catch(\Exception $e) {
			$con->rollback();
			
			$result->setResult("unable to assign role : " . $e->getMessage() . "\n");
			$result->setErrorLevel(1);
		}
		
		return $result;
	}
	
	public function createLocalUserAction() {
		$sm = $this->getServiceLocator();
		$config = $sm->get('Application\Config');
		$logger = $sm->get('console_logger');
		$db = $sm->get('db');
		
		$hr_accounts_mdl = HrAccounts::factory($sm);
		
		$salt = $config['salt'];
		$blockCipher = BlockCipher::factory('mcrypt', array(
			'algo' => 'aes'
		));
		$blockCipher->setKey($salt);
		
		$username = $this->params()->fromRoute('username');
		$password = $this->params()->fromRoute('password');
		$email = $this->params()->fromRoute('email');
		$first_name = $this->params()->fromRoute('first-name');
		$last_name = $this->params()->fromRoute('last-name');
		
		$result = new ConsoleModel();		
		$con = $db->getDriver()->getConnection();
		
		try {
			$con->beginTransaction();
			
			$account_row = $hr_accounts_mdl->get(array(
				'username' => $username
			));
			
			if($account_row !== false) {
				$error = "The user with username {$username} already exists";
				throw new \Exception($error);
			}
            
			$account_id = $hr_accounts_mdl->insert(
				// username
				$username,
				// password
				$blockCipher->encrypt($password),
				// email
				$email,
				// first_name
				$first_name,
				// last_name
				$last_name
			);
			
			if ($account_id > 0) {
				$logger->log(Logger::INFO, "successfully created user account for {$username}");
			} else {
				$logger->log(Logger::INFO, "unable to create user account for {$username}");
				$result->setErrorLevel(1);
			}
			
			$con->commit();
		} catch(\Exception $e) {
			$con->rollback();
			
			$p = $e->getPrevious();
			if ($p) {
				$logger->log(Logger::ERR, $p->getMessage());
				$logger->log(Logger::ERR, $p->getTraceAsString());
			}
			
			$result->setResult("unable to create local user account : " . $e->getMessage() . "\n");
			$result->setErrorLevel(1);
		}
		
		return $result;
	}
	
	public function updateLocalUserPasswordAction() {
		$sm = $this->getServiceLocator();
		$config = $sm->get('Application\Config');
		$logger = $sm->get('console_logger');
		$db = $sm->get('db');
		
		$hr_accounts_mdl = HrAccounts::factory($sm);
		
		$salt = $config['salt'];
		$blockCipher = BlockCipher::factory('mcrypt', array(
			'algo' => 'aes'
		));
		$blockCipher->setKey($salt);
		
		$username = $this->params()->fromRoute('username');
		$password = $this->params()->fromRoute('password');
		
		$result = new ConsoleModel();		
		$con = $db->getDriver()->getConnection();
		
		try {
			$con->beginTransaction();
			
			$account_row = $hr_accounts_mdl->get(array(
				'username' => $username
			));
			
			if($account_row === false) {
				$error = "The user with username {$username} does not exists";
				throw new \Exception($error);
			}
            
			$cnt = $hr_accounts_mdl->update($account_row->id, array(
				'password' => $blockCipher->encrypt($password)
			));
			
			if ($cnt > 0) {
				$logger->log(Logger::INFO, "successfully updated user account password for {$username}");
			} else {
				$logger->log(Logger::INFO, "password did not change, no update was necessary for user account {$username}");
			}
			
			$con->commit();
		} catch(\Exception $e) {
			$con->rollback();
			
			$result->setResult("unable to assign role : " . $e->getMessage() . "\n");
			$result->setErrorLevel(1);
		}
		
		return $result;
	}
}
?>