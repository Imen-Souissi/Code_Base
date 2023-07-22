<?php
namespace Api\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Log\Logger;
use Zend\Form\Factory;

use Api\Model\ApiKeys;
use Api\Model\ApiKeyServiceMethods;
use Api\Model\ApiServiceMethods;

class KeysController extends AbstractActionController {    
	public function indexAction() {
		return new ViewModel(array(
			'add' => $this->params()->fromQuery('add')
		));
	}
	
	 protected function buildAddForm() {
		$factory = new Factory();
		$trimfilter = new \Zend\Filter\StringTrim();
		
		$fields = array('user_id', 'description', 'auto_configure');
		$elements = array();
		$input_filter = array();
		
		foreach($fields as $field) {
			$elements[] = array(
				'spec' => array(
					'type' => 'Zend\Form\Element\Text',
					'name' => $field,
				),
			);
			$input_filter[$field] = array(
				// ensure that the callback validator will be trigger even if the fields are empty
				'continue_if_empty' => true,
				'validators' => array(),
				'filters' => array(
					$trimfilter 
				),
			);
		}
		
		$input_filter['user_id']['validators'][] = new \Zend\Validator\NotEmpty(array(
			'message' => 'Please select a User'
		));
		
		return $factory->createForm(array(
			'hydrator' => 'Zend\Stdlib\Hydrator\ArraySerializable',
			'elements' => $elements,
			'input_filter' => $input_filter,
		));
	}
    
    protected function buildEditForm() {
        return $this->buildAddForm();
    }
	
	public function addAction() {
        $sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
        $config = $sm->get('Config');
        
        $app = (empty($config['api']['app'])) ? 'app' : $config['api']['app'];
		
		$api_keys_mdl = ApiKeys::factory($sm);
        
		$form = $this->buildAddForm();
		$error = "";
		
		$id = null;
		$auto_configure = null;
		
		if($this->getRequest()->isPost()) {
			$form->setData($this->params()->fromPost());
			
			if($form->isValid()) {
				$con = $db->getDriver()->getConnection();
				$post = $form->getData();
				
				try {
					$con->beginTransaction();
					
					$attempts = 0;
					$done = false;
					$auto_configure = intval($post['auto_configure']);
					
					do {
						// generate the key
						$key = strtoupper(dechex(crc32($post['user_id'] . microtime() . rand() . $app)));
						
						// check if this key has been use
						$row = $api_keys_mdl->get(array('key' => $key));
						if($row === false) {						
							$id = $api_keys_mdl->insert(
								$app,
								$key,
								$post['user_id'],
								$post['description'],
								$auto_configure
							);
							$done = true;							
						} else {
							$attempts++;
							$key = null;
						}
					} while(!$done && $attempts < 100);
					
					if(empty($key)) {
						$error = "Unable to generate unique key";
						throw new \Exception($error);
					}
					
					$con->commit();
				} catch(\Exception $e) {
					$con->rollback();
						
					$p = $e->getPrevious();
					if($p) {
						$e = $p;
					}
					
					if(empty($error)) {
						$error = "Unable to complete adding key, please try again";
					}
					$logger->log(Logger::ERR, "unable to add key: " . $e->getMessage());
					$logger->log(Logger::ERR, $e->getTraceAsString());
				}
			} else {
				$messages = $form->getMessages();
				foreach($messages as $field => $msg) {
					$error = array_shift(array_values($msg));
					break;
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
			'id' => $id,
			'auto_configure' => $auto_configure
		));
    }
	
	public function editAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$api_keys_mdl = ApiKeys::factory($sm);
		
		$form = $this->buildEditForm();
		$error = "";
		$id = $this->params()->fromRoute('id');
		
        if($this->getRequest()->isPost()) {
			$form->setData($this->params()->fromPost());
			
			if($form->isValid()) {
				$con = $db->getDriver()->getConnection();
				$post = $form->getData();
				
				try {
					$con->beginTransaction();
					
					$api_keys_mdl->update($id, array(
						'user_id' => $post['user_id'],
						'description' => $post['description'],
						'auto_configure' => intval($post['auto_configure'])
					));
					
					$con->commit();
				} catch(\Exception $e) {
					$con->rollback();
                    
					$p = $e->getPrevious();
					if($p) {
						$e = $p;
					}
					
					if(empty($error)) {
						$error = "Unable to complete updating key, please try again";
					}
					$logger->log(Logger::ERR, "unable to update key: " . $e->getMessage());
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
	
	public function deleteAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
        
		$error = "";
		$id = $this->params()->fromRoute('id');
		
		$api_keys_mdl = ApiKeys::factory($sm);
		
		if($this->getRequest()->isPost()) {			
			$con = $db->getDriver()->getConnection();
			
			try {
				$con->beginTransaction();
				
                $api_keys_mdl->delete($id);
				
				$con->commit();
			} catch(\Zend\Db\Adapter\Exception\InvalidQueryException $e) {
				$con->rollback();
				
				$error = "Can no longer be deleted, please retire instead";
			} catch(\Exception $e) {
				$con->rollback();
				
				$p = $e->getPrevious();
				if($p) {
					$e = $p;
				}
				
				if(empty($error)) {
					$error = "Unable to complete deleting key, please try again";
				}
				$logger->log(Logger::ERR, "unable to delete key: " . $e->getMessage());
				$logger->log(Logger::ERR, $e->getTraceAsString());
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
	
	public function retireAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
        
		$error = "";
		$id = $this->params()->fromRoute('id');
		
		$api_keys_mdl = ApiKeys::factory($sm);
		
		if($this->getRequest()->isPost()) {			
			$con = $db->getDriver()->getConnection();
			
			try {
				$con->beginTransaction();
				
                $api_keys_mdl->update($id, array(
					'active' => 0
				));
				
				$con->commit();
			} catch(\Exception $e) {
				$con->rollback();
				
				$p = $e->getPrevious();
				if($p) {
					$e = $p;
				}
				
				if(empty($error)) {
					$error = "Unable to complete retiring key, please try again";
				}
				$logger->log(Logger::ERR, "unable to retire key: " . $e->getMessage());
				$logger->log(Logger::ERR, $e->getTraceAsString());
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
	
	public function viewAction() {
		$sm = $this->getServiceLocator();
        
		$id = $this->params()->fromRoute('id');
		
        $api_keys_mdl = ApiKeys::factory($sm);
		
		$view = new ViewModel(array(
			'id' => $id
		));
		
		$key = $api_keys_mdl->get($id);		
		if($key === false) {
			$view->setTemplate('api/keys/invalid-key');
		} else {
			$view->setVariables(array(
				'key' => $key->getArrayCopy()
			));
		}
	
		return $view;
	}
	
	public function assignServiceMethodsAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$error = "";
		$id = $this->params()->fromRoute('id');
		
		$api_keys_mdl = ApiKeys::factory($sm);
		$api_key_service_methods_mdl = ApiKeyServiceMethods::factory($sm);
		
		if($this->getRequest()->isPost()) {
			$service_methods = $this->params()->fromPost('service_methods');
			$service_methods = split(',', $service_methods);
			$con = $db->getDriver()->getConnection();
			
			try {
				$con->beginTransaction();
				
				if(count($service_methods) > 0) {
					foreach($service_methods as $service_method_id) {
						if(!$api_key_service_methods_mdl->exists(array(
							'key_id' => $id,
							'service_method_id' => $service_method_id
						))) {
							$api_key_service_methods_mdl->insert($id, $service_method_id);
						}
					}
				}
				
				$api_keys_mdl->computeStats($id);
				
				$con->commit();
			} catch(\Exception $e) {
				$con->rollback();
				
				$p = $e->getPrevious();
				if($p) {
					$e = $p;
				}
				
				if(empty($error)) {
					$error = "Unable to complete assigning service methods, please try again";
				}
				$logger->log(Logger::ERR, "unable to assign service methods: " . $e->getMessage());
				$logger->log(Logger::ERR, $e->getTraceAsString());
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
	
	public function unassignServiceMethodsAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$error = "";
		$id = $this->params()->fromRoute('id');
		
		$api_keys_mdl = ApiKeys::factory($sm);
		$api_key_service_methods_mdl = ApiKeyServiceMethods::factory($sm);
		
		if($this->getRequest()->isPost()) {
			$service_method_ids = $this->params()->fromPost('service_method_id');
			$service_method_ids = split(',', $service_method_ids);
			
			$con = $db->getDriver()->getConnection();
			
			try {
				$con->beginTransaction();
				
				foreach($service_method_ids as $service_method_id) {
					$api_key_service_methods_mdl->delete(array(
						'key_id' => $id,
						'service_method_id' => $service_method_id
					));
				}
				
				$api_keys_mdl->computeStats($id);
				
				$con->commit();
			} catch(\Exception $e) {
				$con->rollback();
				
				$p = $e->getPrevious();
				if($p) {
					$e = $p;
				}
				
				if(empty($error)) {
					$error = "Unable to complete unassigning service method, please try again";
				}
				$logger->log(Logger::ERR, "unable to unassign service method: " . $e->getMessage());
				$logger->log(Logger::ERR, $e->getTraceAsString());
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
	
	public function autoConfigureAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');		
		$logger = $sm->get('logger');
		
		$error = "";
		$id = $this->params()->fromRoute('id');
		if(empty($id)) {
			$id = $this->params()->fromPost('id');
		}
		
		$api_keys_mdl = ApiKeys::factory($sm);
		
		if($this->getRequest()->isPost()) {			
			$con = $db->getDriver()->getConnection();
			
			try {
				$con->beginTransaction();
				
				$ids = split(',', $id);
				
				foreach($ids as $iid) {
					$auto_configure = $this->params()->fromPost('auto_configure');
					$auto_configure = (empty($auto_configure)) ? 0 : intval($auto_configure);
					
					$api_keys_mdl->update($iid, array(
						'auto_configure' => $auto_configure
					));
				}
				
				$con->commit();
			} catch(\Exception $e) {
				$con->rollback();
				
				$p = $e->getPrevious();
				if($p) {
					$e = $p;
				}
				
				if(empty($error)) {
					$error = "Unable to set auto configure, please try again";
				}
				$logger->log(Logger::ERR, "unable to set auto configure: " . $e->getMessage());
				$logger->log(Logger::ERR, $e->getTraceAsString());
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