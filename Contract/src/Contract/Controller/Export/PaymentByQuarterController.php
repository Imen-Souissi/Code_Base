<?php
namespace Contract\Controller\Export;

use Els\Mvc\Controller\AbstractExportController;
use Zend\Db\Sql\Expression;

use Contract\Model\CalendarQuarters;
use Contract\Model\Contracts;

class PaymentByQuarterController extends AbstractExportController {
	protected $filename = "contract_payment_by_quarter_export.xls";
	
	public function getList() {
		$columns = array(
			'fiscal_year' => array(
				'label' => 'Fiscal Year',
				'width' => 30
			),
			'fiscal_quarter' => array(
				'label' => 'Fiscal Quarter',
				'width' => 20
			),
			'start_date' => array(
				'label' => 'Start Date',
				'width' => 20
			),
			'end_date' => array(
				'label' => 'End Date',
				'width' => 20
			),
			'payments_amount_scheduled' => array(
				'label' => 'Due Amount',
				'width' => 20
			),
			'payments_count_scheduled' => array(
				'label' => 'Due Count',
				'width' => 20
			),
			'payments_amount_paid' => array(
				'label' => 'Paid Amount',
				'width' => 20
			),
			'payments_count_paid' => array(
				'label' => 'Paid Count',
				'width' => 20
			)
		);
		
		$this->setColumns($columns);
		$sm = $this->getServiceLocator();
		
		$calendar_quarters_mdl = CalendarQuarters::factory($sm);
		$contracts_mdl = Contracts::factory($sm);
		
		$filter = $this->getFilter();
		$filter2 = array();
		$sort = $this->getSort();
		
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
		
		return $calendar_quarters_mdl->filterPaymentByQuarter($filter, array(), $sort, $total);
	}
}
?>