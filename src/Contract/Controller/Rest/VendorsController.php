<?php
namespace Contract\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Db\Sql\Expression;

use Contract\Model\Vendors;

class VendorsController extends AbstractRestfulController {
	public function getList() {
		$vendors_mdl = Vendors::factory($this->getServiceLocator());
		
		$filter = $this->getFilter();
		
		$items = $vendors_mdl->filter($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$vendors_mdl = Vendors::factory($this->getServiceLocator());
		$item = $vendors_mdl->get($id);
		return $item;
	}
}
?>