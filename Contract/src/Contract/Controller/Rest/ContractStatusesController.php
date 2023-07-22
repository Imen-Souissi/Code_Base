<?php
namespace Contract\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Db\Sql\Expression;

use Contract\Model\ContractStatuses;

class ContractStatusesController extends AbstractRestfulController {
	public function getList() {
		$contract_statuses_mdl = ContractStatuses::factory($this->getServiceLocator());
		
		$filter = $this->getFilter();
		
		$items = $contract_statuses_mdl->filter($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$contract_statuses_mdl = ContractStatuses::factory($this->getServiceLocator());
		$item = $contract_statuses_mdl->get($id);
		return $item;
	}
}
?>