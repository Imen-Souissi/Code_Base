<?php
namespace Ticket\Controller\Script;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Log\Logger;
use Zend\View\Model\ConsoleModel;

use Gem\Gearman\Worker\Emailer;
use Els\Gearman\Client as ElsGearmanClient;

use Ticket\Salesforce\Cache;
use Ticket\Model\SalesforceProducts;
use Ticket\Model\SalesforceRegions;
use Ticket\Model\SalesforceServiceTypes;
use Ticket\Model\SalesforceStatuses;
use Ticket\Model\SalesforceLabLocations;

class SalesforceController extends AbstractActionController {
	public function importAction() {
		$sm = $this->getServiceLocator();

		$logger = $sm->get('console_logger');
		$config = $sm->get('Config');
		$db = $sm->get('db');

		$result = new ConsoleModel();

		// parameters
		$start = $this->params()->fromRoute('start');
		$end = $this->params()->fromRoute('end');
		$osr = $this->params()->fromRoute('osr');
		$sfdc_id = $this->params()->fromRoute('sfdc-id');

		$round = intval($this->params()->fromRoute('round'));
		$round = (empty($round)) ? 0 : $round;

		$perround = intval($this->params()->fromRoute('perround'));
		$perround = (empty($perround)) ? 100 : $perround;

		if(!empty($start)) {
			$ts = strtotime($start);
			if($ts !== false) {
				if(preg_match("/^[0-9]{4}\-[0-9]{2}\-[0-9]{2}$/", $start)) {
					$start = date('c', $ts);
				} else {
					$start = date('c', $ts);
				}
			} else {
				throw new \Exception("invalid start given");
			}
		}

		if(!empty($end)) {
			$ts = strtotime($end);
			if($ts !== false) {
				if(preg_match("/^[0-9]{4}\-[0-9]{2}\-[0-9]{2}$/", $end)) {
					$end = date('c', $ts);
				} else {
					$end = date('c', $ts);
				}
			} else {
				throw new \Exception("invalid end given");
			}
		}

		$logger->log(Logger::INFO, "starting salesforce import");
		$cache = Cache::factory($sm);
		$cache->setLogger($logger);

		//$result = $cache->describe('Attachment');
		//$result = $cache->describe('Opportunity_Support_Request__c');
		//$result = $cache->query("SELECT Id, Username, LastName, FirstName, Name, CompanyName, Department, Title, Email, ReceivesInfoEmails, UserType, AD_UserName__c, Alias, TimeZoneSidKey, LocaleSidKey, EmailEncodingKey, LanguageLocaleKey FROM User WHERE Id = '0055000000754LV'");
		// UserType => Guest
		//file_put_contents('/tmp/osr.json', \Zend\Json\Json::prettyPrint(\Zend\Json\Json::encode($result)));
		//var_dump($result);
		//exit;

		try {
			$where = array();

			if(!empty($start)) {
				$where[] = "LastModifiedDate >= {$start}";
			}
			if(!empty($end)) {
				$where[] = "LastModifiedDate <= {$end}";
			}
			if(!empty($osr)) {
				$where[] = "Name = '{$osr}'";
			}
			if (!empty($sfdc_id)) {
				$where[] = "Id = '{$sfdc_id}'";
			}

			$totalrounds = 0;
			$total = 0;

			$total = $cache->total($where);
			$totalrounds = ceil($total / $perround);

			if($totalrounds == 0) {
				$logger->log(Logger::INFO, "unable to determine total rounds to process");
			} else {
				$logger->log(Logger::INFO, "found {$total} osrs to process, dividing into {$totalrounds} rounds");

				for($currentround = $round; $currentround < $totalrounds; $currentround++) {
					$rowstart = $currentround * $perround;
					//$logger->log(Logger::DEBUG, print_r($where, true));
					$cache->cacheOsrs($where, $rowstart, $perround);
				}

				$logger->log(Logger::INFO, "successfully imported salesforce");
			}
		} catch(\Exception $e) {
			$p = $e->getPrevious();
			if($p) {
				$e = $p;
			}

			$result->setResult($e->getMessage() . "\n" . $e->getTraceAsString());
			$result->setErrorLevel(1);
		}

		$logger->log(Logger::INFO, "finished salesforce import");

		return $result;
	}

	public function importDefinitionAction() {
		$sm = $this->getServiceLocator();

		$logger = $sm->get('console_logger');
		$config = $sm->get('Config');
		$db = $sm->get('db');

		$result = new ConsoleModel();

		$salesforce_products_mdl = SalesforceProducts::factory($sm);
		$salesforce_regions_mdl = SalesforceRegions::factory($sm);
		$salesforce_sevice_types_mdl = SalesforceServiceTypes::factory($sm);
		$salesforce_statuses_mdl = SalesforceStatuses::factory($sm);
		$salesforce_lab_locations_mdl = SalesforceLabLocations::factory($sm);

		$salesforce_products_mdl->setAllowFilterExpression(false);
		$salesforce_regions_mdl->setAllowFilterExpression(false);
		$salesforce_sevice_types_mdl->setAllowFilterExpression(false);
		$salesforce_statuses_mdl->setAllowFilterExpression(false);
		$salesforce_lab_locations_mdl->setAllowFilterExpression(false);

		$logger->log(Logger::INFO, "starting salesforce definition import");
		$cache = Cache::factory($sm);
		$cache->setLogger($logger);

		try {
			$output = $cache->describe('Opportunity_Support_Request__c');

			if (empty($output)) {
				$logger->log(Logger::ERR, "unable to retrieve definition");
			} else {
				$logger->log(Logger::INFO, "successfully retrieved definition");

				foreach ($output['fields'] as $field) {
					// process the list of products (Product_Under_Test__c)
					if ($field['name'] == 'Product_Under_Test__c') {
						// we found the field to process
						foreach ($field['picklistValues'] as $product_info) {
							$product_name = $product_info['value'];
							$product = $salesforce_products_mdl->get(array(
								'name' => $product_name
							));

							if (empty($product)) {
								$salesforce_products_mdl->insert(
									$product_name
								);
							}
						}
					}
					// process the list of regions (Region__c)
					else if ($field['name'] == 'Region__c') {
						// we found the field to process
						foreach ($field['picklistValues'] as $region_info) {
							$region_name = $region_info['value'];
							$region = $salesforce_regions_mdl->get(array(
								'name' => $region_name
							));

							if (empty($region)) {
								$salesforce_regions_mdl->insert(
									$region_name
								);
							}
						}
					}
					// process the list of service types (Service_Type__c)
					else if ($field['name'] == 'Service_Type__c') {
						// we found the field to process
						foreach ($field['picklistValues'] as $service_type_info) {
							$service_type_name = $service_type_info['value'];
							$service_type = $salesforce_sevice_types_mdl->get(array(
								'name' => $service_type_name
							));

							if (empty($service_type)) {
								$salesforce_sevice_types_mdl->insert(
									$service_type_name
								);
							}
						}
					}
					// process the list of statuses (Status__c)
					else if ($field['name'] == 'Status__c') {
						// we found the field to process
						foreach ($field['picklistValues'] as $status_info) {
							$status_name = $status_info['value'];
							$status = $salesforce_statuses_mdl->get(array(
								'name' => $status_name
							));

							if (empty($status)) {
								$salesforce_statuses_mdl->insert(
									$status_name
								);
							}
						}
					}
					// process the list of lab locations (Lab_Location_Required__c)
					else if ($field['name'] == 'Lab_Location_Required__c') {
						// we found the field to process
						foreach ($field['picklistValues'] as $location_info) {
							$location_name = $location_info['value'];
							$status = $salesforce_lab_locations_mdl->get(array(
								'name' => $location_name
							));

							if (empty($status)) {
								$salesforce_lab_locations_mdl->insert(
									$location_name
								);
							}
						}
					}
				}
				$logger->log(Logger::INFO, print_r($output, true));
				$logger->log(Logger::INFO, "successfully imported salesforce definition");
			}
		} catch(\Exception $e) {
			$p = $e->getPrevious();
			if($p) {
				$e = $p;
			}

			$result->setResult($e->getMessage() . "\n" . $e->getTraceAsString());
			$result->setErrorLevel(1);
		}

		$logger->log(Logger::INFO, "finished salesforce definition import");

		return $result;
	}
}

?>
