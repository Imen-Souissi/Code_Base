<?php
namespace Api\Controller;

use Zend\Mvc\Controller\AbstractController;
use zend\Mvc\Exceptionn\DomainException;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Mvc\MvcEvent;
use Zend\Log\Logger;
use Zend\Json\Json;
use Zend\Loader\AutoloaderFactory;

use Els\Mvc\Controller\PublicInterface;

use Api\Model\ApiKeys;
use Api\Model\ApiKeyServiceMethods;
use Api\Model\ApiServiceMethods;

use Hr\Model\HrUsers;
use Hr\Model\HrUserGroupLinks;

use Api\Service\Exception\ErrorException;

class ApiController extends AbstractController implements PublicInterface {
	public function onDispatch(MvcEvent $event) {
        $routeMatch = $event->getRouteMatch();
        if (!$routeMatch) {
            throw new DomainException('Missing route matches; unsure how to retrieve action');
        }
		
		$sm = $this->getServiceLocator();
        $config = $sm->get('Config');
        $logger = $sm->get('api_logger');
        
        $version = $this->params()->fromRoute('version');
        $service = $this->params()->fromRoute('service');
        $method = $this->params()->fromRoute('method');
        $apikey = $this->params()->fromRoute('apikey');
        
        $method = lcfirst(implode('', array_map('ucfirst', split('-', $method))));
        
        // verify if apikey is valid
        $apikeys_mdl = ApiKeys::factory($sm);
        
        $key = $apikeys_mdl->get(array(
            'key' => $apikey,
            'active' => 1
        ));
        
        if($key === false) {
            // invalid key, return 403
            $this->getResponse()->setStatusCode(403);
            $model = new JsonModel(array(
                'error' => 'invalid api key'
            ));
			
			$event->setResult($model);
			
			return $model;
        }
        
		// grab the session manager and use the apikey as the session id
		$session_manager = $sm->get('SessionManager');
		$session_manager->setId(md5($apikey));
		
		// now let's see if the session is loaded
		if(!$this->authentication()->isAuthenticated() || $this->authentication()->getAuthenticatedUserId() != $key->user_id) {
			// we should artificially authenticate this user_id so that all proper permissions are loaded
			if(!$this->authenticateUser($key->user_id)) {
				$model = new JsonModel(array(
					'error' => 'invalid api key'
				));
				
				$event->setResult($model);
				
				return $model;
			}
		}
		
        // check if this service exists
        $cls = $config['api']['services'][$service];
        $loader = AutoloaderFactory::getRegisteredAutoloader("Zend\\Loader\\StandardAutoloader");
		$cls = $loader->autoload($cls);
        
        if($cls === false) {
            // invalid service, return 404
            $this->getResponse()->setStatusCode(404);
            $model = new JsonModel(array(
                'error' => 'invalid service'
            ));
			
			$event->setResult($model);
			
			return $model;
        } else if(!$this->checkApiServiceAccess($service, $version, $method, $key->id, intval($key->auto_configure) == 1)) {
			// unauthorize access, return 401
			$this->getResponse()->setStatusCode(401);
			$model = new JsonModel(array(
				'error' => 'insufficient permissions'
			));
			
			$event->setResult($model);
			
			return $model;
		}
        
        $obj = new $cls();
        if($obj instanceof ServiceLocatorAwareInterface) {
			$obj->setServiceLocator($sm);
		}
        
        if(!method_exists($obj, $method)) {
            // invalid method, return 404
            $this->getResponse()->setStatusCode(404);
            $model = new JsonModel(array(
                'error' => 'invalid service method'
            ));
			
			$event->setResult($model);
			
			return $model;
        }
        
        $params = $this->params()->fromQuery();
        if($this->getRequest()->isPost()) {
            $post = $this->params()->fromPost();
            if(count($post) == 0) {
                // see if the post content is a json data
                try {
                    $raw = $this->getRequest()->getContent();
                    $post = Json::decode($raw, Json::TYPE_ARRAY);
                    $params = array_merge($params, $post);
                } catch(\Exception $e) {
                    // do nothing
                }
            } else {
                $params = array_merge($params, $post);
            }
        }
        
        try {
            $result = call_user_func_array(array($obj, $method), array($params));
            $model = new JsonModel(array(
                'result' => $result
            ));
        } catch(ErrorException $e) {
			$logger->log(Logger::ERR, $e->getMessage());
            $logger->log(Logger::ERR, $e->getTraceAsString());
            
            $this->getResponse()->setStatusCode(($e->getCode()) ? $e->getCode() : 500);
			
			$model = new JsonModel(array(
				'error' => $e->getMessage()
			));
		} catch(\Exception $e) {
            $logger->log(Logger::ERR, $e->getMessage());
            $logger->log(Logger::ERR, $e->getTraceAsString());
            
			$p = $e->getPrevious();
			if($p) {
				$logger->log(Logger::ERR, $p->getMessage());
				$logger->log(Logger::ERR, $p->getTraceAsString());
			}
			
            $this->getResponse()->setStatusCode(500);
            $model = new JsonModel(array(
                'error' => 'unknown error'
            ));
        }
		
        $event->setResult($model);
		
		return $model;
    }
	
	protected function authenticateUser($user_id) {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('api_logger');
		
		$hr_users_mdl = HrUsers::factory($sm);
		$hr_user_group_links_mdl = HrUserGroupLinks::factory($sm);
		
		$auth_storage = $sm->get('auth_storage');
		
		// load the user's info
		$info = array();
		
		$user = $hr_users_mdl->get(array('id' => $user_id));
		if($user !== false) {
			$auth_storage->write($user_id);
			
			$info['display_name'] = $user->display_name;
			$info['full_name'] = $user->full_name;
			$info['first_name'] = $user->first_name;
			$info['last_name'] = $user->last_name;
			$info['username'] = $user->username;
			$info['email'] = $user->email;
			$info['phone'] = $user->phone;
			$info['department_id'] = $user->department_id;
			$info['department_number'] = $user->department_number;
			$info['department'] = $user->department;
			$info['memberof'] = array();			
			$info['picture'] = $user->picture;
			
			// load all the groups the user is part of
			$groups = $hr_user_group_links_mdl->filterUser(array(
				'user_id' => $user->id
			), array(), array(), $total);
			
			foreach($groups as $group) {
				$info['memberof'][] = $group->name;
			}
			
			$session = $sm->get('session');
			$session->userinfo = $info;
			
			// trigger loginSuccess event here
			$this->getEventManager()->trigger('login-success', $this, array(
				'user_id' => $user_id,
				'info' => $info
			));
			
			return true;
		} else {
			$auth_storage->clear();
			return false;
		}
	}
	
	protected function checkApiServiceAccess($service, $version, $method, $key_id, $auto_configure = false) {
		$sm = $this->getServiceLocator();
		
		$api_service_methods_mdl = ApiServiceMethods::factory($sm);
		$api_key_service_methods_mdl = ApiKeyServiceMethods::factory($sm);
		
		$filter = array(
			'service' => $service,
			'service_version' => $version,
			'method' => $method
		);
		
		if($auto_configure) {
			$filter['security_resource_id'] = $this->loadResources($key_id);
			
			$item = $api_service_methods_mdl->getAuto($filter);
		} else {
			$filter['key_id'] = $key_id;
			
			$item = $api_key_service_methods_mdl->get($filter);
		}
		
		return ($item !== false);
	}
	
	protected function loadResources($key_id) {
        $sm = $this->getServiceLocator();
        $config = $sm->get('Config');
        $app = (empty($config['api']['app'])) ? 'app' : $config['api']['app'];
        
        $api_keys_mdl = ApiKeys::factory($sm);
        
        $allresources = array(0);
        
        $resources = $api_keys_mdl->filterUserSecurityResources(array(
            'id' => $key_id
        ), array(), array(), $total);
        
        foreach($resources as $resource) {
            $allresources[] = $resource->resource_id;
        }
        
        $resources = $api_keys_mdl->filterGroupSecurityResources(array(
           'id' => $key_id
        ), array(), array(), $total);
        
        foreach($resources as $resource) {
            $allresources[] = $resource->role_id;
        }
        
        return array_values(array_unique($allresources));
    }
}
?>