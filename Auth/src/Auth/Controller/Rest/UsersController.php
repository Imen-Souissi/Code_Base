<?php
namespace Auth\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Db\Sql\Expression;

use Auth\Model\SecurityUserRoleLinks;

class UsersController extends AbstractRestfulController {
	public function getList() {
		$security_user_role_links_mdl = SecurityUserRoleLinks::factory($this->getServiceLocator());
		
		$filter = $this->getFilter();
		
		$items = $security_user_role_links_mdl->filter($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$security_user_role_links_mdl = SecurityUserRoleLinks::factory($this->getServiceLocator());
		return $security_user_role_links_mdl->get($id);
	}
}
?>