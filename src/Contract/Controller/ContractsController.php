<?php
namespace Contract\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Log\Logger;
use Zend\Form\Factory;
use Zend\Db\Sql\Expression;
use Els\Db\Sql\Where;


use Contract\Model\CalendarQuarters;
use Contract\Model\Contracts;
use Contract\Model\ContractContactLinks;
use Contract\Model\ContractDevices;
use Contract\Model\ContractDocuments;
use Contract\Model\ContractStatuses;
use Contract\Model\ContractUserLinks;
use Contract\Model\Payments;
use Contract\Model\PaymentRunRates;
use Contract\Model\PaymentSchedules;
use Contract\Model\PaymentTypes;

use Contract\Util\Calendar AS CalendarUtil;

use Gem\Model\AssetDevices;
use Gem\Model\Labviews;
use Gem\Model\LabviewContracts;

use Hr\Model\HrDepts;

use PHPExcel;
use PHPExcel_Cell_DataType;
use PHPExcel_IOFactory;
use PHPExcel_Style_Alignment;
use PHPExcel_Style_NumberFormat;

class ContractsController extends AbstractActionController {
	const STATUS_TEXT_ACTIVE = 'Active';
	const STATUS_TEXT_CLOSED = 'Closed';
	const STATUS_TEXT_DELETED = 'Deleted';

	const PAYMENT_TYPE_TEXT_PAID = 'Paid';
	const PAYMENT_TYPE_TEXT_RENEWAL = 'Renewal';
	const PAYMENT_TYPE_TEXT_SCHEDULED = 'Scheduled';

	protected $max_scheduled_payments = 20;
	protected $renewal_increase = 1.10;
	protected $max_year_month_interval = '2 year';

	public function indexAction() {
		$sm = $this->getServiceLocator();

		// we should set the wizard redirect to be here
		$this->wizard()->setRedirect('contracts', $this->url()->fromRoute('contract/default', array(
			'controller' => 'contracts',
			'action' => 'index',
		)));

		$year_month = date('Ym');
		$max_year_month = date('Ym', strtotime('+'.$this->max_year_month_interval));

		return new ViewModel(array(
			'today' => array(
				'year_month' => $year_month
			),
			'max' => array(
				'year_month' => $max_year_month
			)
		));
	}

	public function byQuarterAction() {
		// we should set the wizard redirect to be here
		$this->wizard()->setRedirect('contracts', $this->url()->fromRoute('contract/default', array(
			'controller' => 'contracts',
			'action' => 'by-quarter',
		)));

		$today = date('Y-m-d');

		return new ViewModel(array(
			'today' => array(
				'date' => $today,
			)
		));
	}

	public function viewAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');

		$id = $this->params()->fromRoute('id');
		$pane = $this->params()->fromQuery('pane');

		$contracts_mdl = Contracts::factory($sm);
		$contract_user_links_mdl = ContractUserLinks::factory($sm);
		$payments_mdl = Payments::factory($sm);

		$contract = $contracts_mdl->get($id);

		if($contract) {
			$type = $contract->type;
			$units = $contract->units;
			$status = $contract->status;
			$vendor = $contract->vendor;
			$vendor_id = $contract->vendor_id;
			$vendor_logo = $contract->vendor_logo;
			$contract_number = $contract->contract_number;
			$account_number = $contract->account_number;
			$pr = $contract->pr;
			$po = $contract->po;
			$ppd_category = $contract->ppd_category;
			$department = $contract->department;
			$requestor_name = $contract->requestor_name;
			$project = $contract->project;
			$description = $contract->description;
			$notes = $contract->notes;
			$payment_schedule = $contract->payment_schedule;
			$notification_schedule = $contract->notification_schedule;
			$start_date = $contract->start_date;
			$end_date = $contract->end_date;
			$previous_payment_date = $contract->previous_payment_date;
			$next_payment_date = $contract->next_payment_date;
			$next_payment_type = $contract->next_payment_type;
			$can_edit = ($status!=self::STATUS_TEXT_CLOSED && $status!=self::STATUS_TEXT_DELETED);
			$can_renew = ($status!=self::STATUS_TEXT_DELETED && (!$next_payment_date || $next_payment_type==self::PAYMENT_TYPE_TEXT_RENEWAL));
			$can_archive = ($status!=self::STATUS_TEXT_CLOSED && $status!=self::STATUS_TEXT_DELETED);
			$can_delete = $status!=self::STATUS_TEXT_DELETED;

			//check if there are payments attached
			$payments = $payments_mdl->filter(array(
				'contract_id' => $id
			), array(), array(), $payments_total);

			$incomplete = ($payments_total==0 && $status!=self::STATUS_TEXT_CLOSED && $status!=self::STATUS_TEXT_DELETED) ? 'This contract appears to be incomplete as there currently are no payments assigned' : '';

			$notification_users = $contract_user_links_mdl->filter(array(
				'contract_id' => $id
			), array(), array(), $total)->toArray();

			$notification_users = implode(', ', array_map(function($user) {
				return $user['name'];
			}, $notification_users));
		}

		// we should set the wizard redirect to be here
		$this->wizard()->setRedirect('contracts', $this->url()->fromRoute('contract/default', array(
			'controller' => 'contracts',
			'action' => 'view',
			'id' => $id
		)));

		return new ViewModel(array(
			'id' => $id,
			'type' => $type,
			'units' => $units,
			'status' => $status,
			'vendor' => $vendor,
			'vendor_id' => $vendor_id,
			'vendor_logo' => $vendor_logo,
			'account_number' => $account_number,
			'contract_number' => $contract_number,
			'pr' => $pr,
			'po' => $po,
			'ppd_category' => $ppd_category,
			'department' => $department,
			'requestor_name' => $requestor_name,
			'project' => $project,
			'description' => $description,
			'notes' => $notes,
			'payment_schedule' => $payment_schedule,
			'notification_schedule' => $notification_schedule,
			'notification_users' => $notification_users,
			'start_date' => $start_date,
			'end_date' => $end_date,
			'previous_payment_date' => $previous_payment_date,
			'next_payment_date' => $next_payment_date,
			'next_payment_type' => $next_payment_type,
			'can_edit' => $can_edit,
			'can_renew' => $can_renew,
			'can_archive' => $can_archive,
			'can_delete' => $can_delete,
			'incomplete' => $incomplete,
			'pane' => $pane
		));
	}

	public function byQuarterViewAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');

		$id = $this->params()->fromRoute('id');

		$calendar_quarters_mdl = CalendarQuarters::factory($sm);
		$contracts_mdl = Contracts::factory($sm);

		$filter = array();
		$filter2 = array();

		//we'll first determine the contracts we have access to and then use them to determine payment run rates
		if(!$this->authorization()->isPermitted('Contract::Contracts', 'access-all')) {
			$user_id = $this->authentication()->getAuthenticatedUserId();
			$labview_ids = $this->authorization()->getLabviewIds();

			if(empty($labview_ids) || count($labview_ids) == 0) {
				$labview_ids = array(0);
			}

			$department_ids = $this->authorization()->getDepartmentIds();

			$lqs = implode(',', array_fill(0, count($labview_ids), '?'));

			$qvals = array_merge($labview_ids, array($user_id, $user_id));

			$table = $contracts_mdl->getTableName();
			$filter2['access'] = new Expression("MAX(LVC.labview_id IN ({$lqs}) OR {$table}.created_by_id = ? OR CUL.user_id = ?)", $qvals);
		}

		$items2 = $contracts_mdl->filterMore($filter2, array(), array(), $total2);
		foreach($items2 AS $item) {
			$filter['contract_id'][] = $item->id;
		}

		$filter['id'] = $id;

		$calendar = $calendar_quarters_mdl->filterPaymentByQuarter($filter, array(), array(), $total);

		if($calendar) {
			$calendar = $calendar->current();

			$fiscal_year = $calendar->fiscal_year;
			$fiscal_quarter = $calendar->fiscal_quarter;
			$start_date = $calendar->start_date;
			$end_date = $calendar->end_date;
			$payments_amount_scheduled = $calendar->payments_amount_scheduled;
			$payments_count_scheduled = $calendar->payments_count_scheduled;
			$payments_amount_paid = $calendar->payments_amount_paid;
			$payments_count_paid = $calendar->payments_count_paid;
		}

		return new ViewModel(array(
			'id' => $id,
			'fiscal_year' => $fiscal_year,
			'fiscal_quarter' => $fiscal_quarter,
			'start_date' => $start_date,
			'end_date' => $end_date,
			'payments_amount_scheduled' => $payments_amount_scheduled,
			'payments_count_scheduled' => $payments_count_scheduled,
			'payments_amount_paid' => $payments_amount_paid,
			'payments_count_paid' => $payments_count_paid
		));
	}

	protected function buildAddForm() {
		$factory = new Factory();
		$trimfilter = new \Zend\Filter\StringTrim();

		$fields = array('vendor_id', 'type_id', 'status_id', 'account_number', 'units',
			'contract_number', 'pr', 'po', 'ppd_category', 'department_id', 'requestor_id',
			'project', 'description', 'notes', 'notification_users', 'notification_schedule_id',
			'payment_schedule_id', 'start_date', 'end_date');
		$elements = array();
		$input_filter = array();

		foreach($fields as $field) {
			$elements[] = array(
				'spec' => array(
					'type' => 'Zend\Form\Element\Text',
					'name' => $field,
				),
			);
			$input_filter[$field] = array(
				// ensure that the callback validator will be trigger even if the fields are empty
				'continue_if_empty' => true,
				'validators' => array(),
				'filters' => array(
					$trimfilter
				),
			);
		}

		$input_filter['vendor_id']['validators'][] = new \Zend\Validator\NotEmpty(array('message' => 'Please select a Vendor'));
		$input_filter['type_id']['validators'][] = new \Zend\Validator\NotEmpty(array('message' => 'Please select a Contract Type'));
		$input_filter['status_id']['validators'][] = new \Zend\Validator\NotEmpty(array('message' => 'Please select a Status'));
		$input_filter['department_id']['validators'][] = new \Zend\Validator\NotEmpty(array('message' => 'Please select a Department'));
		$input_filter['requestor_id']['validators'][] = new \Zend\Validator\NotEmpty(array('message' => 'Please select a Requestor'));
		$input_filter['payment_schedule_id']['validators'][] = new \Zend\Validator\NotEmpty(array('message' => 'Please select a Payment Schedule'));
		$input_filter['notification_schedule_id']['validators'][] = new \Zend\Validator\NotEmpty(array('message' => 'Please select a Notification Schedule'));
		$input_filter['start_date']['validators'][] = new \Zend\Validator\NotEmpty(array('message' => 'Please enter a Start Date'));
		$input_filter['end_date']['validators'][] = new \Zend\Validator\NotEmpty(array('message' => 'Please enter a End Date'));


		return $factory->createForm(array(
			'hydrator' => 'Zend\Stdlib\Hydrator\ArraySerializable',
			'elements' => $elements,
			'input_filter' => $input_filter,
		));
	}

	public function addAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');
		$user_id = $this->authentication()->getAuthenticatedUserId();

		$post = $this->params()->fromPost();
		$form = $this->buildAddForm();

		if($this->getRequest()->isPost()) {

			$form->setData($post);

			if($form->isValid()) {
				$post = $form->getData();

				$vendor_id = $post['vendor_id'];
				$type_id = $post['type_id'];
				$units = $post['units'];
				$status_id = $post['status_id'];
				$account_number = $post['account_number'];
				$contract_number = $post['contract_number'];
				$pr = $post['pr'];
				$po = $post['po'];
				$ppd_category = $post['ppd_category'];
				$department_id = $post['department_id'];
				$requestor_id = $post['requestor_id'];
				$project = $post['project'];
				$description = $post['description'];
				$notes = $post['notes'];
				$notification_users = $post['notification_users'];
				$notification_schedule_id = $post['notification_schedule_id'];
				$payment_schedule_id = $post['payment_schedule_id'];
				$start_date = $post['start_date'];
				$end_date = $post['end_date'];
				$total_amount = 0;

				$start_date = date('Y-m-d', strtotime($start_date));
				$end_date = date('Y-m-d', strtotime($end_date));

				$contracts_mdl = Contracts::factory($sm);
				$labviews_mdl = Labviews::factory($sm);
				$hr_depts_mdl = HrDepts::factory($sm);

				$dept = $hr_depts_mdl->get($department_id);

				$logger->log(Logger::DEBUG, "attempting to add new contract: start_date: {$start_date}, end_date: {$end_date}");

				$contract_id = $contracts_mdl->insert(array(
					'vendor_id' => $vendor_id,
					'type_id' => $type_id,
					'units' => $units,
					'status_id' => $status_id,
					'account_number' => $account_number,
					'contract_number' => $contract_number,
					'pr' => $pr,
					'po' => $po,
					'ppd_category' => $ppd_category,
					'department_id' => $dept->id,
					'department' => $dept->display_name,
					'requestor_id' => $requestor_id,
					'project' => $project,
					'description' => $description,
					'notes' => $notes,
					'notification_schedule_id' => $notification_schedule_id,
					'payment_schedule_id' => $payment_schedule_id,
					'start_date' => $start_date,
					'end_date' => $end_date,
					'created_by_id' => $user_id,
					'total_amount' => $total_amount
				));

				//we'll add the contract to default/auto-gen labview to give cost center level access

				// get auto-gen labview
				$labview = $labviews_mdl->getFull(array(
					'name' => "{$dept->number} *",
					'description' => "Auto generated *",
					'department_id' => $dept->id
				));

				if($labview) {
					$this->assignLabviews($contract_id, array($labview->id));
				}

				if($contract_id) {
					//assign users
					$users = split(',', $notification_users);
					$error = $this->assignUsers($contract_id, $users);

					// redirect to the success page
					$this->redirect()->toRoute('contract/default', array(
						'controller' => 'contracts',
						'action' => 'edit-payments',
						'id' => $contract_id
					), array(
						'query' => array(
							'recalc_payments' => 1,
							'extend_payments' => 0,
							'renew' => 0
						)
					));


				}
				else {
					$error = "Unable to complete adding contract, please try again";
					$logger->log(Logger::DEBUG, "Failed to add a new contract");
				}
			}
		}

		return new ViewModel(array(
			'error' => $error,
			'post' => $post,
			'form' => $form,
			'logged_in_user' => $user_id
		));
	}

	public function editPaymentsAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');

		$id = $this->params()->fromRoute('id');

		$contracts_mdl = Contracts::factory($sm);
		$payments_mdl = Payments::factory($sm);
		$payment_run_rates_mdl = PaymentRunRates::factory($sm);
		$payment_types_mdl = PaymentTypes::factory($sm);

		$calendar_util = new CalendarUtil($sm);

		if($this->getRequest()->isPost()) {
			$payment_type_ids = $this->buildPaymentTypeIdsArray();

			$num_payments = trim($this->params()->fromPost('num_payments'));
			$num_payments_sans_renewal = trim($this->params()->fromPost('num_payments_sans_renewal'));
			$include_renewal = $this->params()->fromPost('include_renewal');
			$include_final = $this->params()->fromPost('include_final');

			$post = $this->params()->fromPost();
			//$logger->log(Logger::INFO, print_r($post, true));
			$next_payment_id = NULL;
			$last_payment_id = NULL;

			$total_amount = 0; //used to calc monthly run rate
			$total_months = 0; //used to calc monthly run rate

			//update payments
			for($i=1, $current_payment_number=1; $i<=$num_payments; $i++) {
				if(isset($post['payment_date_'.$i])
					&& !($i==$num_payments && !$include_renewal)
					&& !($i>1 && $i==$num_payments_sans_renewal && !$include_final)
				) {
					$payment_date = $post['payment_date_'.$i];
					$amount = $post['payment_amount_'.$i];
					$payment_id = $post['payment_id_'.$i];
					$payment_type = $post['payment_type_'.$i];
					$num_months = $post['num_months_'.$i];
					$payment_made = $post['scheduled_payment_made_'.$i];

					//remove any commas & dollar signs
					$amount = preg_replace('/[\$,]/', '', $amount);

					//can't mark renewals as paid
					if($payment_type == self::PAYMENT_TYPE_TEXT_RENEWAL) {
						$payment_type_id = $payment_type_ids[self::PAYMENT_TYPE_TEXT_RENEWAL];
					}
					else {
						$payment_type_id = $payment_made ? $payment_type_ids[self::PAYMENT_TYPE_TEXT_PAID] : $payment_type_ids[self::PAYMENT_TYPE_TEXT_SCHEDULED];
					}

					$payment_date = date('Y-m-d', strtotime($payment_date));

					$payment = $payments_mdl->get(array(
						'contract_id' => $id,
						'payment_number' => $current_payment_number
					));

					//update if contract_id & payment_number already exist
					if($payment) {
						$payment_id = $payment->id;
						$payments_mdl->update($payment_id, array(
							'amount' => $amount,
							'payment_date' => $payment_date,
							'payment_type_id' => $payment_type_id,
							'num_months' => $num_months
						));
					}
					//insert new
					else {
						$payment_id = $payments_mdl->insert($id, $amount, $payment_date, $payment_type_id, $current_payment_number, $num_months);
					}

					//make sure we have quarter added
					$calendar_util->checkQuarter($payment_date);

					//save the latest payment made
					if($payment_type_id == $payment_type_ids[self::PAYMENT_TYPE_TEXT_PAID]) {
						$last_payment_id = $payment_id;
						$next_payment_id = NULL;
					}
					//save the next scheduled payment
					else if(($payment_type_id != $payment_type_ids[self::PAYMENT_TYPE_TEXT_PAID]) && $next_payment_id === NULL) {
						$next_payment_id = $payment_id;
					}

					//we'll exclude renewal payment from monthly run rate
					if($payment_type_id != $payment_type_ids[self::PAYMENT_TYPE_TEXT_RENEWAL]) {
						$total_amount += $amount;
						$total_months += $num_months;
					}

					$current_payment_number++;
				}
			}

			//delete any old left over payments
			$payments = $payments_mdl->filter(array(
				'contract_id' => $id,
				'>payment_number' => ($current_payment_number-1)
			), array(), array(), $payments_total);

			foreach($payments AS $payment) {
				$payments_mdl->delete($payment->id);
			}

			//update previous & next payment dates
			$result = $contracts_mdl->update($id, array(
				'previous_payment_id' => $last_payment_id,
				'next_payment_id' => $next_payment_id
			));

			//save/update payment monthly run rates
			$contract = $contracts_mdl->get($id);

			$monthly_avg_amount = $total_amount/$total_months;
			$date = new \DateTime($contract->start_date);
			$interval = new \DateInterval('P1M');
			$num = 1;
			while($date->format('Y-m-d')<$contract->end_date) {
				$month = $date->format('m');
				$year = $date->format('Y');
				$year_month = $year.$month;
				$payment_run_rate = $payment_run_rates_mdl->get(array(
					'contract_id' => $id,
					'num' => $num
				));

				//update existing
				if($payment_run_rate) {
					$payment_run_rates_mdl->update($payment_run_rate->id, array(
						'amount' => $monthly_avg_amount,
						'month' => $month,
						'year' => $year,
						'year_month' => $year_month
					));
				}
				//insert new
				else {
					$payment_run_rates_mdl->insert(array(
						'contract_id' => $id,
						'month' => $month,
						'year' => $year,
						'year_month' => $year_month,
						'amount' => $monthly_avg_amount,
						'num' => $num
					));
				}

				$date->add($interval);
				$num++;
			}

			//delete any left overs that we don't need anymore
			$payment_run_rates = $payment_run_rates_mdl->filter(array(
				'contract_id' => $id,
				'>num' => ($num-1)
			), array(), array(), $payment_run_rates_total);
			foreach($payment_run_rates AS $payment_run_rate) {
				$payment_run_rates_mdl->delete($payment_run_rate->id);
			}

			$renew = $this->params()->fromQuery('renew');
			if($renew != '' && $renew != '0') {
				// redirect to renew devices page
				$this->redirect()->toRoute('contract/default', array(
					'controller' => 'contracts',
					'action' => 'renew-devices',
					'id' => $id
				), array(
					'query' => array(
						'renew' => $renew
					)
				));
			}
			else {
				// redirect to the success page
				$this->redirect()->toRoute('contract/default', array(
					'controller' => 'contracts',
					'action' => 'edit-success',
				));
			}
		}
		else {
			$recalc_payments = $this->params()->fromQuery('recalc_payments');
			$extend_payments = $this->params()->fromQuery('extend_payments');
			$renew = $this->params()->fromQuery('renew');

			$contract = $contracts_mdl->get($id);
			$start_date = $contract->start_date;
			$end_date = $contract->end_date;
			$next_payment_months = $contract->next_payment_months;
			$interval = new \DateInterval('P'.$next_payment_months.'M');
			$renewal_found = 0;

			$payments = $payments_mdl->filter(array(
				'contract_id' => $id
			), array(), array(
				'payment_number ASC'
			), $payments_total);

			//payment schedule has been changed, need to recalc new scheduled payments
			//OR
			//directly accessing edit-payments on contract w/o any payments
			if($recalc_payments || ($payments_total==0)) {
				$previous_payment_date = $contract->previous_payment_date;

				//we want to preserve payments already marked as paid
				$payments = $payments_mdl->filter(array(
					'contract_id' => $id,
					'payment_type' => self::PAYMENT_TYPE_TEXT_PAID
				), array(), array(
					'payment_number ASC'
				), $payments_total);

				$payments = $payments->toArray();

				//if there's a previous payment, next payment will be previous + scheduled interval
				if($previous_payment_date) {
					$payment_date = new \DateTime($previous_payment_date);
					$payment_date->add($interval);
				}
				//if no payments, we'll start at the start date
				else {
					$payment_date = new \DateTime($start_date);
				}

				$renewal_amount = ($renew) ? $this->determineRenewalAmount($renew) : 0.00;

				//payment schedule of 1+ months
				if($next_payment_months>0) {
					$i = 0;
					//we'll set a max number of scheduled payments
					while(($payment_date->format('Y-m-d')<$end_date) && ($i<$this->max_scheduled_payments)) {
						$payments_total++;
						$payments[] = array(
							'id' => '',
							'payment_type' => self::PAYMENT_TYPE_TEXT_SCHEDULED,
							'payment_number' => $payments_total,
							'payment_date' => $payment_date->format('Y-m-d'),
							'amount' => $renewal_amount,
							'num_months' => $next_payment_months
						);
						$payment_date->add($interval);
						$i++;
					}
				}
				//one time payment
				else {
					//if no payments, we'll add one for the start date
					if($payments_total == 0) {
						$payment_date = new \DateTime($start_date);
						$end_date_dt = new \DateTime($end_date);
						//calculate custom num_months for monthly run rates
						$next_payment_months = $payment_date->diff($end_date_dt)->m  + ($payment_date->diff($end_date_dt)->y*12);
						//round partial months
						if($payment_date->diff($end_date_dt)->d > 15) {
							$next_payment_months++;
						}

						$payments_total++;
						$payments[] = array(
							'id' => '',
							'payment_type' => self::PAYMENT_TYPE_TEXT_SCHEDULED,
							'payment_number' => $payments_total,
							'payment_date' => $payment_date->format('Y-m-d'),
							'amount' => $renewal_amount,
							'num_months' => $next_payment_months
						);
					}
				}

				//add the renewal
				//TODO: only add renewal if we didn't cutoff the scheduled payments due to exceeding max
				$renewal_found = 1;
				$renewal_date = new \DateTime($end_date);
				$renewal_date->add(new \DateInterval('P1D'));
				$payments_total++;
				$payments[] = array(
					'id' => '',
					'payment_type' => self::PAYMENT_TYPE_TEXT_RENEWAL,
					'payment_number' => $payments_total,
					'payment_date' => $renewal_date->format('Y-m-d'),
					'amount' => ($renewal_amount*$this->renewal_increase),
					'num_months' => $next_payment_months
				);
			}
			//use current payment info
			else {
				$payments = $payments->toArray();

				//if end date moved up, we'll need to remove any scheduled payments > end date
				//if end date moved back, we'll need to see if any more scheduled payments fit in between
				// the last scheduled payment & end date
				//lastly, we'll need to update the renewal date
				if($extend_payments) {
					$payments_extended = array();
					$payments_total = 0;
					$last_date = '';
					foreach($payments AS $payment) {
						if($payment['payment_type']==self::PAYMENT_TYPE_TEXT_PAID) {
							$payments_extended[] = $payment;
							$last_date = $payment['payment_date'];
							$payments_total++;
						}
						else if($payment['payment_type']==self::PAYMENT_TYPE_TEXT_SCHEDULED && $payment['payment_date']<$end_date) {
							$payments_extended[] = $payment;
							$last_date = $payment['payment_date'];
							$payments_total++;
						}
					}

					if($last_date < $end_date) {
						$payment_date = new \DateTime($last_date);
						$payment_date->add($interval);

						$i = 0;
						//we'll set a max number of scheduled payments
						while(($payment_date->format('Y-m-d')<$end_date) && ($i<$this->max_scheduled_payments)) {
							$payments_total++;
							$payments_extended[] = array(
								'id' => '',
								'payment_type' => self::PAYMENT_TYPE_TEXT_SCHEDULED,
								'payment_number' => $payments_total,
								'payment_date' => $payment_date->format('Y-m-d'),
								'num_months' => $next_payment_months
							);
							$payment_date->add($interval);
							$i++;
						}
					}

					//add the renewal
					//TODO: only add renewal if we didn't cutoff the scheduled payments due to exceeding max
					$renewal_found = 1;
					$renewal_date = new \DateTime($end_date);
					$renewal_date->add(new \DateInterval('P1D'));
					$payments_total++;
					$payments_extended[] = array(
						'id' => '',
						'payment_type' => self::PAYMENT_TYPE_TEXT_RENEWAL,
						'payment_number' => $payments_total,
						'payment_date' => $renewal_date->format('Y-m-d'),
						'num_months' => $next_payment_months
					);

					$payments = $payments_extended;
				}
				else {
					//check if any payments are renewals
					foreach($payments AS $payment) {
						if($payment['payment_type']==self::PAYMENT_TYPE_TEXT_RENEWAL) {
							$renewal_found = 1;
						}
					}
				}
			}

			$payments_sans_renewal = ($renewal_found) ? ($payments_total-1) : $payments_total;

			return new ViewModel(array(
				'id' => $id,
				'num_payments' => $payments_total,
				'payments' => $payments,
				'payments_sans_renewal' => $payments_sans_renewal,
				'recalc_payments' => $recalc_payments,
				'extend_payments' => $extend_payments,
				'renew' => $renew
			));
		}
	}

	protected function buildEditForm() {
		$factory = new Factory();
		$trimfilter = new \Zend\Filter\StringTrim();

		$fields = array('vendor_id', 'type_id', 'status_id', 'account_number', 'units',
			'contract_number', 'pr', 'po', 'ppd_category', 'department_id', 'requestor_id',
			'project', 'description', 'notes', 'notification_users', 'notification_schedule_id',
			'payment_schedule_id', 'start_date', 'end_date');
		$elements = array();
		$input_filter = array();

		foreach($fields as $field) {
			$elements[] = array(
				'spec' => array(
					'type' => 'Zend\Form\Element\Text',
					'name' => $field,
				),
			);
			$input_filter[$field] = array(
				// ensure that the callback validator will be trigger even if the fields are empty
				'continue_if_empty' => true,
				'validators' => array(),
				'filters' => array(
					$trimfilter
				),
			);
		}

		$input_filter['vendor_id']['validators'][] = new \Zend\Validator\NotEmpty(array('message' => 'Please select a Vendor'));
		$input_filter['type_id']['validators'][] = new \Zend\Validator\NotEmpty(array('message' => 'Please select a Contract Type'));
		$input_filter['status_id']['validators'][] = new \Zend\Validator\NotEmpty(array('message' => 'Please select a Status'));
		$input_filter['department_id']['validators'][] = new \Zend\Validator\NotEmpty(array('message' => 'Please select a Department'));
		$input_filter['requestor_id']['validators'][] = new \Zend\Validator\NotEmpty(array('message' => 'Please select a Requestor'));
		$input_filter['payment_schedule_id']['validators'][] = new \Zend\Validator\NotEmpty(array('message' => 'Please select a Payment Schedule'));
		$input_filter['notification_schedule_id']['validators'][] = new \Zend\Validator\NotEmpty(array('message' => 'Please select a Notification Schedule'));
		$input_filter['start_date']['validators'][] = new \Zend\Validator\NotEmpty(array('message' => 'Please enter a Start Date'));
		$input_filter['end_date']['validators'][] = new \Zend\Validator\NotEmpty(array('message' => 'Please enter a End Date'));


		return $factory->createForm(array(
			'hydrator' => 'Zend\Stdlib\Hydrator\ArraySerializable',
			'elements' => $elements,
			'input_filter' => $input_filter,
		));
	}

	public function editAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');

		$id = $this->params()->fromRoute('id');

		$contracts_mdl = Contracts::factory($sm);
		$hr_depts_mdl = HrDepts::factory($sm);

		$post = $this->params()->fromPost();
		$form = $this->buildEditForm();

		if($this->getRequest()->isPost()) {
			$form->setData($post);

			if($form->isValid()) {
				$vendor_id = $post['vendor_id'];
				$type_id = $post['type_id'];
				$units = $post['units'];
				$status_id = $post['status_id'];
				$account_number = $post['account_number'];
				$contract_number = $post['contract_number'];
				$pr = $post['pr'];
				$po = $post['po'];
				$ppd_category = $post['ppd_category'];
				$department_id = $post['department_id'];
				$requestor_id = $post['requestor_id'];
				$project = $post['project'];
				$description = $post['description'];
				$notes = $post['notes'];
				$notification_users = $post['notification_users'];
				$notification_schedule_id = $post['notification_schedule_id'];
				$payment_schedule_id = $post['payment_schedule_id'];
				$start_date = $post['start_date'];
				$end_date = $post['end_date'];

				$start_date = date('Y-m-d', strtotime($start_date));
				$end_date = date('Y-m-d', strtotime($end_date));

				$dept = $hr_depts_mdl->get($department_id);

				//get current info to see if the payment_schedule is being changed
				$contract = $contracts_mdl->get($id);
				$old_payment_schedule_id = $contract->payment_schedule_id;
				$old_start_date = $contract->start_date;
				$old_end_date = $contract->end_date;

				$logger->log(Logger::DEBUG, "edit contract ID {$id} (BEFORE) payment_schedule_id: {$payment_schedule_id}");
				$logger->log(Logger::DEBUG, "edit contract ID {$id} (AFTER) type_id: {$type_id}, vendor_id: {$vendor_id}, status_id: {$status_id}, contract_number: {$contract_number}, description: {$description}, payment_schedule_id: {$payment_schedule_id}, start_date: {$start_date}, end_date: {$end_date}");

				$result = $contracts_mdl->update($id, array(
					'type_id' => $type_id,
					'units' => $units,
					'status_id' => $status_id,
					'vendor_id' => $vendor_id,
					'account_number' => $account_number,
					'contract_number' => $contract_number,
					'pr' => $pr,
					'po' => $po,
					'ppd_category' => $ppd_category,
					'department_id' => $dept->id,
					'department' => $dept->display_name,
					'requestor_id' => $requestor_id,
					'project' => $project,
					'description' => $description,
					'notes' => $notes,
					'notification_schedule_id' => $notification_schedule_id,
					'payment_schedule_id' => $payment_schedule_id,
					'start_date' => $start_date,
					'end_date' => $end_date
				));

				//update notification users
				$users = split(',', $notification_users);
				$error = $this->assignUsers($id, $users);
				$this->cleanupUsers($id, $users);

				$post['notification_users'] = $users;

				//check/save any values being passed back & forth
				$recalc_payments_post = $this->params()->fromPost('recalc_payments');
				$extend_payments_post = $this->params()->fromPost('extend_payments');
				$renew_post = $this->params()->fromPost('renew');

				//calculate values on the fly
				$recalc_payments_calc = ($old_payment_schedule_id!=$payment_schedule_id || $old_start_date!=$start_date) ? 1 : 0;
				$extend_payments_calc = (!$recalc_payments && ($old_end_date!=$end_date)) ? 1 : 0;
				$renew_calc = 0;

				//set if either method is set above
				$recalc_payments = ($recalc_payments_post || $recalc_payments_calc);
				$extend_payments = ($extend_payments_post || $extend_payments_calc);
				$renew = ($renew_post || $renew_calc);

				// redirect to the success page
				$this->redirect()->toRoute('contract/default', array(
					'controller' => 'contracts',
					'action' => 'edit-payments',
					'id' => $id
				), array(
					'query' => array(
						'recalc_payments' => $recalc_payments,
						'extend_payments' => $extend_payments,
						'renew' => $renew
					)
				));
			} else {
				$error = "Unable to complete editing contract, please try again";
				$logger->log(Logger::DEBUG, "Failed to edit a new contract");
			}
		}
		else {
			$recalc_payments = $this->params()->fromQuery('recalc_payments');
			$extend_payments = $this->params()->fromQuery('extend_payments');
			$renew = $this->params()->fromQuery('renew');

			$post = $contracts_mdl->get($id);
			if($post) {
				if($post['start_date'] =='0000-00-00') {
					$post['start_date'] = '';
				}
				if($post['end_date']=='0000-00-00') {
					$post['end_date'] = '';
				}

				$notification_users = $this->getNotificationUsers($id);
				$notification_users = implode(",",$notification_users);
				$post['notification_users'] = $notification_users;
			}
		}

		return new ViewModel(array(
			'id' => $id,
			'post' => $post,
			'error' => $error,
			'form' => $form,
			'recalc_payments' => $recalc_payments,
			'extend_payments' => $extend_payments,
			'renew' => $renew
		));
	}

	public function renewAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');

		$id = $this->params()->fromRoute('id');

		$contracts_mdl = Contracts::factory($sm);
		$contract_statuses_mdl = ContractStatuses::factory($sm);
		$labviews_mdl = Labviews::factory($sm);
		$hr_depts_mdl = HrDepts::factory($sm);

		if($this->getRequest()->isPost()) {
			$vendor_id = trim($this->params()->fromPost('vendor_id'));
			$type_id = trim($this->params()->fromPost('type_id'));
			$units = trim($this->params()->fromPost('units'));
			$status_id = trim($this->params()->fromPost('status_id'));
			$account_number = trim($this->params()->fromPost('account_number'));
			$contract_number = trim($this->params()->fromPost('contract_number'));
			$pr = trim($this->params()->fromPost('pr'));
			$po = trim($this->params()->fromPost('po'));
			$ppd_category = trim($this->params()->fromPost('ppd_category'));
			$department_id = trim($this->params()->fromPost('department_id'));
			$requestor_id = trim($this->params()->fromPost('requestor_id'));
			$project = trim($this->params()->fromPost('project'));
			$description = trim($this->params()->fromPost('description'));
			$notes = trim($this->params()->fromPost('notes'));
			$notification_users = trim($this->params()->fromPost('notification_users'));
			$notification_schedule_id = trim($this->params()->fromPost('notification_schedule_id'));
			$payment_schedule_id = trim($this->params()->fromPost('payment_schedule_id'));
			$start_date = trim($this->params()->fromPost('start_date'));
			$end_date = trim($this->params()->fromPost('end_date'));
			$user_id = $this->authentication()->getAuthenticatedUserId();
			$total_amount = 0;

			$start_date = date('Y-m-d', strtotime($start_date));
			$end_date = date('Y-m-d', strtotime($end_date));

			$dept = $hr_depts_mdl->get($department_id);

			//get status_id for closed
			$status = $contract_statuses_mdl->get(array(
				'name' => self::STATUS_TEXT_CLOSED
			));

			//close out original
			$result = $contracts_mdl->update($id,array(
				'status_id' => $status->id
			));

			//create a new record for the renewal
			$contract_id = $contracts_mdl->insert(array(
				'vendor_id' => $vendor_id,
				'type_id' => $type_id,
				'units' => $units,
				'status_id' => $status_id,
				'account_number' => $account_number,
				'contract_number' => $contract_number,
				'pr' => $pr,
				'po' => $po,
				'ppd_category' => $ppd_category,
				'department_id' => $dept->id,
				'department' => $dept->display_name,
				'requestor_id' => $requestor_id,
				'project' => $project,
				'description' => $description,
				'notes' => $notes,
				'notification_schedule_id' => $notification_schedule_id,
				'payment_schedule_id' => $payment_schedule_id,
				'start_date' => $start_date,
				'end_date' => $end_date,
				'created_by_id' => $user_id,
				'total_amount' => $total_amount
			));

			//we'll add the contract to default/auto-gen labview to give cost center level access

			// get auto-gen labview
			$labview = $labviews_mdl->getFull(array(
				'name' => "{$dept->number} *",
				'description' => "Auto generated *",
				'department_id' => $dept->id
			));

			if($labview) {
				$this->assignLabviews($contract_id, array($labview->id));
			}

			if($contract_id) {
				//assign users
				$users = split(',', $notification_users);
				$error = $this->assignUsers($contract_id, $users);

				// redirect to the edit-payments page
				$this->redirect()->toRoute('contract/default', array(
					'controller' => 'contracts',
					'action' => 'edit-payments',
					'id' => $contract_id
				), array(
					'query' => array(
						'recalc_payments' => 1,
						'extend_payments' => 0,
						'renew' => $id
					)
				));
			}
			else {
				$error = "Unable to complete adding contract, please try again";
			}
		}
		else {
			$contract = $contracts_mdl->get($id);
			if($contract) {
				$vendor_id = $contract->vendor_id;
				$vendor = $contract->vendor;
				$type_id = $contract->type_id;
				$status_id = $contract->status_id;
				$account_number = $contract->account_number;
				$contract_number = $contract->contract_number;
				$ppd_category = $contract->ppd_category;
				$department_id = $contract->department_id;
				$requestor_id = $contract->requestor_id;
				$project = $contract->project;
				$description = $contract->description;
				$notes = $contract->notes;
				$notification_schedule_id = ($contract->notification_schedule_id) ? $contract->notification_schedule_id : '';
				$payment_schedule_id = $contract->payment_schedule_id;
				$start_date = ($contract->end_date>'1970-01-01') ? date('Y-m-d', strtotime("+1 day", strtotime($contract->end_date))) : date('Y-m-d');
				$end_date = '';

				$notification_users = $this->getNotificationUsers($id);
				$notification_users = implode(",",$notification_users);
			}

			return new ViewModel(array(
				'id' => $id,
				'vendor_id' => $vendor_id,
				'vendor' => $vendor,
				'type_id' => $type_id,
				'status_id' => $status_id,
				'account_number' => $account_number,
				'contract_number' => $contract_number,
				'ppd_category' => $ppd_category,
				'department_id' => $department_id,
				'requestor_id' => $requestor_id,
				'project' => $project,
				'description' => $description,
				'notes' => $notes,
				'notification_users' => $notification_users,
				'notification_schedule_id' => $notification_schedule_id,
				'payment_schedule_id' => $payment_schedule_id,
				'start_date' => $start_date,
				'end_date' => $end_date
			));
		}
	}

	public function deleteAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');

		$id = trim($this->params()->fromPost('id'));

		$logger->log(Logger::DEBUG, "attempting to delete contract ID {$id}");

		$contracts_mdl = Contracts::factory($sm);
		$contract_statuses_mdl = ContractStatuses::factory($sm);

		//verify contract ID
		$contract = $contracts_mdl->get(array(
				'id' => $id
		));

		$status = 0;
		if(!$contract) {
			$error = "Invalid Contract ID";
		}
		else {
			//get status_id for deleted
			$status = $contract_statuses_mdl->get(array(
					'name' => self::STATUS_TEXT_DELETED
			));

			if($status) {
				$result = $contracts_mdl->update($id, array(
						'status_id' => $status->id
				));
			}
			if($result) {
				$status = 1;
			}
			else {
				$error = "Unable to complete deleting contract, please try again";
			}
		}

		return new JsonModel(array(
				'status' => $status,
				'error' => $error
		));
	}

	public function archiveAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');

		$id = trim($this->params()->fromPost('id'));

		$logger->log(Logger::DEBUG, "attempting to archive contract ID {$id}");

		$contracts_mdl = Contracts::factory($sm);
		$contract_statuses_mdl = ContractStatuses::factory($sm);

		//verify contract ID
		$contract = $contracts_mdl->get(array(
			'id' => $id
		));

		$status = 0;
		if(!$contract) {
			$error = "Invalid Contract ID";
		}
		else {
			//get status_id for deleted
			$status = $contract_statuses_mdl->get(array(
				'name' => self::STATUS_TEXT_CLOSED
			));

			if($status) {
				$result = $contracts_mdl->update($id, array(
					'status_id' => $status->id
				));
			}
			if($result) {
				$status = 1;
			}
			else {
				$error = "Unable to complete archiving contract, please try again";
			}
		}

		return new JsonModel(array(
			'status' => $status,
			'error' => $error
		));
	}

	public function addSuccessAction() {
		return new ViewModel(array(
		));
	}

	public function editSuccessAction() {
		$id = $this->params()->fromRoute('id');

		return new ViewModel(array(
			'id' => $id,
		));
	}

	public function renewSuccessAction() {
		$id = $this->params()->fromRoute('id');

		return new ViewModel(array(
			'id' => $id,
		));
	}

	public function editDeviceAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');

		$id = trim($this->params()->fromPost('id'));
		$notes = trim($this->params()->fromPost('notes'));

		$logger->log(Logger::DEBUG, "attempting to edit device ID {$id}");

		$contract_devices_mdl = ContractDevices::factory($sm);

		$status = 0;

		// update record
		$result = $contract_devices_mdl->update($id, array(
			'notes' => $notes
		));

		$status = 1;

		return new JsonModel(array(
			'status' => $status,
			'error' => $error
		));
	}

	public function removeDeviceAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');

		$contracts_mdl = Contracts::factory($sm);
		$contract_devices_mdl = ContractDevices::factory($sm);

		$id = trim($this->params()->fromPost('id'));

		//remove when viewing contract
		if($id != '') {
			$device_info = array(
				'id' => $id
			);
		}
		//remove when viewing device
		else {
			$device_info = array(
				'contract_id' => trim($this->params()->fromPost('contract_id')),
				'device_id' => trim($this->params()->fromPost('device_id'))
			);
		}

		$contract_device = $contract_devices_mdl->get($device_info);

		$status = 0;
		if(!$contract_device) {
			$error = "Device is no longer attached to contract";
		}
		else {
			$result = $contract_devices_mdl->delete($contract_device->id);

			$status = 1;
		}

		return new JsonModel(array(
			'status' => $status,
			'error' => $error
		));
	}

	public function editPaymentAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');

		$id = trim($this->params()->fromPost('id'));
		$amount = trim($this->params()->fromPost('amount'));
		$payment_date = trim($this->params()->fromPost('payment_date'));

		//remove any commas & dollar signs
		$amount = preg_replace('/[\$,]/', '', $amount);
		$payment_date = date('Y-m-d', strtotime($payment_date));

		$logger->log(Logger::DEBUG, "attempting to edit payment ID {$id}");

		$payments_mdl = Payments::factory($sm);

		$calendar_util = new CalendarUtil($sm);

		$status = 0;

		$result = $payments_mdl->update($id, array(
			'amount' => $amount,
			'payment_date' => $payment_date
		));

		//make sure we have quarter added
		$calendar_util->checkQuarter($payment_date);

		if($result) {
			$status = 1;
		}
		else {
			$error = "Unable to complete editing payment, please try again";
		}

		return new JsonModel(array(
			'status' => $status,
			'error' => $error
		));
	}

	public function makePaymentAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');

		$id = trim($this->params()->fromPost('id'));
		$amount = trim($this->params()->fromPost('amount'));
		$payment_date = trim($this->params()->fromPost('payment_date'));

		$payment_date = date('Y-m-d', strtotime($payment_date));

		$logger->log(Logger::DEBUG, "attempting to make payment ID {$id}");

		$contracts_mdl = Contracts::factory($sm);
		$payments_mdl = Payments::factory($sm);
		$payment_types_mdl = PaymentTypes::factory($sm);

		$status = 0;


		$payment_type = $payment_types_mdl->get(array('name' => self::PAYMENT_TYPE_TEXT_PAID));
		$payment_type_ids[self::PAYMENT_TYPE_TEXT_PAID] = $payment_type->id;

		$payment = $payments_mdl->get($id);
		$contract_id = $payment->contract_id;
		$next_payment_number = $payment->payment_number + 1;

		$next_payment = $payments_mdl->get(array(
			'contract_id' => $contract_id,
			'payment_number' => $next_payment_number
		));

		if($next_payment) {
			$new_next_payment_id = $next_payment->id;
		}
		else {
			$new_next_payment_id = NULL;
		}

		$result = $payments_mdl->update($id, array(
			'amount' => $amount,
			'payment_date' => $payment_date,
			'payment_type_id' => $payment_type_ids[self::PAYMENT_TYPE_TEXT_PAID]
		));

		if($result) {
			$status = 1;

			$new_previous_payment_id = $id;

			$logger->log(Logger::DEBUG, "attempting to update contract ID {$contract_id}, previous_payment_id: {$new_previous_payment_id}, next_payment_id: {$new_next_payment_id}");
			$result = $contracts_mdl->update($contract_id,array(
				'previous_payment_id' => $new_previous_payment_id,
				'next_payment_id' => $new_next_payment_id
			));

			//check if we need to activate renewal function
			$contract = $contracts_mdl->get($contract_id);
			$can_renew = ($contract->status==self::STATUS_TEXT_ACTIVE && (!$contract->next_payment_date || $contract->next_payment_type==self::PAYMENT_TYPE_TEXT_RENEWAL));
		}
		else {
			$error = "Unable to complete making payment, please try again";
		}

		return new JsonModel(array(
			'status' => $status,
			'error' => $error,
			'can_renew' => $can_renew
		));
	}

	public function addDocumentsAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');
		$config = $sm->get('Config');
		$db = $sm->get('db');

		$id = $this->params()->fromRoute('id');
		$post = $this->params()->fromPost();
		$error = "";
		$success = "";

		$contract_doc_dir = $config['contract']['docdir'];
		if(empty($contract_doc_dir)) {
			throw new \Exception("no valid contract doc directory");
		} else if(!is_dir($contract_doc_dir)) {
			@mkdir($contract_doc_dir, 0777, true);
		}

		$view = new ViewModel(array(
			'id' => $id
		));

		$contracts_mdl = Contracts::factory($sm);
		$contract_documents_mdl = ContractDocuments::factory($sm);

		$contract = $contracts_mdl->get($id);
		if($contract === false) {
			// this contract no longer exists
			$view->setTemplate('contract/contracts/invalid-contract');
		} else {
			if($this->getRequest()->isPost()) {
				$con = $db->getDriver()->getConnection();

				$user_id = $this->authentication()->getAuthenticatedUserId();

				try {
					$con->beginTransaction();

					$ext_doc_paths = array();

					// process all external documents first
					if(!empty($post['ext_docs']) && is_array($post['ext_docs'])) {
						foreach($post['ext_docs'] as $doc_path) {
							if(empty($doc_path)) {
								continue;
							}

							// check if this doc already exists
							$doc = $contract_documents_mdl->get(array(
								'path' => $doc_path,
								'contract_id' => $id,
								'is_external' => 1
							));

							// extract the name for the url
							$doc_name = basename($doc_path);

							if($doc === false) {
								$contract_documents_mdl->insert(
									$id,
									$doc_name,
									ContractDocuments::LINK_DOC,
									// content
									null,
									$doc_path,
									// is_external
									1
								);
							} else {
								$contract_documents_mdl->update($doc->id, array(
									'name' => $doc_name
								));
							}

							$ext_doc_paths[] = $doc_path;
						}
					}

					$where = new Where();
					$where->equalTo('contract_id', $id);
					$where->equalTo('is_external', 1);
					if(count($ext_doc_paths) > 0) {
						$where->notIn('path', $ext_doc_paths);
					}

					// delete all external document that are no longer valid
					$contract_documents_mdl->delete($where);

					// insert the documents
					$files = $this->params()->fromFiles();
					$new_contract_doc_dir = $contract_doc_dir . DIRECTORY_SEPARATOR . $id;
					if(!is_dir($new_contract_doc_dir)) {
						@mkdir($new_contract_doc_dir, 0777, true);
					}

					// TODO: for file Drag/And/Drop, the file upload does not work.  Need to check JavaScript side
					foreach($files as $file) {
						if(empty($file['tmp_name'])) {
							continue;
						}

						$doc_name = $file['name'];
						if(empty($doc_name)) {
							continue;
						}

						// we need to define a doc location
						$doc_id = $contract_documents_mdl->insert(
							$id,
							$doc_name,
							$file['type'],
							file_get_contents($file['tmp_name'])
						);

						if($doc_id) {
							$doc_path = $doc_id . '_' . str_replace(' ', '_', strtolower($doc_name));
							move_uploaded_file($file['tmp_name'], $new_contract_doc_dir . DIRECTORY_SEPARATOR . $doc_path);
							$contract_documents_mdl->update($doc_id, array(
								'path' => $doc_path
							));
						} else {
							throw new \Exception("unable to add doc {$doc_name}");
						}
					}

					// delete the docs selected to be deleted
					$delete_doc = $post['delete_doc'];
					if(!empty($delete_doc)) {
						$delete_doc = (is_array($delete_doc)) ? $delete_doc : array($delete_doc);
						foreach($delete_doc as $doc_id) {
							$doc = $contract_documents_mdl->get($doc_id);
							if($doc && $doc->path) {
								$contract_documents_mdl->delete($doc_id);

								// we need to remove the doc from our storage
								$doc_file = $new_contract_doc_dir . DIRECTORY_SEPARATOR . $doc->path;
								@unlink($doc_file);
							}
						}
					}

					$con->commit();

					// redirect to the edit success page
					$this->redirect()->toRoute('contract/default', array(
						'controller' => 'contracts',
						'action' => 'edit-success',
						'id' => $id
					));
				} catch(\Exception $e) {
					$con->rollback();

					$p = $e->getPrevious();
					if($p) {
						$e = $p;
					}

					$error = "Unable to complete updating contract documents, please try again";
					$logger->log(Logger::ERR, "unable to update contract documents : " . $e->getMessage());
					$logger->log(Logger::ERR, $e->getTraceAsString());
				}
			} else {
				// grab the documents
				$contract_documents = $contract_documents_mdl->filter(array(
					'contract_id' => $id
				), array(), array(), $total);

				$post['docs'] = array();
				$post['ext_docs'] = array();

				foreach($contract_documents as $doc) {
					if($doc->is_external == 1) {
						$post['ext_docs'][] = $doc->path;
					} else {
						$post['docs'][] = array(
							'id' => $doc->id,
							'contract_id' => $doc->contract_id,
							'type' => $doc->type,
							'name' => $doc->name,
							'path' => $doc->path
						);
					}
				}
			}
		}

		$view->setVariables(array(
			'post' => $post,
			'error' => $error,
			'success' => $success,
			'added' => $this->params()->fromQuery('added'),
			'contract' => ($contract !== false) ? $contract->getArrayCopy() : array()
		));

		return $view;
	}

	public function addDevicesAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');

		$contract_id = $this->params()->fromPost('contract_id');
		$device_id = $this->params()->fromPost('device_id');
		$notes = trim($this->params()->fromPost('notes'));

		$device_ids = preg_split("/[,]+/", $device_id);

		foreach($device_ids AS $device_id) {
			$logger->log(Logger::DEBUG, "attempting to add device ID {$device_id} contract ID {$contract_id}");
			$result = $this->addDevice($contract_id, $device_id, $notes);
		}

		return new JsonModel($result);
	}

	public function renewDevicesAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');

		$id = $this->params()->fromRoute('id');
		$renew = $this->params()->fromQuery('renew');

		// we should set the wizard redirect to be here
		$this->wizard()->setRedirect('contracts', $this->url()->fromRoute('contract/default', array(
			'controller' => 'contracts',
			'action' => 'view',
			'id' => $id
		)));

		if($this->getRequest()->isPost()) {
			$devices = $this->params()->fromPost('device_ids');
			$devices = explode(",", $devices);

			foreach($devices AS $device) {
				$logger->log(Logger::DEBUG, "attempting to renew contract device ID {$device} to contract ID {$id}");
				$result = $this->renewDevice($id, $device);
			}

			// redirect to renew documents page
			$this->redirect()->toRoute('contract/default', array(
				'controller' => 'contracts',
				'action' => 'renew-documents',
				'id' => $id
			), array(
				'query' => array(
					'renew' => $renew
				)
			));
		}
		else {
			return new ViewModel(array(
				'id' => $id,
				'renew' => $renew
			));
		}
	}

	public function renewDocumentsAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');

		$id = $this->params()->fromRoute('id');
		$renew = $this->params()->fromQuery('renew');

		// we should set the wizard redirect to be here
		$this->wizard()->setRedirect('contracts', $this->url()->fromRoute('contract/default', array(
			'controller' => 'contracts',
			'action' => 'view',
			'id' => $id
		)));

		if($this->getRequest()->isPost()) {
			$documents = $this->params()->fromPost('document_ids');
			$documents = explode(",", $documents);

			foreach($documents AS $document) {
				$logger->log(Logger::DEBUG, "attempting to renew contract document ID {$document} to contract ID {$id}");
				$result = $this->renewDocument($id, $document);
			}

			// redirect to the renew labviews page
			$this->redirect()->toRoute('contract/default', array(
				'controller' => 'contracts',
				'action' => 'renew-labviews',
				'id' => $id
			), array(
				'query' => array(
					'renew' => $renew
				)
			));
		}
		else {
			return new ViewModel(array(
				'id' => $id,
				'renew' => $renew
			));
		}
	}

	public function renewLabviewsAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');

		$id = $this->params()->fromRoute('id');
		$renew = $this->params()->fromQuery('renew');

		// we should set the wizard redirect to be here
		$this->wizard()->setRedirect('contracts', $this->url()->fromRoute('contract/default', array(
			'controller' => 'contracts',
			'action' => 'view',
			'id' => $id
		)));

		if($this->getRequest()->isPost()) {
			$labview_ids = $this->params()->fromPost('labview_ids');
			$labview_ids = explode(",", $labview_ids);

			$result = $this->assignLabviews($id, $labview_ids);

			// redirect to the success page
			$this->redirect()->toRoute('contract/default', array(
				'controller' => 'contracts',
				'action' => 'add-success',
				'id' => $id
			));
		}
		else {
			return new ViewModel(array(
				'id' => $id,
				'renew' => $renew
			));
		}
	}

	public function assignUsersAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');

		$id = $this->params()->fromRoute('id');
		$users = $this->params()->fromPost('assign_users_users');

		$status = 0;

		$users = split(',', $users);

		$error = $this->assignUsers($id, $users);

		if($error == '') {
			$status = 1;
		}

		return new JsonModel(array(
			'status' => $status,
			'error' => $error
		));
	}

	public function unassignUserAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');

		$id = trim($this->params()->fromPost('id'));

		$logger->log(Logger::DEBUG, "attempting to remove contract user ID {$id}");

		$contract_user_links_mdl = ContractUserLinks::factory($sm);

		$status = 0;
		if(!$contract_user_links_mdl->exists($id)) {
			$error = "User is already unassigned from contract";
		}
		else {
			$result = $contract_user_links_mdl->delete(array(
				'id' => $id
			));
			if($result) {
				$status = 1;
			}
			else {
				$error = "Unable to complete removing contract user, please try again";
			}
		}

		return new JsonModel(array(
			'status' => $status,
			'error' => $error
		));
	}

	public function assignContactsAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');

		$id = $this->params()->fromRoute('id');
		$contacts = $this->params()->fromPost('assign_contacts_contacts');

		$status = 0;

		$contacts = split(',', $contacts);

		$error = $this->assignContacts($id, $contacts);

		if($error == '') {
			$status = 1;
		}

		return new JsonModel(array(
			'status' => $status,
			'error' => $error
		));
	}

	public function unassignContactAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');

		$id = trim($this->params()->fromPost('id'));

		$logger->log(Logger::DEBUG, "attempting to remove contract/contact link {$id}");

		$contract_contact_links_mdl = ContractContactLinks::factory($sm);

		$status = 0;
		if(!$contract_contact_links_mdl->exists($id)) {
			$error = "Support contact has already been unassigned from contract";
		}
		else {
			$result = $contract_contact_links_mdl->delete(array(
				'id' => $id
			));
			if($result) {
				$status = 1;
			}
			else {
				$error = "Unable to complete removing contract/contact link, please try again";
			}
		}

		return new JsonModel(array(
			'status' => $status,
			'error' => $error
		));
	}

	public function assignLabviewsAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');

		$id = $this->params()->fromRoute('id');
		$labviews = $this->params()->fromPost('assign_labviews_labviews');

		$status = 0;

		$labviews = split(',', $labviews);

		$error = $this->assignLabviews($id, $labviews);

		if($error == '') {
			$status = 1;
		}

		return new JsonModel(array(
			'status' => $status,
			'error' => $error
		));
	}

	public function unassignLabviewAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');

		$contract_id = trim($this->params()->fromRoute('id'));
		$labview_id = trim($this->params()->fromPost('labview_id'));

		$logger->log(Logger::DEBUG, "attempting to remove labview {$labview_id} from contract {$contract_id}");

		$labview_contracts_mdl = LabviewContracts::factory($sm);

		$status = 0;
		if(!$labview_contracts_mdl->exists(array(
				'contract_id' => $contract_id,
				'labview_id' => $labview_id
		))) {
			$error = "Labview has already been unassigned from contract";
		}
		else {
			$result = $labview_contracts_mdl->delete(array(
				'contract_id' => $contract_id,
				'labview_id' => $labview_id
			));
			if($result) {
				$status = 1;
			}
			else {
				$error = "Unable to complete removing contract/labview link, please try again";
			}
		}

		return new JsonModel(array(
			'status' => $status,
			'error' => $error
		));
	}

	public function exportAction() {
		// disable time limit on this script
		\set_time_limit(0);

		$filename = 'contract_export.xls';

		$sm = $this->getServiceLocator();

		$id = $this->params()->fromRoute('id');

		// Create new PHPExcel object
		$objPHPExcel = new PHPExcel();

		$sheets = array(
			array(
				'key' => 'details',
				'title' => 'Details',
				'no-headers' => true,
				'columns' => array(
					'A' => array(
						'width' => 40,
						'style' => array(
							'bold' => 1
						)
					),
					'B' => array(
						'width' => 40
					)
				),
				'rows' => array(
					array(
						'key' => 'vendor',
						'name' => 'Vendor'
					), array(
						'key' => 'type',
						'name' => 'Type'
					), array(
						'key' => 'status',
						'name' => 'Status'
					), array(
						'key' => 'account_number',
						'name' => 'Account Number',
						'format' => array('na','text')
					), array(
						'key' => 'contract_number',
						'name' => 'Contract Number',
						'format' => array('na','text')
					), array(
						'key' => 'pr',
						'name' => 'PR',
						'format' => array('na','text')
					), array(
						'key' => 'po',
						'name' => 'PO',
						'format' => array('na','text')
					), array(
						'key' => 'ppd_category',
						'name' => 'PPD Category',
						'format' => array('na','text')
					), array(
						'key' => 'department_full',
						'name' => 'Department',
						'format' => array('na')
					), array(
						'key' => 'requestor_name',
						'name' => 'Requestor',
						'format' => array('na')
					), array(
						'key' => 'project',
						'name' => 'Project',
						'format' => array('na')
					), array(
						'key' => 'description',
						'name' => 'Description',
						'format' => array('na')
					), array(
						'key' => 'notes',
						'name' => 'Notes',
						'format' => array('na')
					), array(
						'key' => 'start_date',
						'name' => 'Start Date',
						'format' => array('date')
					), array(
						'key' => 'end_date',
						'name' => 'End Date',
						'format' => array('date')
					), array(
						'key' => 'next_payment_date',
						'name' => 'Next Payment Date',
						'format' => array('date')
					), array(
						'key' => 'payment_schedule',
						'name' => 'Payment Schedule'
					), array(
						'key' => 'notification_schedule',
						'name' => 'Notification Schedule',
						'format' => array('na')
					)
				)
			),
			array(
				'key' => 'devices',
				'title' => 'Devices',
				'columns' => array(
					'A' => array(
						'name' => 'Infodot',
						'key' => 'infodot',
						'width' => 30,
						'format' => array('na','text')
					),
					'B' => array(
						'name' => 'Serial',
						'key' => 'serial',
						'width' => 30,
						'format' => array('na','text')
					),
					'C' => array(
						'name' => 'Asset Tag',
						'key' => 'asset',
						'width' => 30,
						'format' => array('na','text')
					),
					'D' => array(
						'name' => 'Site',
						'key' => 'site',
						'width' => 30,
						'format' => array('na')
					),
					'E' => array(
						'name' => 'Lab',
						'key' => 'lab',
						'width' => 30,
						'format' => array('na')
					),
					'F' => array(
						'name' => 'Rack',
						'key' => 'rack',
						'width' => 30,
						'format' => array('na','text')
					),
					'G' => array(
						'name' => 'Manufacturer',
						'key' => 'manufacturer',
						'width' => 30,
						'format' => array('na')
					),
					'H' => array(
						'name' => 'Model',
						'key' => 'model',
						'width' => 30,
						'format' => array('na','text')
					),
					'I' => array(
						'name' => 'Notes',
						'key' => 'notes',
						'width' => 30,
						'format' => array('na')
					)
				)
			),
			array(
				'key' => 'payments',
				'title' => 'Payments',
				'columns' => array(
					'A' => array(
						'name' => 'Payment #',
						'key' => 'payment_number',
						'width' => 30
					),
					'B' =>	array(
						'name' => 'Payment Type',
						'key' => 'payment_type',
						'width' => 30
					),
					'C' => array(
						'name' => 'Payment Date',
						'key' => 'payment_date',
						'width' => 30,
						'format' => array('date')
					),
					'D' => array(
						'name' => 'Amount',
						'key' => 'amount',
						'width' => 30,
						'format' => array('currency')
					)
				)
			)
		);

		$contracts_mdl = Contracts::factory($sm);
		$contract_devices_mdl = ContractDevices::factory($sm);
		$payments_mdl = Payments::factory($sm);

		$objPHPExcel->getDefaultStyle()
			->getAlignment()
			->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);

		$sheet_num = 0;
		foreach($sheets AS $sheet) {
			//Excel starts with one sheet already
			if($sheet_num>0) {
				$objPHPExcel->createSheet();
			}
			$objPHPExcel->setActiveSheetIndex($sheet_num);

			// Rename worksheet
			$objPHPExcel->getActiveSheet()->setTitle($sheet['title']);

			$row = 1;
			// Add column headers
			foreach($sheet['columns'] AS $col=>$header) {
				if(isset($header['width'])) {
					$objPHPExcel->getActiveSheet()
						->getColumnDimension($col)
						->setWidth($header['width'])
					;
				}

				if(!$sheet['no-headers']) {
					$objPHPExcel->getActiveSheet()
						->setCellValue($col.$row, $header['name'])
					;

					$objPHPExcel->getActiveSheet()->getStyle($col.$row)->getFont()->setBold(true);
				}
			}

			if(!$sheet['no-headers']) {
				$row++;
			}

			if($sheet['key'] == 'details') {
				$data = $contracts_mdl->get($id);

				foreach($sheet['rows'] AS $sheet_row) {
					$val['B'] = $data[$sheet_row['key']];

					if(in_array('na', $sheet_row['format'])) {
						$val['B'] = $this->formatExportNa($val['B']);
					}
					else if(in_array('date', $sheet_row['format'])) {
						$val['B'] = $this->formatExportDate($val['B']);
					}

					$objPHPExcel->getActiveSheet()
						->setCellValue('A'.$row, $sheet_row['name'])
						->setCellValue('B'.$row, $val['B'])
					;

					if(isset($sheet['columns']['A']['style']['bold'])) {
						$objPHPExcel->getActiveSheet()->getStyle('A'.$row)->getFont()->setBold(true);
					}

					$row++;
				}
			}
			else {
				if($sheet['key'] == 'devices') {
					$data = $contract_devices_mdl->filter(array(
						'contract_id' => $id
					), array(), array(), $data_total);
				}
				else if($sheet['key'] == 'payments') {
					$data = $payments_mdl->filter(array(
						'contract_id' => $id
					), array(), array(), $data_total);
				}
				else {
					continue;
				}

				if($data) {
					$data = $data->toArray();
				}

				foreach($data AS $d) {
					foreach($sheet['columns'] AS $col=>$column) {
						if(isset($column['key'])) {
							$val = $d[$column['key']];
						}

						if(in_array('na', $column['format'])) {
							$val = $this->formatExportNa($val);
						}
						else if(in_array('date', $column['format'])) {
							$val = $this->formatExportDate($val);
						}
						else if(in_array('currency', $column['format'])) {
							$objPHPExcel->getActiveSheet()
								->getStyle($col.$row)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
							;
						}

						if(in_array('text', $column['format'])) {
							$objPHPExcel->getActiveSheet()
								->setCellValueExplicit($col.$row, $val, PHPExcel_Cell_DataType::TYPE_STRING)
							;
						}
						else {
							$objPHPExcel->getActiveSheet()
								->setCellValue($col.$row, $val)
							;
						}
					}

					$row++;
				}
			}

			$sheet_num++;
		}

		// Reset active sheet index to the first sheet
		$objPHPExcel->setActiveSheetIndex(0);


		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');

		ob_start();
		$objWriter->save('php://output');
		$content = ob_get_clean();

		$response = $this->getResponse();
		$response->setContent($content);
		$response->getHeaders()
			->addHeaderLine('Content-Disposition', "attachment; filename={$filename}")
			->addHeaderLine('Content-Transfer-Encoding', 'binary')
			->addHeaderLine('Content-Type', 'application/vnd.ms-excel')
			->addHeaderLine('Content-Length', strlen($content));

		return $response;
	}

	public function userLookupAction() {
		return new ViewModel(array(
			'user_id' => $this->authentication()->getAuthenticatedUserId()
		));
	}

	protected function determineRenewalAmount($id) {
		$sm = $this->getServiceLocator();

		$payments_mdl = Payments::factory($sm);

		$payments = $payments_mdl->filter(array(
			'contract_id' => $id
		), array(), array('payment_number DESC'), $payment_total);

		$renewal_amount = 0.00;

		//we want the last payment
		//if it's a renewal, we'll use the amount
		//if it's marked as paid, we'll increase it by x%
		if($payments) {
			$payments = $payments->toArray();
			$payment_type = $payments[0]['payment_type'];
			$amount = $payments[0]['amount'];

			if($payment_type == self::PAYMENT_TYPE_TEXT_RENEWAL) {
				$renewal_amount = $amount;
			}
			else if($payment_type == self::PAYMENT_TYPE_TEXT_PAID) {
				$renewal_amount = $amount*$this->renewal_increase;
			}
		}

		return $renewal_amount;
	}

	protected function buildPaymentTypeIdsArray() {
		$sm = $this->getServiceLocator();

		$payment_types_mdl = PaymentTypes::factory($sm);

		$payment_type_ids = array();
		$payment_type = $payment_types_mdl->get(array('name' => self::PAYMENT_TYPE_TEXT_PAID));
		$payment_type_ids[self::PAYMENT_TYPE_TEXT_PAID] = $payment_type->id;

		$payment_type = $payment_types_mdl->get(array('name' => self::PAYMENT_TYPE_TEXT_SCHEDULED));
		$payment_type_ids[self::PAYMENT_TYPE_TEXT_SCHEDULED] = $payment_type->id;

		$payment_type = $payment_types_mdl->get(array('name' => self::PAYMENT_TYPE_TEXT_RENEWAL));
		$payment_type_ids[self::PAYMENT_TYPE_TEXT_RENEWAL] = $payment_type->id;

		return $payment_type_ids;
	}

	/**
	 *
	 * @param int $contract_id
	 * @param array $users : array of usernames
	 */
	protected function assignUsers($contract_id, $users) {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');
		$db = $sm->get('db');

		$con = $db->getDriver()->getConnection();

		$contract_user_links_mdl = ContractUserLinks::factory($sm);

		$error = '';
		try {
			$con->beginTransaction();

			if(count($users) > 0) {
				foreach($users as $user_id) {
					if(!$contract_user_links_mdl->exists(array(
						'contract_id' => $contract_id,
						'user_id' => $user_id
					))) {
						$contract_user_links_mdl->insert($contract_id, $user_id);
					}
				}
			}

			$con->commit();
		} catch(\Exception $e) {
			$con->rollback();

			$p = $e->getPrevious();
			if($p) {
				$e = $p;
			}

			if(empty($error)) {
				$error = "Unable to complete assigning users, please try again";
			}
			$logger->log(Logger::ERR, "unable to assign users: " . $e->getMessage());
			$logger->log(Logger::ERR, $e->getTraceAsString());
		}

		return $error;
	}

	/**
	 * Remove all users not included in $users array from contract $contract_id
	 * @param array $users : array of usernames
	 * @param int $contract_id
	 */
	protected function cleanupUsers($contract_id, $users) {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');

		$contract_user_links_mdl = ContractUserLinks::factory($sm);
		$contract_user_links = $contract_user_links_mdl->filter(array(
			'contract_id' => $contract_id,
			'!user_id' => $users
		), array(), array(), $contract_user_links_total);

		foreach($contract_user_links AS $contract_user_link) {
			$contract_user_links_mdl->delete($contract_user_link->id);
		}
	}

	/**
	 *
	 * @param array $contacts : array of contact_ids
	 * @param int $contract_id
	 */
	protected function assignContacts($contract_id, $contacts) {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');
		$db = $sm->get('db');

		$con = $db->getDriver()->getConnection();

		$contract_contact_links_mdl = ContractContactLinks::factory($sm);

		$error = '';
		try {
			$con->beginTransaction();

			if(count($contacts) > 0) {
				foreach($contacts as $contact_id) {
					if(!$contract_contact_links_mdl->exists(array(
						'contract_id' => $contract_id,
						'contact_id' => $contact_id
					))) {
						$contract_contact_links_mdl->insert($contract_id, $contact_id);
					}
				}
			}

			$con->commit();
		} catch(\Exception $e) {
			$con->rollback();

			$p = $e->getPrevious();
			if($p) {
				$e = $p;
			}

			if(empty($error)) {
				$error = "Unable to complete assigning support contacts, please try again";
			}
			$logger->log(Logger::ERR, "unable to assign users: " . $e->getMessage());
			$logger->log(Logger::ERR, $e->getTraceAsString());
		}

		return $error;
	}


	/**
	 *
	 * @param array $labviews : array of labview_ids
	 * @param int $contract_id
	 */
	protected function assignLabviews($contract_id, $labviews) {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');
		$db = $sm->get('db');

		$con = $db->getDriver()->getConnection();

		$labview_contracts_mdl = LabviewContracts::factory($sm);

		$error = '';
		try {
			$con->beginTransaction();

			if(count($labviews) > 0) {
				foreach($labviews as $labview_id) {
					if(!$labview_contracts_mdl->exists(array(
						'contract_id' => $contract_id,
						'labview_id' => $labview_id
					))) {
						$labview_contracts_mdl->insert($labview_id, $contract_id);
					}
				}
			}

			$con->commit();
		} catch(\Exception $e) {
			$con->rollback();

			$p = $e->getPrevious();
			if($p) {
				$e = $p;
			}

			if(empty($error)) {
				$error = "Unable to complete assigning labview, please try again";
			}
			$logger->log(Logger::ERR, "unable to assign labview: " . $e->getMessage());
			$logger->log(Logger::ERR, $e->getTraceAsString());
		}

		return $error;
	}

	protected function addDevice($contract_id, $device_id, $notes) {
		$sm = $this->getServiceLocator();

		$contracts_mdl = Contracts::factory($sm);
		$contract_devices_mdl = ContractDevices::factory($sm);
		$asset_devices_mdl = AssetDevices::factory($sm);

		$status = 0;
		if($contract_id != '' && $device_id != '') {
			if(!$contracts_mdl->exists($contract_id)) {
				$error = "Invalid Contract ID";
			}
			else if(!$asset_devices_mdl->exists($device_id)) {
				$error = "Invalid Device ID";
			}
			else if(!$contract_devices_mdl->exists(array(
				'contract_id' => $contract_id,
				'device_id' => $device_id
			))) {
				$id = $contract_devices_mdl->insert(
					$contract_id,
					$device_id,
					$notes
				);
			}

			$status = 1;
		}

		return array(
			'status' => $status,
			'error' => $error
		);
	}

	protected function renewDevice($contract_id, $contract_device_id) {
		$sm = $this->getServiceLocator();

		$contract_devices_mdl = ContractDevices::factory($sm);

		//get old device info
		$contract_device = $contract_devices_mdl->get($contract_device_id);

		if($contract_device) {
			//insert new device info
			$contract_device_id_new = $contract_devices_mdl->insert(
				$contract_id,
				$contract_device->device_id,
				$contract_device->notes
			);
		}
	}

	protected function renewDocument($contract_id, $document_id) {
		$sm = $this->getServiceLocator();

		$contract_documents_mdl = ContractDocuments::factory($sm);

		//get old document info
		$contract_document = $contract_documents_mdl->get($document_id);

		if($contract_document) {
			//insert new document info
			$contract_document_id_new = $contract_documents_mdl->insert(
				$contract_id,
				$contract_document->name,
				$contract_document->type,
				$contract_document->content,
				$contract_document->path,
				$contract_document->is_external
			);
		}
	}

	protected function getNotificationUsers($contract_id) {
		$sm = $this->getServiceLocator();

		$contract_user_links_mdl = ContractUserLinks::factory($sm);

		$contract_user_links = $contract_user_links_mdl->filter(array(
			'contract_id' => $contract_id
		), array(), array(), $contract_user_links_total);

		$notification_users = array();
		foreach($contract_user_links AS $contract_user_link) {
			$notification_users[] = $contract_user_link->user_id;
		}

		return $notification_users;
	}

	protected function formatExportNa($val) {
		$str = $val;
		if($str=='') {
			$str = 'N/A';
		}

		return $str;
	}

	protected function formatExportDate($val) {
		$str = $val;
		$date = date_create($str);
		$str = date_format($date, 'n/j/Y');

		return $str;
	}

}
?>
