<?php
namespace Ticket\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Ticket\Model\Systems;

class SystemsController extends AbstractRestfulController {
	public function getList() {
		$sm = $this->getServiceLocator();		
		$systems_mdl = Systems::factory($sm);
		
		$items = $systems_mdl->filter($this->getFilter(), $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$systems_mdl = Systems::factory($this->getServiceLocator());
		return $systems_mdl->get($id);
	}
}
?>