<?php
namespace Power\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Db\Sql\Expression;

use Power\Model\PowerPdus;


class PowerPdusController extends AbstractRestfulController {
	public function getList() {
		$sm = $this->getServiceLocator();

		$powerpdus_md1 = PowerPdus::factory($sm);


		$filter = $this->getFilter();
		$filter2 = array();

		if(isset($filter['!status'])) {
			$filter2['!status'] = $filter['!status'];
			unset($filter['!status']);
		}

		//we'll first determine the contracts we have access to and then use them to determine payment run rates
		if(!$this->authorization()->isPermitted('Power::PowerPdus', 'access-all')) {
			$user_id = $this->authentication()->getAuthenticatedUserId();
			$labview_ids = $this->authorization()->getLabviewIds();

			if(empty($labview_ids) || count($labview_ids) == 0) {
				$labview_ids = array(0);
			}

			// $department_ids = $this->authorization()->getDepartmentIds();

			$lqs = implode(',', array_fill(0, count($labview_ids), '?'));

			$qvals = array_merge($labview_ids, array($user_id, $user_id));

			$table = $powerpdus_md1->getTableName();
			$filter2['access'] = new Expression("MAX(LVC.labview_id IN ({$lqs}) OR {$table}.power_iq_pdu_id = ? OR CUL.user_id = ?)", $qvals);
		}

		$items = $powerpdus_md1->filter($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
}
?>
