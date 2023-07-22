<?php
namespace Auth\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Db\Sql\Expression;

use Auth\Model\HrUsers;

class AvailableUsersController extends AbstractRestfulController {
	public function getList() {
		$hr_users_mdl = HrUsers::factory($this->getServiceLocator());
		
		$filter = $this->getFilter();		
		$items = $hr_users_mdl->filterAvailable($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$hr_users_mdl = HrUsers::factory($this->getServiceLocator());
		return $hr_users_mdl->get($id);
	}
}
?>