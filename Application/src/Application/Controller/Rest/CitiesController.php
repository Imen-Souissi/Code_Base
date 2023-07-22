<?php
namespace Application\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Application\Model\Cities;

class CitiesController extends AbstractRestfulController {
	public function getList() {
		$cities_mdl = Cities::factory($this->getServiceLocator());
		$items = $cities_mdl->filter($this->getFilter(), $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$cities_mdl = Cities::factory($this->getServiceLocator());
		$item = $cities_mdl->get($id);
		return $item;
	}
}
?>