<?php
namespace Contract\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Db\Sql\Expression;

use Contract\Model\CalendarQuarters;
use Contract\Model\Contracts;

class PaymentByQuarterController extends AbstractRestfulController {
	public function getList() {
		$sm = $this->getServiceLocator();
		
		$calendar_quarters_mdl = CalendarQuarters::factory($sm);
		$contracts_mdl = Contracts::factory($sm);
		
		$filter = $this->getFilter();
		$filter2 = array();
		
		//we'll first determine the contracts we have access to and then use them to determine payment run rates
		if(!$this->authorization()->isPermitted('Contract::Contracts', 'access-all')) {
			$user_id = $this->authentication()->getAuthenticatedUserId();
			$labview_ids = $this->authorization()->getLabviewIds();
			
			if(empty($labview_ids) || count($labview_ids) == 0) {
				$labview_ids = array(0);
			}
			
			$lqs = implode(',', array_fill(0, count($labview_ids), '?'));
			
			$qvals = array_merge($labview_ids, array($user_id, $user_id));
			
			$table = $contracts_mdl->getTableName();
			$filter2['access'] = new Expression("MAX(LVC.labview_id IN ({$lqs}) OR {$table}.created_by_id = ? OR CUL.user_id = ?)", $qvals);
		}
		
		$items2 = $contracts_mdl->filterMore($filter2, array(), array(), $total2);
		foreach($items2 AS $item) {
			$filter['contract_id'][] = $item->id;
		}
		
		$items = $calendar_quarters_mdl->filterPaymentByQuarter($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
}
?>