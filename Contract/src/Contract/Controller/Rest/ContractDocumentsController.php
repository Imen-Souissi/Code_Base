<?php
namespace Contract\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Db\Sql\Expression;

use Contract\Model\ContractDocuments;

class ContractDocumentsController extends AbstractRestfulController {
	public function getList() {
		$contract_documents_mdl = ContractDocuments::factory($this->getServiceLocator());
		
		$filter = $this->getFilter();
		
		$items = $contract_documents_mdl->filter($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$contract_documents_mdl = ContractDocuments::factory($this->getServiceLocator());
		$item = $contract_documents_mdl->get($id);
		return $item;
	}
}
?>