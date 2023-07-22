<?php
namespace Contract\Controller\Export;

use Els\Mvc\Controller\AbstractExportController;
use Zend\Db\Sql\Expression;

use Contract\Model\Payments;

class PaymentsController extends AbstractExportController {
	protected $filename = "contract_payments_export.xls";
	
	public function getList() {
		$columns = array(
			'contract_vendor' => array(
				'label' => 'Vendor',
				'width' => 30
			),
			'contract_type' => array(
				'label' => 'Contract Type',
				'width' => 20
			),
			'contract_description' => array(
				'label' => 'Contract Description',
				'width' => 20
			),
			'contract_number' => array(
				'label' => 'Contract Number',
				'width' => 20
			),
			'payment_type' => array(
				'label' => 'Payment Type',
				'width' => 20
			),
			'payment_date' => array(
				'label' => 'Payment Date',
				'width' => 20
			),
			'amount' => array(
				'label' => 'Payment Amount',
				'width' => 20
			)
		);
		
		$this->setColumns($columns);
		
		$payments_mdl = Payments::factory($this->getServiceLocator());
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
			
			$filter['access'] = new Expression("MAX(LVC.labview_id IN ({$lqs}) OR C.created_by_id = ? OR CUL.user_id = ?)", $qvals);
		}
		
		return $payments_mdl->filter($filter, array(), $sort, $total);
	}
}
?>