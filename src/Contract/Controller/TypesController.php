<?php
namespace Contract\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Log\Logger;
use Zend\Db\Sql\Expression;

use Contract\Model\ContractTypes;

class TypesController extends AbstractActionController {
	
	public function indexAction() {
		return new ViewModel(array(
		));
	}
	
	public function addAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');
		
		$name = trim($this->params()->fromPost('name'));
		
		$logger->log(Logger::DEBUG, "attempting to add new contract type {$name}");
		
		$contract_types_mdl = ContractTypes::factory($sm);

		$status = 0;
		//contract_type name already exists
		if($contract_types_mdl->exists(array(
			'name' => $name
		))) {
			$error = "Contract type with matching name already exists";
		}
		else {
			$result = $contract_types_mdl->insert(array(
				'name' => $name,
				'active' => 1
			));
			if($result) {
				$status = 1;
			}
			else {
				$error = "Unable to complete adding contract type, please try again";
			}
		}
		
		return new JsonModel(array(
			'status' => $status,
			'error' => $error
		));
	}	
	
	public function editAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');
		
		$id = trim($this->params()->fromPost('id'));
		$name = trim($this->params()->fromPost('name'));
		$active = trim($this->params()->fromPost('active'));
		
		$contract_types_mdl = ContractTypes::factory($sm);

		$status = 0;
		if(!$contract_types_mdl->exists($id)) {
			$error = "Contract type no longer exists";
		}
		//contract type name already exists
		else if($contract_types_mdl->exists(array(
			'name' => $name,
			'!id' => $id
		))) {
			$error = "Contract type with matching name already exists";
		}
		else {
			$contract_types_mdl->update($id, array(
				'name' => $name,
				'active' => $active,
				'etime' => new Expression("NOW()")
			));
			
			$status = 1;
		}

		return new JsonModel(array(
			'status' => $status,
			'error' => $error
		));
	}
}
?>