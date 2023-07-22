<?php
namespace Contract\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Db\Sql\Expression;

use Contract\Model\ContractDevices;

class ContractDevicesController extends AbstractRestfulController {
	public function getList() {
		$contract_devices_mdl = ContractDevices::factory($this->getServiceLocator());
		
		$filter = $this->getFilter();
		
		$items = $contract_devices_mdl->filter($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$contract_devices_mdl = ContractDevices::factory($this->getServiceLocator());
		$item = $contract_devices_mdl->get($id);
		return $item;
	}
}
?>