<?php
namespace Ticket\Controller;

use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Log\Logger;
use Zend\Form\Factory;

use Gem\Gearman\Worker\Emailer;
use Els\Gearman\Client as ElsGearmanClient;

use Hr\Model\HrUsers;

use Ticket\Model\Systems;
use Ticket\Model\ServiceRequests;
use Ticket\Model\FootprintsStatuses;
use Ticket\Model\SalesforceStatuses;
use Ticket\Model\SalesforceOsrs;
use Ticket\Model\SalesforceUsers;

use Ticket\Footprints\Cache as FootprintsCache;
use Ticket\Salesforce\Cache as SalesforceCache;

use Application\Model\Tables;
use Application\Model\TableColumns;
use Application\Model\TableUserColumns;

class ServiceRequestsController extends AbstractActionController {
	public function indexAction() {
		$sm = $this->getServiceLocator();

		$systems_mdl = Systems::factory($sm);
		$tables_mdl = Tables::factory($sm);
		$table_columns_mdl = TableColumns::factory($sm);

		$iframe = intval($this->params()->fromQuery('iframe'));
		if($iframe == 1) {
			$this->layout('layout/content-only');
		}

		// we want to change the layout type to 'horizontal'
		$this->layout()->setVariable('layout_type', 'h');
		// we want to allow users to pin the filter on this page
		$this->layout()->setVariable('allow_pin_filter', true);

		$user_id = $this->authentication()->getAuthenticatedUserId();

		// apply the pin filtering
		$this->pinfilter()->applyPinFilter($user_id);

		$query = $this->params()->fromQuery();
		$query['keywords'] = (empty($query['keywords'])) ? '' : $query['keywords'];
		$query['extended'] = (empty($query['extended'])) ? 0 : $query['extended'];

		// we should set the wizard redirect to be here
		$this->wizard()->setRedirect('service-request', $this->url()->fromRoute('ticket/default', array(
			'controller' => 'service-requests',
			'action' => 'index'
		), array(
			'query' => $this->params()->fromQuery()
		)));

		$table = $tables_mdl->get(array(
			'module' => strtolower(array_shift(explode('\\', __NAMESPACE__))),
			'name' => 'service-request'
		));

		if($table !== false) {
			$columns = $table_columns_mdl->filterUser(array(
				'table_id' => $table->id,
				'user_id' => $user_id,
				'active' => 1
			), array(), array(), $total);
		}

		$system_id = $this->params()->fromQuery('system_id');
		if (empty($system_id)) {
			// grab the default system_id
			$system = $systems_mdl->get(array(
				'is_default' => 1
			));

			if (!empty($system)) {
				$system_id = $system->id;
			}
		}

		$statuses = array();
		if (!empty($system_id)) {
			$statuses_mdl = null;

			// grab the statuses for this system
			$system = $systems_mdl->get($system_id);
			if (!empty($system)) {
				if ($system->name == Systems::FOOTPRINTS) {
					$statuses_mdl = FootprintsStatuses::factory($sm);
				} else if ($system->name == Systems::SALESFORCE) {
					$statuses_mdl = SalesforceStatuses::factory($sm);
				}
			}

			if (!empty($statuses_mdl)) {
				$statuses = $statuses_mdl->filter(array(), array(), array(), $total)->toArray();
			}
		}

		return new ViewModel(
			array_merge(
				$query,
				array(
					'table' => ($table) ? $table->getArrayCopy() : array(),
					'table_columns' => ($columns) ? $columns->toArray() : array()
				),
				array(
					'system_id' => $system_id,
					'statuses' => $statuses
				)
			)
		);
    }

	protected function buildRequestForm() {
		$factory = new Factory();
		$trimfilter = new \Zend\Filter\StringTrim();

		$fields = array('products', 'request', 'lab_location', 'osr_type');
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

		$input_filter['request']['validators'][] = new \Zend\Validator\NotEmpty(array('message' => 'Please provide the request details'));

		return $factory->createForm(array(
			'hydrator' => 'Zend\Stdlib\Hydrator\ArraySerializable',
			'elements' => $elements,
			'input_filter' => $input_filter
		));
	}

	public function requestAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');
		$db = $sm->get('db');
		$config = $sm->get('Config');

		$hr_users_mdl = HrUsers::factory($sm);

		$systems_mdl = Systems::factory($sm);
		$service_requests_mdl = ServiceRequests::factory($sm);
		$salesforce_osrs_mdl = SalesforceOsrs::factory($sm);
		$salesforce_users_mdl = SalesforceUsers::factory($sm);

		$post = $this->params()->fromPost();
		$form = $this->buildRequestForm();

		// grab the default system_id
		$system = $systems_mdl->get(array(
			'is_default' => 1
		));

		if($this->getRequest()->isPost()) {
			$form->setData($post);

			if($form->isValid()) {
				$con = $db->getDriver()->getConnection();
				$user_id = $this->authentication()->getAuthenticatedUserId();
				$post = $form->getData();
				$logger->log(Logger::DEBUG, print_r($post, true));

				$user = $hr_users_mdl->get(array('id' => $user_id));
				if (empty($system)) {
					$error = 'No valid service request system';
				} else if (empty($user)) {
					$error = 'No valid user';
				} else {
					try {
						$con->beginTransaction();

						if ($system->name == Systems::FOOTPRINTS) {
							// submit the request to footprints
							// NOTE: not yet implemented since we will not be using Footprints anymore
							throw new \Exception("service request for Footprints is not yet implemented");
						} else if ($system->name == Systems::SALESFORCE) {
							// let's make sure the user linkage in the salesforce system exists
							$sfdc_id = null;
							$sfdc_user = $salesforce_users_mdl->get(array(
								'hr_user_id' => $user_id
							));
							if (empty($sfdc_user)) {
								// we need to create a temporary one
								$sfdc_user_id = $salesforce_users_mdl->insert(
									uniqid('temp-'),
									$user_id
								);
							} else {
								$sfdc_user_id = $sfdc_user->id;
								if (!preg_match("/^temp\-/", $sfdc_user->sfdc_id)) {
									$sfdc_id = $sfdc_user->sfdc_id;
								}
							}

							// XXIONG: hard-coding the region lookup for now
							if ($post['lab_location'] == 'Sunnyvale') {
								$region = 'Americas';
							} else {
								$region = null;
							}

							if ($post['osr_type'] == 'Facilities') {
								$record_type_id = '0122J00000023W6QAI';
							} else {
								$record_type_id = null;
							}

							$api = SalesforceCache::factory($sm);

							$request = <<<EOT
Request By: {$user->display_name}<br />
Email: {$user->email}<br />
Department: {$user->department}<br />
---------------------------------<br />
{$post['request']}
EOT;

							$preparation_start_date = SalesforceCache::normalizeDate(date('Y-m-d H:i:s', strtotime('+2 week')));
							$presentation_start_date = SalesforceCache::normalizeDate(date('Y-m-d H:i:s', strtotime('+3 week')));

							// submit the request to salesforce
							$result = $api->create(
								// request
								$request,
								// products
								implode(';', array_filter(explode(',', $post['products']))),
								// owner_id
								$sfdc_id,
								// lab_location_require
								$post['lab_location'],
								// region
								$region,
								// preparation_start_date
								$preparation_start_date,
								// presentation_start_date
								$presentation_start_date,
								// $record_type_id
								$record_type_id
							);

							//$logger->log(Logger::DEBUG, print_r($result, true));

							if (intval($result['success']) == 1) {
								// the osr case was created
								$sfdc_id = $result['id'];
								// we need to cache the osr
								$where = array(
									"Id = '{$sfdc_id}'"
								);

								//$logger->log(Logger::DEBUG, print_r($where, true));
								$osrs = $api->dump($where, 0, 1);
								foreach ($osrs as $osr) {
									$api->cacheOsr($osr['Name'], $osr);
								}

								// now let's pull the cached osr
								$osr = $salesforce_osrs_mdl->get(array(
									'sfdc_id' => $sfdc_id
								));

								if (!empty($osr)) {
									// now let's link the user to this osr since Salesforce may not be able to
									$salesforce_osrs_mdl->update($osr->id, array(
										'contact_id' => $sfdc_user_id
									));

									// now let's pull the service request generated for this osr
									$service_request = $service_requests_mdl->get(array(
										'system_id' => $system->id,
										'request_id' => $osr->id
									));

									if (!empty($service_request)) {
										$id = $service_request->id;
									} else {
										throw new \Exception("unable to locate created service request, please try again later");
									}
								} else {
									// something went wrong, the osr should have been cached
									throw new \Exception("unable to locate created service request record, please try again later");
								}
							} else {
								$logger->log(Logger::ERR, print_r($result, true));
								// unable to create the osr case
								throw new \Exception("unable to create service request, please try again later");
							}
						} else {
							throw new \Exception("unrecognize service request system");
						}

						$con->commit();

						// trigger the email to the user that the OSR has been created
						$gearman_client = ElsGearmanClient::factory($sm);
						$gearman_client->doBackground(Emailer::NAME, array(
							'from' => $config['system']['email'],
							'to' => $user->email,
							'subject' => 'BRICS Service Request Created',
							'template' => 'ticket/email/service-request-created',
							'params' => array(
								'osr' => ($osr) ? $osr->name : '',
								'user' => $user->display_name
							),
						));

						// redirect to the request success page
						$this->redirect()->toRoute('ticket/default', array(
							'controller' => 'service-requests',
							'action' => 'request-success',
							'id' => $id
						));
					} catch(\Exception $e) {
						$con->rollback();

						$p = $e->getPrevious();
						if($p) {
							$e = $p;
						}

						$error = "Unable to submit service request, please try again";
						$logger->log(Logger::ERR, "unable to submit service request : " . $e->getMessage());
						$logger->log(Logger::ERR, $e->getTraceAsString());
					}
				}
			} else {
				$messages = $form->getMessages();
				if (count($messages) > 0) {
					foreach ($messages as $msg) {
						$error = $msg;
						break;
					}
				} else {
					$error = 'Invalid form input, please retry';
				}
			}
		}

		return new ViewModel(array(
			'form' => $form,
			'post' => $post,
			'error' => $error,
			'default_system_id' => (empty($system)) ? 0 : $system->id
		));
	}

	public function requestSuccessAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');

		$systems_mdl = Systems::factory($sm);
		$service_requests_mdl = ServiceRequests::factory($sm);
		$salesforce_osrs_mdl = SalesforceOsrs::factory($sm);

		$id = $this->params()->fromRoute('id');
		$number = 'N/A';

		$service_request = $service_requests_mdl->get(array(
			'id' => $id
		));

		if (!empty($service_request)) {
			$system = $systems_mdl->get(array(
				'id' => $service_request->system_id
			));

			if (!empty($system)) {
				if ($system->name == Systems::SALESFORCE) {
					$osr = $salesforce_osrs_mdl->get(array(
						'id' => $service_request->request_id
					));

					if ($osr) {
						$number = $osr->name;
					}
				}
			}
		}

		return new ViewModel(array(
			'id' => $id,
			'number' => $number
		));
	}

	public function viewAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');
		$config = $sm->get('Config');
		$db = $sm->get('db');

		$id = $this->params()->fromRoute('id');

		$systems_mdl = Systems::factory($sm);
		$service_requests_mdl = ServiceRequests::factory($sm);
		$salesforce_osrs_mdl = SalesforceOsrs::factory($sm);

		$view = new ViewModel(array(
			'id' => $id
		));

		$service_request = $service_requests_mdl->get(array(
			'id' => $id
		));

		if (!empty($service_request)) {
			$system = $systems_mdl->get(array(
				'id' => $service_request->system_id
			));

			if (!empty($system)) {
				if ($system->name == Systems::SALESFORCE) {
					$osr = $salesforce_osrs_mdl->get(array(
						'id' => $service_request->request_id
					));

					$view->setTemplate('ticket/service-requests/salesforce-view');
					$view->setVariables(array(
						'service_request' => $service_request->getArrayCopy(),
						'osr' => ($osr) ? $osr->getArrayCopy() : array()
					));
				} else {
					$view->setTemplate('ticket/service-requests/invalid-service-request');
				}
			} else {
				$view->setTemplate('ticket/service-requests/invalid-service-request');
			}
		} else {
			$view->setTemplate('ticket/service-requests/invalid-service-request');
		}

		return $view;
	}
}

?>
