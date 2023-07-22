<?php
namespace Api\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Log\Logger;
use Zend\Form\Factory;

use Api\Model\ApiServices;
use Api\Model\ApiServiceMethods;

class ServicesController extends AbstractActionController {    
	public function indexAction() {
		return new ViewModel();
	}
    
    protected function buildAddForm() {
		$factory = new Factory();
		$trimfilter = new \Zend\Filter\StringTrim();
		
		$fields = array('name', 'version');
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
		
		$input_filter['name']['validators'][] = new \Zend\Validator\NotEmpty(array(
			'message' => 'Please provide a Name'
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
    
	protected function buildAddMethodForm() {
		$factory = new Factory();
		$trimfilter = new \Zend\Filter\StringTrim();
		
		$fields = array('method', 'security_resource_id');
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
		
		$input_filter['method']['validators'][] = new \Zend\Validator\NotEmpty(array(
			'message' => 'Please provide a Method'
		));
		
		return $factory->createForm(array(
			'hydrator' => 'Zend\Stdlib\Hydrator\ArraySerializable',
			'elements' => $elements,
			'input_filter' => $input_filter,
		));
	}
    
    protected function buildEditMethodForm() {
		$factory = new Factory();
		$trimfilter = new \Zend\Filter\StringTrim();
		
		$fields = array('method_id', 'method', 'security_resource_id');
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
		
		$input_filter['method_id']['validators'][] = new \Zend\Validator\NotEmpty(array(
			'message' => 'Invalid Method'
		));
		$input_filter['method']['validators'][] = new \Zend\Validator\NotEmpty(array(
			'message' => 'Please provide a Method'
		));
		
		return $factory->createForm(array(
			'hydrator' => 'Zend\Stdlib\Hydrator\ArraySerializable',
			'elements' => $elements,
			'input_filter' => $input_filter,
		));
	}
	
    public function addAction() {
        $sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		$config = $sm->get('Config');
        
        $app = (empty($config['api']['app'])) ? 'app' : $config['api']['app'];
        $api_services_mdl = ApiServices::factory($sm);
        
		$form = $this->buildAddForm();
		$error = "";
		
		if($this->getRequest()->isPost()) {
			$form->setData($this->params()->fromPost());
			
			if($form->isValid()) {
				$con = $db->getDriver()->getConnection();
				$post = $form->getData();
				
				try {
					$con->beginTransaction();
					
					// check if this name is used
					$service = $api_services_mdl->get(array(
                        'app' => $app,
						'name' => $post['name'],
						'version' => $post['version']
					));
					if($service !== false) {
						$error = "Service name & version already exists";
						throw new \Exception($error);
					}
					
					$id = $api_services_mdl->insert(
                        $app,
						$post['name'],
						$post['version']
					);
					
					$con->commit();
				} catch(\Exception $e) {
					$con->rollback();
						
					$p = $e->getPrevious();
					if($p) {
						$e = $p;
					}
					
					if(empty($error)) {
						$error = "Unable to complete adding service, please try again";
					}
					$logger->log(Logger::ERR, "unable to add service: " . $e->getMessage());
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
			'id' => $id
		));
    }
    
    public function editAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		$config = $sm->get('Config');
        
		$form = $this->buildEditForm();
		$error = "";
		$id = $this->params()->fromRoute('id');
		
        $app = (empty($config['api']['app'])) ? 'app' : $config['api']['app'];
        $api_services_mdl = ApiServices::factory($sm);
		
        if($this->getRequest()->isPost()) {
			$form->setData($this->params()->fromPost());
			
			if($form->isValid()) {
				$con = $db->getDriver()->getConnection();
				$post = $form->getData();
				
				try {
					$con->beginTransaction();
					
					$post['version'] = (empty($post['version'])) ? 0 : $post['version'];
					
					// check if this name is used
					$ex_service = $api_services_mdl->get(array(
                        'app' => $app,
						'name' => $post['name'],
						'version' => $post['version'],
						'!id' => $id
					));
					if($ex_service !== false) {
						$error = "Service name already exists";
						throw new \Exception($error);
					}
					
					$api_services_mdl->update($id, array(
						'name' => $post['name'],
						'version' => $post['version']
					));
					
					$con->commit();
				} catch(\Exception $e) {
					$con->rollback();
                    
					$p = $e->getPrevious();
					if($p) {
						$e = $p;
					}
					
					if(empty($error)) {
						$error = "Unable to complete updating service, please try again";
					}
					$logger->log(Logger::ERR, "unable to update service: " . $e->getMessage());
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
		
        $api_services_mdl = ApiServices::factory($sm);
		
		if($this->getRequest()->isPost()) {			
			$con = $db->getDriver()->getConnection();
			
			try {
				$con->beginTransaction();
				
                $api_services_mdl->delete($id);
				
				$con->commit();
			} catch(\Exception $e) {
				$con->rollback();
					
				$p = $e->getPrevious();
				if($p) {
					$e = $p;
				}
				
				if(empty($error)) {
					$error = "Unable to complete deleting service, please try again";
				}
				$logger->log(Logger::ERR, "unable to delete service: " . $e->getMessage());
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
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		$config = $sm->get('Config');
        
		$error = "";
		$id = $this->params()->fromRoute('id');
		
        $api_services_mdl = ApiServices::factory($sm);
		
		$service = $api_services_mdl->get($id);		
		
		return new ViewModel(array(
			'id' => $id,
			'service' => ($service !== false) ? $service->getArrayCopy() : array()
		));
	}
	
	public function addMethodAction() {
        $sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		$config = $sm->get('Config');
        
		$id = $this->params()->fromRoute('id');
		
        $api_services_mdl = ApiServices::factory($sm);
        $api_service_methods_mdl = ApiServiceMethods::factory($sm);
		
		$form = $this->buildAddMethodForm();
		$error = "";
		
		if($this->getRequest()->isPost()) {
			$form->setData($this->params()->fromPost());
			
			if($form->isValid()) {
				$con = $db->getDriver()->getConnection();
				$post = $form->getData();
				
				try {
					$con->beginTransaction();
					
					// check if this method is used
					$method = $api_service_methods_mdl->get(array(
                        'service_id' => $id,
						'method' => $post['method']
					));
					if($method !== false) {
						$error = "Service method already exists";
						throw new \Exception($error);
					}
					
					$api_service_methods_mdl->insert(
                        $id,
						$post['method'],
						$post['security_resource_id']
					);
					
					// compute the status for this service
					$api_services_mdl->computeStats($id);
					
					$con->commit();
				} catch(\Exception $e) {
					$con->rollback();
					
					$p = $e->getPrevious();
					if($p) {
						$e = $p;
					}
					
					if(empty($error)) {
						$error = "Unable to complete adding service method, please try again";
					}
					$logger->log(Logger::ERR, "unable to add service method: " . $e->getMessage());
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
			'id' => $id
		));
    }
	
	public function editMethodAction() {
        $sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		$config = $sm->get('Config');
        
		$id = $this->params()->fromRoute('id');
		
        $api_services_mdl = ApiServices::factory($sm);
        $api_service_methods_mdl = ApiServiceMethods::factory($sm);
		
		$form = $this->buildEditMethodForm();
		$error = "";
		
		if($this->getRequest()->isPost()) {
			$form->setData($this->params()->fromPost());
			
			if($form->isValid()) {
				$con = $db->getDriver()->getConnection();
				$post = $form->getData();
				
				try {
					$con->beginTransaction();
					
					// check if this method is used
					$method = $api_service_methods_mdl->get(array(
                        'service_id' => $id,
						'!id' => $post['method_id'],
						'method' => $post['method']
					));
					if($method !== false) {
						$error = "Service method already exists";
						throw new \Exception($error);
					}
					
					$api_service_methods_mdl->update($post['method_id'], array(
						'method' => $post['method'],
						'security_resource_id' => $post['security_resource_id']
					));
					
					$con->commit();
				} catch(\Exception $e) {
					$con->rollback();
					
					$p = $e->getPrevious();
					if($p) {
						$e = $p;
					}
					
					if(empty($error)) {
						$error = "Unable to complete updating service method, please try again";
					}
					$logger->log(Logger::ERR, "unable to update service method: " . $e->getMessage());
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
			'id' => $id
		));
    }
	
	public function deleteMethodAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		$config = $sm->get('Config');
        
		$error = "";
		$id = $this->params()->fromRoute('id');
		
        $api_services_mdl = ApiServices::factory($sm);
		$api_service_methods_mdl = ApiServiceMethods::factory($sm);
		
		if($this->getRequest()->isPost()) {
			$post = $this->params()->fromPost();
			$con = $db->getDriver()->getConnection();
			
			try {
				$con->beginTransaction();
				
                $api_service_methods_mdl->delete($post['method_id']);
				$api_services_mdl->computeStats($id);
				
				$con->commit();
			} catch(\Exception $e) {
				$con->rollback();
					
				$p = $e->getPrevious();
				if($p) {
					$e = $p;
				}
				
				if(empty($error)) {
					$error = "Unable to complete deleting service method, please try again";
				}
				$logger->log(Logger::ERR, "unable to delete service method: " . $e->getMessage());
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