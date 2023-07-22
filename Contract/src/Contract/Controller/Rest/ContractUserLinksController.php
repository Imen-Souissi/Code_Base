<?php
namespace Contract\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Db\Sql\Expression;

use Contract\Model\ContractUserLinks;

class ContractUserLinksController extends AbstractRestfulController {
	public function getList() {
		$contract_user_links_mdl = ContractUserLinks::factory($this->getServiceLocator());
		
		$filter = $this->getFilter();
		
		$items = $contract_user_links_mdl->filter($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$contract_user_links_mdl = ContractUserLinks::factory($this->getServiceLocator());
		$item = $contract_user_links_mdl->get($id);
		return $item;
	}
}
?>