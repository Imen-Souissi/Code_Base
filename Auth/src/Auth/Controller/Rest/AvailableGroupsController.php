<?php
namespace Auth\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Db\Sql\Expression;

use Auth\Model\HrGroups;

class AvailableGroupsController extends AbstractRestfulController {
	public function getList() {
		$hr_groups_mdl = HrGroups::factory($this->getServiceLocator());
		
		$filter = $this->getFilter();		
		$items = $hr_groups_mdl->filterAvailable($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$hr_groups_mdl = HrGroups::factory($this->getServiceLocator());
		return $hr_groups_mdl->get($id);
	}
}
?>