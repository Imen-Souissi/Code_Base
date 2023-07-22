<?php
namespace Contract\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Db\Sql\Expression;

use Contract\Model\NotificationSchedules;

class NotificationSchedulesController extends AbstractRestfulController {
	public function getList() {
		$notification_schedules_mdl = NotificationSchedules::factory($this->getServiceLocator());
		
		$filter = $this->getFilter();
		
		$items = $notification_schedules_mdl->filterDaysStr($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$notification_schedules_mdl = NotificationSchedules::factory($this->getServiceLocator());
		$item = $notification_schedules_mdl->getDaysStr($id);
		return $item;
	}
}
?>