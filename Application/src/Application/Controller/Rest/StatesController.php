<?php
namespace Application\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Application\Model\States;

class StatesController extends AbstractRestfulController {
	public function getList() {
		$states_mdl = States::factory($this->getServiceLocator());
		$items = $states_mdl->filter($this->getFilter(), $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$states_mdl = States::factory($this->getServiceLocator());
		$item = $states_mdl->get($id);
		return $item;
	}
}
?>