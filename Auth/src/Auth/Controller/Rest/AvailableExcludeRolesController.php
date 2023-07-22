<?php
namespace Auth\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Db\Sql\Expression;

use Auth\Model\SecurityRoles;

class AvailableExcludeRolesController extends AbstractRestfulController {
	public function getList() {
		$security_roles_mdl = SecurityRoles::factory($this->getServiceLocator());
		
		$filter = $this->getFilter();
		
		$filter['<=rights_level'] = max($this->authorization()->getRoles());
		$items = $security_roles_mdl->filterExcludedAvailable($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$security_roles_mdl = SecurityRoles::factory($this->getServiceLocator());
		return $security_roles_mdl->getExcludedAvailable($id);
	}
}
?>