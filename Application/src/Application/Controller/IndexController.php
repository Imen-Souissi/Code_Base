<?php
namespace Application\Controller;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Log\Logger;
use Zend\Json\Json;

use Application\Model\Settings;

class IndexController extends AbstractActionController {
	public function indexAction() {
		return new ViewModel();
	}
	
	public function searchAction() {
		// we want to change the layout type to 'horizontal'
		$this->layout()->setVariable('layout_type', 'h');
		$this->layout()->setVariable('keywords', $this->params()->fromQuery('keywords'));
		return new ViewModel(array(
			'keywords' => $this->params()->fromQuery('keywords')
		));
	}
	
	public function pinItAction() {
		$sm = $this->getServiceLocator();
		$config = $sm->get('Application\Config');
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$app = $config['app'];
		
		$error = "";
		$settings_mdl = Settings::factory($sm);
		$id = null;
		
		$authentication = $this->plugin('authentication');
		
		if(empty($authentication)) {
			$error = "no authentication plugin";
		} else if(empty($app)) {
			$error = "no app defined";
		} else {
			$user_id = $authentication->getAuthenticatedUserId();
			
			if($this->getRequest()->isPost()) {
				$post = $this->params()->fromPost();
				$con = $db->getDriver()->getConnection();
				
				try {
					$con->beginTransaction();
					
					// check if this setting already exists
					$setting = $settings_mdl->get(array(
						'user_id' => $user_id,
						'app' => $app,
						'field' => 'landing'
					));
					
					$value = Json::encode(array(
						'route' => $post['route'],
						'controller' => $post['controller'],
						'action' => $post['action'],
						'id' => $post['id'],
						'query' => Json::decode($post['query'], Json::TYPE_ARRAY)
					));
					
					if($setting) {
						// we will just update
						$settings_mdl->update($setting->id, array(
							'value' => $value
						));
						$id = $setting->id;
					} else {
						// we will insert the new setting
						$id = $settings_mdl->insert(
							$user_id,
							$app,
							'landing',
							$value
						);
					}
					
					$con->commit();
				} catch(\Exception $e) {
					$con->rollback();
					
					$p = $e->getPrevious();
					if($p) {
						$e = $p;
					}
					
					if(empty($error)) {
						$error = "Unable to pin it";
					}
					$logger->log(Logger::ERR, "unable to pin it : " . $e->getMessage());
					$logger->log(Logger::ERR, $e->getTraceAsString());
				}
			}			
		}
		
		if(!empty($error)) {
			$this->getResponse()->setStatusCode(500);
			return new JsonModel(array(
				'error' => $error
			));
		}
			
		return new JsonModel(array(
			'id' => $id
		));
	}
	
	public function clearItAction() {
		$sm = $this->getServiceLocator();
		$config = $sm->get('Application\Config');
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$app = $config['app'];
		
		$error = "";
		$settings_mdl = Settings::factory($sm);
		$id = null;
		
		$authentication = $this->plugin('authentication');
		
		if(empty($authentication)) {
			$error = "no authentication plugin";
		} else if(empty($app)) {
			$error = "no app defined";
		} else {
			$user_id = $authentication->getAuthenticatedUserId();
			
			if($this->getRequest()->isPost()) {
				$con = $db->getDriver()->getConnection();
				
				try {
					$con->beginTransaction();
					
					// delete the landing setting
					$settings_mdl->delete(array(
						'user_id' => $user_id,
						'app' => $app,
						'field' => 'landing'
					));					
					
					$con->commit();
				} catch(\Exception $e) {
					$con->rollback();
					
					$p = $e->getPrevious();
					if($p) {
						$e = $p;
					}
					
					if(empty($error)) {
						$error = "Unable to clear it";
					}
					$logger->log(Logger::ERR, "unable to clear it : " . $e->getMessage());
					$logger->log(Logger::ERR, $e->getTraceAsString());
				}
			}			
		}
		
		if(!empty($error)) {
			$this->getResponse()->setStatusCode(500);
			return new JsonModel(array(
				'error' => $error
			));
		}
			
		return new JsonModel(array(
			'id' => $id
		));
	}
	
	public function pinFilterAction() {
		$sm = $this->getServiceLocator();
		$config = $sm->get('Application\Config');
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$app = $config['app'];
		
		$error = "";
		$settings_mdl = Settings::factory($sm);
		$id = null;
		
		$authentication = $this->plugin('authentication');
		
		if(empty($authentication)) {
			$error = "no authentication plugin";
		} else if(empty($app)) {
			$error = "no app defined";
		} else {
			$user_id = $authentication->getAuthenticatedUserId();
			
			if($this->getRequest()->isPost()) {
				$post = $this->params()->fromPost();
				$con = $db->getDriver()->getConnection();
				
				try {
					$con->beginTransaction();
					
					// compute the md5 checksum of the route/controller/action/id
					$checksum = md5("{$post['route']},{$post['controller']},{$post['action']},{$post['id']}");
					
					// check if this setting already exists
					$setting = $settings_mdl->get(array(
						'user_id' => $user_id,
						'app' => $app,
						'field' => "filter_{$checksum}"
					));
					
					$value = Json::encode(array(
						'route' => $post['route'],
						'controller' => $post['controller'],
						'action' => $post['action'],
						'id' => $post['id'],
						'query' => Json::decode($post['query'], Json::TYPE_ARRAY)
					));
					
					if($setting) {
						// we will just update
						$settings_mdl->update($setting->id, array(
							'value' => $value
						));
						$id = $setting->id;
					} else {
						// we will insert the new setting
						$id = $settings_mdl->insert(
							$user_id,
							$app,
							"filter_{$checksum}",
							$value
						);
					}
					
					$con->commit();
				} catch(\Exception $e) {
					$con->rollback();
					
					$p = $e->getPrevious();
					if($p) {
						$e = $p;
					}
					
					if(empty($error)) {
						$error = "Unable to pin filter";
					}
					$logger->log(Logger::ERR, "unable to pin filter : " . $e->getMessage());
					$logger->log(Logger::ERR, $e->getTraceAsString());
				}
			}			
		}
		
		if(!empty($error)) {
			$this->getResponse()->setStatusCode(500);
			return new JsonModel(array(
				'error' => $error
			));
		}
			
		return new JsonModel(array(
			'id' => $id
		));
	}
	
	public function clearFilterAction() {
		$sm = $this->getServiceLocator();
		$config = $sm->get('Application\Config');
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$app = $config['app'];
		
		$error = "";
		$settings_mdl = Settings::factory($sm);
		$id = null;
		
		$authentication = $this->plugin('authentication');
		
		if(empty($authentication)) {
			$error = "no authentication plugin";
		} else if(empty($app)) {
			$error = "no app defined";
		} else {
			$user_id = $authentication->getAuthenticatedUserId();
			
			if($this->getRequest()->isPost()) {
				$post = $this->params()->fromPost();
				$con = $db->getDriver()->getConnection();
				
				try {
					$con->beginTransaction();
					
					// compute the md5 checksum of the route/controller/action/id
					$checksum = md5("{$post['route']},{$post['controller']},{$post['action']},{$post['id']}");
					
					// delete the filter setting
					$settings_mdl->delete(array(
						'user_id' => $user_id,
						'app' => $app,
						'field' => "filter_{$checksum}"
					));
					
					$con->commit();
				} catch(\Exception $e) {
					$con->rollback();
					
					$p = $e->getPrevious();
					if($p) {
						$e = $p;
					}
					
					if(empty($error)) {
						$error = "Unable to clear filter";
					}
					$logger->log(Logger::ERR, "unable to clear filter : " . $e->getMessage());
					$logger->log(Logger::ERR, $e->getTraceAsString());
				}
			}
		}
		
		if(!empty($error)) {
			$this->getResponse()->setStatusCode(500);
			return new JsonModel(array(
				'error' => $error
			));
		}
			
		return new JsonModel(array(
			'id' => $id
		));
	}
}

?>