<?php
namespace Contract\Controller\Rest\Contracts;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Db\Sql\Expression;

use Contract\Model\Contracts\Gem\Labviews;

class LabviewsController extends AbstractRestfulController {
	public function getList() {
		$contract_id = $this->params()->fromRoute('contract_id');
		
		$labviews_mdl = Labviews::factory($this->getServiceLocator());
		
		$filter = $this->getFilter();
		$filter['contract_id'] = $contract_id;
		
		$items = $labviews_mdl->filterContract($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$contract_id = $this->params()->fromRoute('contract_id');
		
		$labviews_mdl = Labviews::factory($this->getServiceLocator());
		$item = $labviews_mdl->getContract(array(
			'contract_id' => $project_id,
			'labview_id' => $id
		));
		return $item;
	}
}
?>