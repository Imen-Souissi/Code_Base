<?php
namespace Contract\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Db\Sql\Expression;

use Contract\Model\PaymentSchedules;

class PaymentSchedulesController extends AbstractRestfulController {
	public function getList() {
		$payment_schedules_mdl = PaymentSchedules::factory($this->getServiceLocator());
		
		$filter = $this->getFilter();
		
		$items = $payment_schedules_mdl->filter($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$payment_schedules_mdl = PaymentSchedules::factory($this->getServiceLocator());
		$item = $payment_schedules_mdl->get($id);
		return $item;
	}
}
?>