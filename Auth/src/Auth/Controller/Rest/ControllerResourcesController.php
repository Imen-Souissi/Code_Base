<?php
namespace Auth\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Db\Sql\Expression;

use Auth\Model\SecurityResources;

class ControllerResourcesController extends AbstractRestfulController {
	public function getList() {
		$security_resources_mdl = SecurityResources::factory($this->getServiceLocator());
		
		$filter = $this->getFilter();
		
		$items = $security_resources_mdl->filterController($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$security_resources_mdl = SecurityResources::factory($this->getServiceLocator());
		return $security_resources_mdl->get($id);
	}
}
?>