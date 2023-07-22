<?php
namespace Auth\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Db\Sql\Expression;

use Auth\Model\SecurityPermissions;

class PermissionsController extends AbstractRestfulController {
	public function getList() {
		$security_permissions_mdl = SecurityPermissions::factory($this->getServiceLocator());
		
		$filter = $this->getFilter();
		
		// only show permissions mapped to roles with a lower rights level or not mapped yet
		$filter['rights_level'] = new Expression("R.rights_level IS NULL OR R.rights_level <= ?", max($this->authorization()->getRoles()));
		
		$items = $security_permissions_mdl->filter($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$security_permissions_mdl = SecurityPermissions::factory($this->getServiceLocator());
		return $security_permissions_mdl->get($id);
	}
}
?>