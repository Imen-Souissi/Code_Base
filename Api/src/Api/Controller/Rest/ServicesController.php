<?php
namespace Api\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Api\Model\ApiServices;

class ServicesController extends AbstractRestfulController {
	public function getList() {
		$apiresources_mdl = ApiServices::factory($this->getServiceLocator());
		$items = $apiresources_mdl->filter($this->getFilter(), $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$apiresources_mdl = ApiServices::factory($this->getServiceLocator());
		$item = $apiresources_mdl->get($id);
		return $item;
	}
}
?>