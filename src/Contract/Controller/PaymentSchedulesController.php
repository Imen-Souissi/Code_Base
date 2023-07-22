<?php
namespace Contract\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Log\Logger;

use Contract\Model\Contracts;
use Contract\Model\PaymentSchedules;

class PaymentSchedulesController extends AbstractActionController {
	
	public function indexAction() {
		return new ViewModel(array(
		));
	}
	
	public function addAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');
	
		$name = trim($this->params()->fromPost('name'));
		$months = trim($this->params()->fromPost('months'));
	
		$active = 1;

		$logger->log(Logger::DEBUG, "attempting to add new payment schedule {$name}");
	
		$payment_schedules_mdl = PaymentSchedules::factory($sm);
	
		$status = 0;
		//payment schedule matching name already exists
		if($payment_schedules_mdl->exists(array(
			'name' => $name,
		))) {
			$error = "Payment schedule matching name already exists";
		}
		//payment schedule matching months already exists
		else if($payment_schedules_mdl->exists(array(
			'next_payment_months' => $months
		))) {
			$error = "Payment schedule matching months already exists";
		}
		else {
			$result = $payment_schedules_mdl->insert($name,$months,$active);
			
			$status = 1;
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
		$next_payment_months = trim($this->params()->fromPost('next_payment_months'));
		$active = trim($this->params()->fromPost('active'));
		if ($active!=1) { 
			$active = 0;
		}

		$logger->log(Logger::DEBUG, "attempting to edit payment schedule ID {$id}");
	
		$payment_schedules_mdl = PaymentSchedules::factory($sm);
	
		$status = 0;
		//verify payment schedule still exists
		if(!$payment_schedules_mdl->exists($id)) {
			$error = "Payment schedule no longer exists";
		}
		//payment schedule matching name already exists
		else if($payment_schedules_mdl->exists(array(
			'!id' => $id,
			'name' => $name,
		))) {
			$error = "Payment schedule matching name already exists";
		}
		//payment schedule matching months already exists
		else if($payment_schedules_mdl->exists(array(
			'!id' => $id,
			'next_payment_months' => $months
		))) {
			$error = "Payment schedule matching months already exists";
		}
		else {
			$result = $payment_schedules_mdl->update($id, array(
				'name' => $name,
				'next_payment_months' => $next_payment_months,
				'active' => $active
			));
				
			$status = 1;
		}
	
		return new JsonModel(array(
			'status' => $status,
			'error' => $error
		));
	}

	public function deleteAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');
	
		$id = trim($this->params()->fromPost('id'));

		$logger->log(Logger::DEBUG, "attempting to delete payment schedule ID {$id}");
	
		$contracts_mdl = Contracts::factory($sm);
		$payment_schedules_mdl = PaymentSchedules::factory($sm);
	
		$status = 0;
		//verify payment schedule isn't being used
		if($contracts_mdl->exists(array(
			'payment_schedule_id' => $id
		))) {
			$error = "Payment schedule cannot be deleted as it is currently being used by a contract";
		}
		else {
			$payment_schedules_mdl->delete($id);
	
			$status = 1;
		}
	
		return new JsonModel(array(
			'status' => $status,
			'error' => $error
		));
	}
}
?>