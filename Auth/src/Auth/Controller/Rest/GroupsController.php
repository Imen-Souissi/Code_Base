<?php
namespace Auth\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Db\Sql\Expression;

use Auth\Model\SecurityGroupRoleLinks;

class GroupsController extends AbstractRestfulController {
	public function getList() {
		$security_group_role_links = SecurityGroupRoleLinks::factory($this->getServiceLocator());
		
		$filter = $this->getFilter();
		
		$items = $security_group_role_links->filter($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$security_group_role_links = SecurityGroupRoleLinks::factory($this->getServiceLocator());
		return $security_group_role_links->get($id);
	}
}
?>