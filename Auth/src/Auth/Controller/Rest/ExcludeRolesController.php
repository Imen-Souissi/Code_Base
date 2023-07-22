<?php
namespace Auth\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Db\Sql\Expression;

use Auth\Model\SecurityRoleExcludeRoles;

class ExcludeRolesController extends AbstractRestfulController {
	public function getList() {
		$security_role_exclude_roles_mdl = SecurityRoleExcludeRoles::factory($this->getServiceLocator());
		
		$filter = $this->getFilter();
		
		$filter['<=rights_level'] = max($this->authorization()->getRoles());
		$items = $security_role_exclude_roles_mdl->filterExcluded($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$security_role_exclude_roles_mdl = SecurityRoles::factory($this->getServiceLocator());
		return $security_role_exclude_roles_mdl->getExcluded($id);
	}
}
?>