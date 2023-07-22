<?php
namespace Application\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Application\Model\Countries;

class CountriesController extends AbstractRestfulController {
	public function getList() {
		$countries_mdl = Countries::factory($this->getServiceLocator());
		$items = $countries_mdl->filter($this->getFilter(), $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$countries_mdl = Countries::factory($this->getServiceLocator());
		$item = $countries_mdl->get($id);
		return $item;
	}
}
?>