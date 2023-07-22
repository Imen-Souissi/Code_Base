<?php
namespace Contract\Controller\Export;

use Els\Mvc\Controller\AbstractExportController;
use Zend\Db\Sql\Expression;

use Contract\Model\Contracts;
use Contract\Model\PaymentRunRates;

class PaymentRunRatesRollupController extends AbstractExportController {
	protected $filename = "contract_run_rates_export.xls";

	public function getList() {
		$columns = array(
			'month_name' => array(
				'label' => 'Month Name',
				'width' => 20
			),
			'month' => array(
				'label' => 'Month',
				'width' => 10
			),
			'year' => array(
				'label' => 'Year',
				'width' => 10
			),
			'amount' => array(
				'label' => 'Amount',
				'width' => 10
			)
		);

		// step 1 get a service locator
		$this->setColumns($columns);
		$sm = $this->getServiceLocator();

		// get the required models == db from the sm
		$contracts_mdl = Contracts::factory($sm);
		$payment_run_rates_mdl = PaymentRunRates::factory($sm);

		// set the filter and the sort methods then initialize another array that results from another filter
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

		return $payment_run_rates_mdl->filterRollup($filter, array(), $sort, $total);
	}
}
?>
