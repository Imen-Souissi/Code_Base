<?php
namespace Contract\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Db\Sql\Expression;

use Contract\Model\CalendarQuarters;

class CalendarQuartersController extends AbstractRestfulController {
	public function getList() {
		$calendar_quarters_mdl = CalendarQuarters::factory($this->getServiceLocator());
		
		$filter = $this->getFilter();
		
		$items = $calendar_quarters_mdl->filter($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$calendar_quarters_mdl = CalendarQuarters::factory($this->getServiceLocator());
		$item = $calendar_quarters_mdl->get($id);
		return $item;
	}
}
?>