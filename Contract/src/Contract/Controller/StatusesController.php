<?php
namespace Contract\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Log\Logger;
use Zend\Db\Sql\Expression;

use Contract\Model\ContractStatuses;

class StatusesController extends AbstractActionController {
	
	public function indexAction() {
		return new ViewModel(array(
		));
	}
	
	public function addAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');
		
		$name = trim($this->params()->fromPost('name'));
		$user_selectable = trim($this->params()->fromPost('user_selectable'));

		$logger->log(Logger::DEBUG, "attempting to add new contract status {$name}");
		
		$contract_statuses_mdl = ContractStatuses::factory($sm);
		
		$status = 0;
		//contract status matching name already exists
		if($contract_statuses_mdl->get(array(
			'name' => $name
		))) {
			$error = "Contract status matching name already exists";
		}
		else {
			$result = $contract_statuses_mdl->insert(array(
				'name' => $name,
				'user_selectable' => $user_selectable,
				'active' => 1
			));
			if($result) {
				$status = 1;
			}
			else {
				$error = "Unable to complete adding contract status, please try again";
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
		$user_selectable = trim($this->params()->fromPost('user_selectable'));
		$active = trim($this->params()->fromPost('active'));
		
		$contract_statuses_mdl = ContractStatuses::factory($sm);
		
		$status = 0;
		if(!$contract_statuses_mdl->exists($id)) {
			$error = "Contract status no longer exists";
		}
		//contract status name already exists
		else if($contract_statuses_mdl->get(array(
				'name' => $name,
				'!id' => $id
		))) {
			$error = "Contract status with matching name already exists";
		}
		else {
			$contract_statuses_mdl->update($id, array(
				'name' => $name,
				'user_selectable' => $user_selectable,
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