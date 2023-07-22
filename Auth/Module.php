<?php
namespace Auth;

use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\Console\Adapter\AdapterInterface;
use Zend\ModuleManager\Feature\ServiceProviderInterface;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\ModuleManager\Feature\ConsoleBannerProviderInterface;
use Zend\ModuleManager\Feature\ConsoleUsageProviderInterface;
use Zend\EventManager\StaticEventManager;

use Zend\Permissions\Acl\Acl;
use Zend\Permissions\Acl\Role\GenericRole as Role;
use Zend\Permissions\Acl\Resource\GenericResource as Resource;

use Zend\Console\Request as ConsoleRequest;
use Zend\Log\Logger;

use Hr\Model\HrDepts;
use Hr\Model\HrUsers;

use Auth\Model\SecurityUserRoleLinks;
use Auth\Model\SecurityGroupRoleLinks;
use Auth\Model\SecurityResources;
use Auth\Model\SecurityRoleExcludeRoles;

class Module implements ServiceProviderInterface, AutoloaderProviderInterface, ConfigProviderInterface, ConsoleBannerProviderInterface, ConsoleUsageProviderInterface {
	protected $ignore_init = false;
	
    public function init() {
        $eventManager = StaticEventManager::getInstance();
        $eventManager->attach('Zend\Mvc\Controller\AbstractActionController', 'dispatch', array($this, 'handleAction'), 100);
		$eventManager->attach('*', 'login-success', array($this, 'userLoggedIn'), 100);
		
		if($config['module_ignore_init'][__NAMESPACE__] !== true) {
			// attach to the authorization check to receive checks
			$eventManager->attach('Zend\Mvc\Controller\AbstractActionController', 'authorization-check', array($this, 'checkAuthorization'));
		} else {
			$this->ignore_init = true;
		}
    }
    
    public function onBootstrap(MvcEvent $e) {
        $eventManager        = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);
    }

    /**
     * Implement to provide the confirmation for this module
     * 
     * @return Array
     * */
    public function getConfig() {
        return include __DIR__ . '/config/module.config.php';
    }

    /**
     * Implement to provide the configuration for the autoloader so that it can load the Controller Classes
     * 
     * @return Array
     * */
    public function getAutoloaderConfig() {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }
    
    /**
     * Implement to provide services that are specific to this module.  This configuration will be mixin
     * with the service_manager configuration in module.config.php
     * 
     * @return Array
     * */
    public function getServiceConfig() {
        return array(
			
        );
    }
    
	public function getConsoleBanner(AdapterInterface $console) {
		
	}
	
	public function getConsoleUsage(AdapterInterface $console) {
		return array(
			'ldap load --username --password <user>' => 'Load the LDAP User using the username/password to bind',
			array('user',     'Username to load'),
			array('username', 'Username to bind'),
			array('password', 'Password to bind'),
			
			'ldap load-all --username --password [--start=] [--end=] [--cutoff=]' => 'Load all the LDAP User using the username/password to bind',
			array('username', 'Username to bind'),
			array('password', 'Password to bind'),
			array('start', 	  'Start processing user names starting with this character'),
			array('end', 	  'Start processing user names ending with this character'),
			array('cutoff',   'Process only users with updates after the cutoff date/time'),
			
			'ldap load-group --username --password <goup>' => 'Load the LDAP Group using the username/password to bind',
			array('group',    'Group to load'),
			array('username', 'Username to bind'),
			array('password', 'Password to bind'),
			
			'ldap load-all-group --username --password [--start=] [--end=] [--cutoff=]' => 'Load all the LDAP Group using the username/password to bind',
			array('username', 'Username to bind'),
			array('password', 'Password to bind'),
			array('start', 	  'Start processing group names starting with this character'),
			array('end',      'Start processing group names ending with this character'),
			array('cutoff',   'Process only groups with updates after the cutoff date/time'),
			
			'ldap clean --cutoff=' => 'Clean all LDAP user/group links',
			array('cutoff',   'Clean only links that are older than the cuttoff date/time'),
			
			'auth role assign --domain= --username= --role=' => 'Assign a role to a specific username within the provided domain',
			array('domain', 'The domain the user is in'),
			array('username', 'The username to assign the role to'),
			array('role', 'The role to assign (Admin, Super Admin, etc)'),
			
			'auth local account create --username= --password= --email= --first-name= --last-name=' => 'Create a local user account',
			array('username', 'The username to create'),
			array('password', 'The password for this account'),
			array('email', 'The email use for any communication for this account'),
			array('first-name', 'The first name of this user account'),
			array('last-name', 'The last name of this user account'),
			
			'auth local account update password --username= --password=' => 'Update a local user account password',
			array('username', 'The username to create'),
			array('password', 'The password for this account'),
		);
	}
	
    public function handleAction($event) {
		if($event instanceof \Zend\Mvc\MvcEvent) {		
			$request = $event->getRequest();
		} else {
			$controller = $event->getTarget();
			$serviceLocator = $controller->getServiceLocator();
			$request = $serviceLocator->get('Request');
		}
		
		if($request instanceof \Zend\Console\Request) {
			// we don't need to do anything for console requests
			return;
		}
		
        $controller = $event->getTarget();
        if($controller instanceof \Els\Mvc\Controller\PublicInterface) {
            // this is a public controller, we do not need to protect it
            return;
        }
		
		$serviceLocator = $controller->getServiceLocator();
		$session = $serviceLocator->get('session');		
		$config = $serviceLocator->get('Config');
		$logger = $serviceLocator->get('logger');
		
		// grab the user id
		$user_id = $controller->authentication()->getAuthenticatedUserId();
		
        $routeMatch = $event->getRouteMatch();
        if ($routeMatch) {
            $resource = $routeMatch->getParam('controller', 'not-found');
            $action = $routeMatch->getParam('action', \Els\Controller\Plugin\Authorization::DEFAULT_ACTION);
        } else {
            $resource = null;
            $action = \Els\Controller\Plugin\Authorization::DEFAULT_ACTION;
        }
        
        if(empty($resource)) {
            // no resource, no need to protect it
            return;
        }
		
		if(empty($user_id)) {
			// redirect to the route with the name 'login'
			$params = array();
			$url = $event->getRequest()->getRequestUri();
			
			if($url != '' && $url != '/') {
				$params['url'] = urlencode($url);
			}
			
			$url = $event->getRouter()->assemble(array(), array('name' => 'login', 'query' => $params));
			
			$response = $event->getResponse();
			$response->getHeaders()->addHeaderLine('Location', $url);
			$response->setStatusCode(302);
			$response->sendHeaders();
			exit;
		} else if($controller->sso()->isJump()) {
			// grab the ldap info for this user
			$info = $controller->sso()->loadUserInfo($user_id);
			
			if($info) {
				$session = $serviceLocator->get('session');
				
				// make sure the image is stored locally
				$info['picture'] = $this->storePicture($info['picture'], $info['user_id']);
				$info['groups'] = split(',', $info['groups']);
				$info['domain'] = $domain;
				
				// set the user info in session
				$session->userinfo = $info;
				
				// this is a sso jump from one server to another, we need to trigger a login-success event
				$results = $controller->getEventManager()->trigger('login-success', $controller, array(
					'user_id' => $info['user_id'],
					'info' => $info
				));
				
				// grab the first jump flag before we mark this a successfull jump.
				$previous_jump = $controller->sso()->getPreviousJump();
				$is_first_jump = $controller->sso()->isFirstJump();
				
				// mark that this is a successful jump
				$controller->sso()->markSuccessJump();
				
				if($is_first_jump) {
					// now let's see if anyone wants us to redirect to their page first, if not we will go to our default landing page
					foreach($results as $result) {
						if(is_array($result) && $result['landing']) {
							try {
								$controller->authentication()->redirectToLandingPage($result['landing']['route'], $result['landing']['url']);
							} catch(\Exception $e) {
								// do nothing for now
							}
						}
					}
				}
			} else {
				$logger->log(Logger::ERR, "unable to load user info");
			}
		} else {
			// trigger the authorization event so that which ever module is restricting access and check
			$results = $controller->getEventManager()->trigger('authorization-check', $controller, array(
				'user_id' => $user_id,
				'controller' => $resource,
				'action' => $action
			));
			
			if(!empty($results)) {
				foreach($results as $result) {
					// any someone responds with a deny request, we will deny access
					if($result['deny'] ===  true) {
						$ctl = ($result['controller']) ? $result['controller'] : $resource;
						$act = ($result['action']) ? $result['action'] : $action;
						
						// redirect to the permission error page
						$url = $event->getRouter()->assemble(array(), array('name' => 'permission-error', 'query' => array(
							'resource' => $ctl,
							'action' => $act
						)));
						$response = $event->getResponse();
						$response->getHeaders()->addHeaderLine('Location', $url);
						$response->setStatusCode(403);
						$response->sendHeaders();
						exit;
					}
				}
			}
		}
    }
	
	public function userLoggedIn($event) {
		if($event instanceof \Zend\Mvc\MvcEvent) {		
			$request = $event->getRequest();
		} else {
			$controller = $event->getTarget();
			$serviceLocator = $controller->getServiceLocator();
			$request = $serviceLocator->get('Request');
		}
		
		if($request instanceof \Zend\Console\Request) {
			// we don't need to do anything for console requests
			return;
		}
		
		$controller = $event->getTarget();
		$serviceLocator = $controller->getServiceLocator();
		
        $db = $serviceLocator->get('db');
		$logger = $serviceLocator->get('logger');
		$config = $serviceLocator->get('Config');
		$app_config = $serviceLocator->get('Application\Config');
		
        $user_id = $event->getParam('user_id');
        $info = $event->getParam('info');
		
		$pluginManager = $serviceLocator->get('controllerPluginManager');
		$authorization = $pluginManager->get('authorization');
		
		$hr_users_mdl = HrUsers::factory($serviceLocator);
		$hr_depts_mdl = HrDepts::factory($serviceLocator);
		
		$security_user_role_links_mdl = SecurityUserRoleLinks::factory($serviceLocator);
		$security_group_role_links_mdl = SecurityGroupRoleLinks::factory($serviceLocator);
		$security_resources_mdl = SecurityResources::factory($serviceLocator);
		$security_role_exclude_roles_mdl = SecurityRoleExcludeRoles::factory($serviceLocator);
		
		// we need to load all roles this user has or the roles the group of this user is in has
		$all_roles = array();
		
		$roles = $security_user_role_links_mdl->filter(array(
			'user_id' => $user_id
		), array(), array(), $total);
		
		foreach($roles as $role) {
			$all_roles[$role->role_id] = $role->role_rights_level;
		}
		
		$roles = $security_group_role_links_mdl->filter(array(
			'user_id' => $user_id
		), array(), array(), $total);
		
		foreach($roles as $role) {
			$all_roles[$role->role_id] = $role->role_rights_level;
		}
		
		if(count($all_roles) > 0) {
			// XXIONG (5/02/2017) - now let's go through the roles and see if any is setup to exclude other roles
			$exclude_roles = $security_role_exclude_roles_mdl->filter(array(
				'role_id' => array_keys($all_roles)
			), array(), array(), $total);
			
			foreach($exclude_roles as $exclude_role) {
				unset($all_roles[$exclude_role->exclude_role_id]);
			}
		}
		
		$authorization->setRoles($all_roles);
		
		$all_permitted_resources = array();
		if(count($all_roles) > 0) {
			// check if the roles this user have has access to the required permission for these resources
			$resources = $security_resources_mdl->filterAccess(array(
				'role_id' => array_keys($all_roles)
			), array(), array(), $total);
			foreach($resources as $resource) {
				$all_permitted_resources["{$resource->controller}::{$resource->action}"] = $resource->id;
			}
		}
		
		$authorization->setPermittedResources($all_permitted_resources);
    }
	
	public function checkAuthorization($event) {
		if($event instanceof \Zend\Mvc\MvcEvent) {
			$request = $event->getRequest();
		} else {
			$controller = $event->getTarget();
			$serviceLocator = $controller->getServiceLocator();
			$request = $serviceLocator->get('Request');
		}
		
		if($request instanceof \Zend\Console\Request) {
			// we don't need to do anything for console requests
			return;
		}
		
		$controller = $event->getTarget();
		$serviceLocator = $controller->getServiceLocator();
		
		$controller = $event->getParam('controller');
		$action = $event->getParam('action');
		
		// we need to replace the \Controller\ with ::
		$controller = str_replace('\\Controller\\', '::', $controller);
		
		// check if this resource requires authorization
		$security_resources_mdl = SecurityResources::factory($serviceLocator);
		$resource = $security_resources_mdl->get(array(
			'controller' => $controller,
			'action' => $action
		));
		
		if($resource === false) {
			// this resource does not require authorization, we will give the user access
			return array(
				'controller' => $controller,
				'action' => $action,
				'deny' => false
			);
		}
		
		$pluginManager = $serviceLocator->get('controllerPluginManager');
		$authorization = $pluginManager->get('authorization');
		
		if($authorization->isPermitted($controller, $action)) {
			// yes this user has access
			return array(
				'controller' => $controller,
				'action' => $action,
				'deny' => false
			); 
		} else {
			// no this user does not have access
			return array(
				'controller' => $controller,
				'action' => $action,
				'deny' => true
			);
		}
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
}

?>