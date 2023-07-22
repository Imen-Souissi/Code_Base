<?php
namespace Auth\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Db\Sql\Expression;

use Auth\Model\SecurityRoles;

class RolesController extends AbstractRestfulController {
	public function getList() {
		$sm = $this->getServiceLocator();
		
		$security_roles_mdl = SecurityRoles::factory($sm);
		
		$filter = $this->getFilter();
		
		$filter['<=rights_level'] = max($this->authorization()->getRoles());
		$items = $security_roles_mdl->filter($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$security_roles_mdl = SecurityRoles::factory($this->getServiceLocator());
		return $security_roles_mdl->get($id);
	}
}
?>