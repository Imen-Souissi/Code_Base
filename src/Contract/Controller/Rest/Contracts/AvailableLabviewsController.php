<?php
namespace Contract\Controller\Rest\Contracts;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Db\Sql\Expression;

use Contract\Model\Contracts\Gem\Labviews;

class AvailableLabviewsController extends AbstractRestfulController {
	public function getList() {	
		$contract_id = $this->params()->fromRoute('contract_id');
		
		$sm = $this->getServiceLocator();
		
		$labviews_mdl = Labviews::factory($sm);

		$config = $sm->get('Config');
		$gem_ns = $config['auth']['gem_ns'];
		
		$filter = $this->getFilter();
		$filter['contract_id'] = $contract_id;

		$labview_ids = $this->authorization()->getLabviewIds($gem_ns);
		
		// if the user does not have full access to devices then we will restrict
		if(!$this->authorization()->isPermitted('Gem::Labviews', 'access-all', $gem_ns)) {
			if(!empty($labview_ids)) {
				$filter['id'] = $labview_ids;
			} else {
				$filter['id'] = 0;
			}
		}
		// otherwise this user has access to everything
		
		$items = $labviews_mdl->filterContractAvailable($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
}
?>