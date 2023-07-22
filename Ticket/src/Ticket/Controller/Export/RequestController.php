<?php
namespace Ticket\Controller\Export;

use Els\Mvc\Controller\AbstractExportController;
use Zend\Db\Sql\Expression;

use Ticket\Model\Contracts;
// here I have to see the other ticket controllers to find out which model they use especially the script
// I found the following models
use Ticket\Model\Systems;
use Ticket\Model\ServiceRequests;
use Ticket\Model\FootprintsStatuses;
use Ticket\Model\SalesforceStatuses;
use Ticket\Model\SalesforceProducts;
use Ticket\Model\SalesforceLabLocations;


class RequestController extends AbstractExportController {
	protected $filename = "ServiceRequests_export.xls";

	public function getList() {
		$columns = array(
			'number' => array(
				'label' => 'Number',
				'width' => 20
			),
			'title' => array(
				'label' => 'Title',
				'width' => 10
			),
			'status' => array(
				'label' => 'Status',
				'width' => 10
			),
			'contact' => array(
				'label' => 'Contact',
				'width' => 10
			),
      'submitted-on' => array(
				'label' => 'Submitted On',
				'width' => 10
			)
		);

		$this->setColumns($columns);
		$sm = $this->getServiceLocator();
		//here starts the logic, start from here 
		$contracts_mdl = Contracts::factory($sm);
		$payment_run_rates_mdl = PaymentRunRates::factory($sm);

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
