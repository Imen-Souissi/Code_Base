<?php
namespace Contract\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Db\Sql\Expression;

use Contract\Model\VendorContacts;

class VendorContactsController extends AbstractRestfulController {
	public function getList() {
		$vendor_contacts_mdl = VendorContacts::factory($this->getServiceLocator());
		
		$filter = $this->getFilter();
		
		$items = $vendor_contacts_mdl->filter($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$vendor_contacts_mdl = VendorContacts::factory($this->getServiceLocator());
		$item = $vendor_contacts_mdl->get($id);
		return $item;
	}
}
?>