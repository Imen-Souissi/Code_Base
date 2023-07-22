<?php
namespace Contract\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Db\Sql\Expression;

use Contract\Model\Payments;

class PaymentsController extends AbstractRestfulController {
	public function getList() {
		$sm = $this->getServiceLocator();
		
		$payments_mdl = Payments::factory($sm);
		
		$filter = $this->getFilter();
		
		if(!$this->authorization()->isPermitted('Contract::Contracts', 'access-all')) {
			$user_id = $this->authentication()->getAuthenticatedUserId();
			$labview_ids = $this->authorization()->getLabviewIds();
			
			if(empty($labview_ids) || count($labview_ids) == 0) {
				$labview_ids = array(0);
			}
			
			$lqs = implode(',', array_fill(0, count($labview_ids), '?'));
			
			$qvals = array_merge($labview_ids, array($user_id, $user_id));
			
			$filter['access'] = new Expression("MAX(LVC.labview_id IN ({$lqs}) OR C.created_by_id = ? OR CUL.user_id = ?)", $qvals);
		}
		
		$items = $payments_mdl->filter($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
}
?>