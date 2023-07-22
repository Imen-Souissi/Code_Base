<?php
namespace Contract\Controller\Export;

use Els\Mvc\Controller\AbstractExportController;
use Zend\Db\Sql\Expression;

use Contract\Model\Contracts;

class ContractsController extends AbstractExportController {
	protected $filename = "contracts_export.xls";
	
	public function getList() {
		$columns = array(
			'vendor' => array(
				'label' => 'Vendor',
				'width' => 30
			),
			'type' => array(
				'label' => 'Type',
				'width' => 20
			),
			'status' => array(
				'label' => 'Status',
				'width' => 20
			),
			'account_number' => array(
				'label' => 'Account Number',
				'width' => 20,
				'formatter' => $this->formatNa()
			),
			'contract_number' => array(
				'label' => 'Contract Number',
				'width' => 20,
				'formatter' => $this->formatNa()
			),
			'pr' => array(
				'label' => 'PR',
				'width' => 20,
				'formatter' => $this->formatNa()
			),
			'po' => array(
				'label' => 'PO',
				'width' => 20,
				'formatter' => $this->formatNa()
			),
			'ppd_category' => array(
				'label' => 'PPD Category',
				'width' => 20,
				'formatter' => $this->formatNa()
			),
			'department_full' => array(
				'label' => 'Department',
				'width' => 20,
				'formatter' => $this->formatNa()
			),
			'requestor_name' => array(
				'label' => 'Requestor',
				'width' => 20,
				'formatter' => $this->formatNa()
			),
			'project' => array(
				'label' => 'Project',
				'width' => 20,
				'formatter' => $this->formatNa()
			),
			'description' => array(
				'label' => 'Description',
				'width' => 20,
				'formatter' => $this->formatNa()
			),
			'notes' => array(
				'label' => 'Notes',
				'width' => 20,
				'formatter' => $this->formatNa()
			),
			'start_date' => array(
				'label' => 'Start Date',
				'width' => 20,
				'formatter' => $this->formatNa()
			),
			'end_date' => array(
				'label' => 'End Date',
				'width' => 20,
				'formatter' => $this->formatNa()
			),
			'next_payment_amount' => array(
				'label' => 'Next Payment Amount',
				'width' => 20,
				'formatter' => $this->formatNa()
			),
			'next_payment_date' => array(
				'label' => 'Next Payment Date',
				'width' => 20,
				'formatter' => $this->formatNa()
			),
			'next_payment_fiscal_year' => array(
				'label' => 'Next Payment Fiscal Year',
				'width' => 20,
				'formatter' => $this->formatNa()
			),
			'next_payment_fiscal_quarter' => array(
				'label' => 'Next Payment Fiscal Quarter',
				'width' => 20,
				'formatter' => $this->formatNa()
			)	
		);
		
		$this->setColumns($columns);
		
		$contracts_mdl = Contracts::factory($this->getServiceLocator());
		$filter = $this->getFilter();
		$sort = $this->getSort();
		
		if(!$this->authorization()->isPermitted('Contract::Contracts', 'access-all')) {
			$user_id = $this->authentication()->getAuthenticatedUserId();
			$labview_ids = $this->authorization()->getLabviewIds();
			
			if(empty($labview_ids) || count($labview_ids) == 0) {
				$labview_ids = array(0);
			}
			
			$lqs = implode(',', array_fill(0, count($labview_ids), '?'));
			
			$qvals = array_merge($labview_ids, array($user_id, $user_id));
			
			$table = $contracts_mdl->getTableName();
			$filter['access'] = new Expression("MAX(LVC.labview_id IN ({$lqs}) OR {$table}.created_by_id = ? OR CUL.user_id = ?)", $qvals);
		}
		
		return $contracts_mdl->filterMore($filter, array(), $sort, $total);
	}
}
?>