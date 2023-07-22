<?php
namespace Ticket\Salesforce;

use Els\Db\Sql\Where;
use Zend\Db\Sql\Expression;
use Zend\Log\Logger;

use Ticket\Salesforce\Api;

use Ticket\Model\SalesforceAccounts;
use Ticket\Model\SalesforceOsrs;
use Ticket\Model\SalesforceOsrProducts;
use Ticket\Model\SalesforceProducts;
use Ticket\Model\SalesforceRecordTypes;
use Ticket\Model\SalesforceRegions;
use Ticket\Model\SalesforceServiceTypes;
use Ticket\Model\SalesforceStatuses;
use Ticket\Model\SalesforceUsers;
use Ticket\Model\SalesforceLabLocations;
use Ticket\Model\ServiceRequests;
use Ticket\Model\Systems;

use Hr\Model\HrUsers;
use Hr\Model\HrDomains;

class Cache extends Api {
	protected $sm;
	protected $logger;

	public function __construct($host, $username, $password, $security_token, $sm) {
		$this->sm = $sm;
		parent::__construct($host, $username, $password, $security_token);
	}

	public static function factory($sm) {
		$config = $sm->get('Config');
		return new self(
			$config['salesforce']['host'],
			$config['salesforce']['username'],
			$config['salesforce']['password'],
            $config['salesforce']['security-token'],
			$sm
		);
	}

	public function setLogger($logger) {
		$this->logger = $logger;
	}

	public function hasLogger() {
		return !empty($this->logger);
	}

    public function cacheRecordType($id = null, $name = null) {
        $salesforce_record_types_mdl = SalesforceRecordTypes::factory($this->sm);

        $where = $this->where(array(
            (!empty($id)) ? "Id = '{$id}'" : null,
            (!empty($name)) ? "Name = '{$name}'" : null,
        ));

        $query = "
            SELECT
                Id,
                Name
            FROM RecordType
            {$where}
        ";

        $result = $this->query($query);

        if ($result['done']) {
            foreach ($result['records'] as $record) {
                $record_type = $salesforce_record_types_mdl->get(array(
                    'sfdc_id' => $record['Id']
                ));

                if (empty($record_type)) {
                    $salesforce_record_types_mdl->insert(
                        // sfdc_id
                        $record['Id'],
                        // name
                        $record['Name']
                    );
                }
            }
        }
    }

    public function cacheUser($id = null, $name = null, $email = null) {
        $salesforce_users_mdl = SalesforceUsers::factory($this->sm);
        $hr_users_mdl = HrUsers::factory($this->sm);
        $hr_domains_mdl = HrDomains::factory($this->sm);

        $where = $this->where(array(
            (!empty($id)) ? "Id = '{$id}'" : null,
            (!empty($name)) ? "Name = '{$name}'" : null,
			(!empty($email)) ? "Email = '{$email}'" : null,
        ));

        $query = "
            SELECT
                Id,
                Username,
                Name,
                FirstName,
                LastName,
                EmployeeNumber,
                AD_UserName__c,
                Email
            FROM User
            {$where}
        ";

        $result = $this->query($query);

        if ($result['done']) {
            foreach ($result['records'] as $record) {
                $email = $record['Email'];
                $username = $record['Username'];
                if (strpos($username, '@') !== false) {
                    list($username, $extras) = explode('@', $username, 2);
                }

                list($extras, $domain_name) = explode('@', $email, 2);
                if (empty($domain_name)) {
                    $domain_name = 'unknown';
                }

                $domain = $hr_domains_mdl->get(array(
                    'name' => $domain_name
                ));

                if (empty($domain)) {
                    $domain_id = $hr_domains_mdl->insert(
                        $domain_name
                    );
                } else {
                    $domain_id = $domain->id;
                }

                // check if this user exists
                $hr_user = $hr_users_mdl->get(array(
                    'email' => $email,
                    'domain_id' => $domain_id
                ));

                if (empty($hr_user)) {
                    // we need to insert this user
                    $hr_user_id = $hr_users_mdl->insert(
                        // number
                        $record['EmployeeNumber'],
                        // username
                        $username,
                        // email
                        $email,
                        // phone
                        null,
                        // display_name
                        "{$record['LastName']}, {$record['FirstName']}",
                        // full_name
                        "{$record['LastName']}, {$record['FirstName']}",
                        // imdb_name
                        "{$record['LastName']}, {$record['FirstName']}",
                        // first_name
                        $record['FirstName'],
                        // last_name
                        $record['LastName'],
                        // department_id
                        null,
                        // department
                        null,
                        // supervisor_id
                        null,
                        // supervisor
                        null,
                        // dept_head_id
                        null,
                        // dept_head
                        null,
                        // dept_director_id
                        null,
                        // dept_director
                        null,
                        // dept_vp_id
                        null,
                        // dept_vp
                        null,
                        // executive_vp_id
                        null,
                        // executive_vp
                        null,
                        // aor_id
                        null,
                        // aor
                        null,
                        // organization
                        null,
                        // domain_id
                        $domain_id
                    );
                } else {
                    // we will just re-use the existing user
                    $hr_user_id = $hr_user->id;
                }

                $user = $salesforce_users_mdl->get(array(
                    'sfdc_id' => $record['Id']
                ));

                if (empty($user)) {
					// check if maybe this user has been added without a sfdc_id
					if (!empty($hr_user_id)) {
						$user = $salesforce_users_mdl->get(array(
							'hr_user_id' => $hr_user_id
						));
					}
                }

				if (empty($user)) {
					$salesforce_users_mdl->insert(
                        // sfdc_id
                        $record['Id'],
                        // hr_user_id
                        $hr_user_id
                    );
				} else {
					$salesforce_users_mdl->update($user->id, array(
						'sfdc_id' => (!empty($record['Id'])) ? $record['Id'] : $user->sfdc_id,
						'hr_user_id' => (!empty($hr_user_id)) ? $hr_user_id : $user->hr_user_id
					));
				}
            }
        }
    }

	public function cacheAccount($id = null, $name = null) {
		$salesforce_accounts_mdl = SalesforceAccounts::factory($this->sm);

        $where = $this->where(array(
            (!empty($id)) ? "Id = '{$id}'" : null,
            (!empty($name)) ? "Name = '{$name}'" : null,
        ));

        $query = "
            SELECT
                Id,
                Name,
				IsDeleted
            FROM Account
            {$where}
        ";

        $result = $this->query($query);

        if ($result['done']) {
            foreach ($result['records'] as $record) {
                $account = $salesforce_accounts_mdl->get(array(
                    'sfdc_id' => $record['Id']
                ));

                if (empty($account)) {
                    $salesforce_accounts_mdl->insert(
                        // sfdc_id
                        $record['Id'],
						// is_deleted
						($record['IsDeleted']) ? 1 : 0,
						// name
						$record['Name']
                    );
                } else {
					$salesforce_accounts_mdl->update($account->id, array(
						'is_deleted' => ($record['IsDeleted']) ? 1 : 0,
						'name' => $record['Name']
					));
				}
            }
        }
	}

	public function cacheOsr($osr_number, $row = null) {
		$salesforce_accounts_mdl = SalesforceAccounts::factory($this->sm);
        $salesforce_osrs_mdl = SalesforceOsrs::factory($this->sm);
		$salesforce_osr_products_mdl = SalesforceOsrProducts::factory($this->sm);
        $salesforce_products_mdl = SalesforceProducts::factory($this->sm);
        $salesforce_record_types_mdl = SalesforceRecordTypes::factory($this->sm);
        $salesforce_regions_mdl = SalesforceRegions::factory($this->sm);
        $salesforce_service_types_mdl = SalesforceServiceTypes::factory($this->sm);
        $salesforce_statuses_mdl = SalesforceStatuses::factory($this->sm);
        $salesforce_users_mdl = SalesforceUsers::factory($this->sm);
		$salesforce_lab_locations_mdl = SalesforceLabLocations::factory($this->sm);
		$service_requests_mdl = ServiceRequests::factory($this->sm);
        $systems_mdl = Systems::factory($this->sm);

		$hr_users_mdl = HrUsers::factory($this->sm);
		$hr_domains_mdl = HrDomains::factory($this->sm);

		if($row === null) {
			// dump this guy
			$row = $this->dump(array("Name = '{$osr_number}'"));
			if(is_array($row)) {
				$row = array_shift($row);
			}
		}

		if(empty($row)) {
			// unable to cache ticket
			throw new \Exception("unable to cache osr, could not load from Salesforce");
		}

		var_dump($row);

		// pull the system
        $system = $systems_mdl->get(array(
            'name' => 'Salesforce'
        ));

		if($system === false) {
			$system_id = $systems_mdl->insert('Salesforce');
		} else {
			$system_id = $system->id;
		}

        // pull the record_type
        $record_type_sfdc_id = $row['RecordTypeId'];
		if (!empty($record_type_sfdc_id)) {
			// check if this record type is already cached
			$record_type_row = $salesforce_record_types_mdl->get(array(
				'sfdc_id' => $record_type_sfdc_id
			));
			if (empty($record_type_row)) {
				// let's cache this record type
				$this->cacheRecordType($record_type_sfdc_id);
				$record_type_row = $salesforce_record_types_mdl->get(array(
					'sfdc_id' => $record_type_sfdc_id
				));
			}

			$record_type_id = $record_type_row->id;
		} else {
			$record_type_id = null;
		}

        // pull the created_by user
        $created_by_sfdc_id = $row['CreatedById'];
		if (!empty($created_by_sfdc_id)) {
			$created_by = $salesforce_users_mdl->get(array(
				'sfdc_id' => $created_by_sfdc_id
			));
			if (empty($created_by)) {
				$this->cacheUser($created_by_sfdc_id);
				$created_by = $salesforce_users_mdl->get(array(
					'sfdc_id' => $created_by_sfdc_id
				));
			}

			if ($created_by) {
				$created_by_id = $created_by->id;
			} else {
				$created_by_id = null;
			}
		} else {
			$created_by_id = null;
		}

        // pull the last_modified_by user
        $last_modified_by_sfdc_id = $row['LastModifiedById'];
		if (!empty($last_modified_by_sfdc_id)) {
			$last_modified_by = $salesforce_users_mdl->get(array(
				'sfdc_id' => $last_modified_by_sfdc_id
			));
			if (empty($last_modified_by)) {
				$this->cacheUser($last_modified_by_sfdc_id);
				$last_modified_by = $salesforce_users_mdl->get(array(
					'sfdc_id' => $last_modified_by_sfdc_id
				));
			}

			if ($last_modified_by) {
				$last_modified_by_id = $last_modified_by->id;
			} else {
				$last_modified_by_id = null;
			}
		} else {
			$last_modified_by_id = null;
		}

        // pull the owner user
        $owner_sfdc_id = $row['OwnerId'];
		if (!empty($owner_sfdc_id)) {
			$owner = $salesforce_users_mdl->get(array(
				'sfdc_id' => $owner_sfdc_id
			));
			if (empty($owner)) {
				$this->cacheUser($owner_sfdc_id);
				$owner = $salesforce_users_mdl->get(array(
					'sfdc_id' => $owner_sfdc_id
				));
			}

			if ($owner) {
				$owner_id = $owner->id;
			} else {
				$owner_id = null;
			}
		} else {
			$owner_id = null;
		}

		// pull the tech user
        $tech_sfdc_id = $row['Technical_Contact__r'];
		if (!empty($tech_sfdc_id)) {
			$tech = $salesforce_users_mdl->get(array(
				'sfdc_id' => $tech_sfdc_id
			));
			if (empty($tech)) {
				$this->cacheUser($tech_sfdc_id);
				$tech = $salesforce_users_mdl->get(array(
					'sfdc_id' => $tech_sfdc_id
				));
			}

			if ($tech) {
				$tech_id = $tech->id;
			} else {
				$tech_id = null;
			}
		} else {
			$tech_id = null;
		}

		// pul the customer
		$customer_sfdc_id = $row['Customer_Name__c'];
		if (!empty($customer_sfdc_id)) {
			$customer = $salesforce_accounts_mdl->get(array(
				'sfdc_id' => $customer_sfdc_id
			));
			if (empty($customer)) {
				$this->cacheAccount($customer_sfdc_id);
				$customer = $salesforce_accounts_mdl->get(array(
					'sfdc_id' => $customer_sfdc_id
				));
			}

			if ($customer) {
				$customer_id = $customer->id;
			} else {
				$customer_id = null;
			}
		} else {
			$customer_id = null;
		}

        // pull the region
        $region_name = $row['Region__c'];
		if (!empty($region_name)) {
			$region = $salesforce_regions_mdl->get(array(
				'name' => $region_name
			));
			if (!empty($region)) {
				$region_id = $region->id;
			} else {
				$region_id = $salesforce_regions_mdl->insert(
					$region_name
				);
			}
		} else {
			$region_id = null;
		}

		// pull the lab location
		$lab_location_name = $row['Lab_Location_Required__c'];
		if (!empty($lab_location_name)) {
			$lab_location = $salesforce_lab_locations_mdl->get(array(
				'name' => $lab_location_name
			));
			if (!empty($lab_location)) {
				$lab_location_id = $lab_location->id;
			} else {
				$lab_location_id = $salesforce_lab_locations_mdl->insert(
					$lab_location_name
				);
			}
		}

        // pull the service type
        $service_type_name = $row['Service_Type__c'];
		if (!empty($service_type_name)) {
			$service_type = $salesforce_service_types_mdl->get(array(
				'name' => $service_type_name
			));
			if (!empty($service_type)) {
				$service_type_id = $service_type->id;
			} else {
				$service_type_id = $salesforce_service_types_mdl->insert(
					$service_type_name
				);
			}
		} else {
			$service_type_id = null;
		}

        // pull the status
        $status_name = $row['Status__c'];
		if (!empty($status_name)) {
			$status = $salesforce_statuses_mdl->get(array(
				'name' => $status_name
			));
			if (!empty($status)) {
				$status_id = $status->id;
			} else {
				$status_id = $salesforce_statuses_mdl->insert(
					$status_name
				);
			}
		} else {
			$status_id = null;
		}

		// extract the title
		$title = strip_tags($row['Testing_Requirements__c']);
		if (strlen($title) > 128) {
			$title = substr($title, 0, 128) . '...';
		}

		// check if the osr exists
        $salesforce_osr = $salesforce_osrs_mdl->get(array(
            'sfdc_id' => $row['Id']
        ));

        if (empty($salesforce_osr)) {
            // insert the osr
            $salesforce_osr_id = $salesforce_osrs_mdl->insert(
                // sfdc_id
                $row['Id'],
                // record_type_id
                $record_type_id,
                // name
                $row['Name'],
                // activity_product_release_state
                $row['Activity_Product_Release_State__c'],
                // activity_service
                $row['Activity_Service__c'],
                // activity_stage
                $row['Activity_Stage__c'],
                // activity_type
                $row['Activity_Type__c'],
                // business_unit
                $row['Business_Unit__c'],
                // created_by_id
                $created_by_id,
                // created_date
                self::denormalizeDate($row['CreatedDate']),
				// contact_id
				null,
                // customer_id
				$customer_id,
                // customer_presentation_location
                $row['Customer_Presentation_Location__c'],
                // is_deleted
                ($row['IsDeleted']) ? 1 : 0,
                // last_modified_by_id
                $last_modified_by_id,
                // last_modified_date
                self::denormalizeDate($row['LastModifiedDate']),
                // owner_id
                $owner_id,
				// tech_id
				$tech_id,
                // preparation_start_date
                $row['Preparation_Start_Date__c'],
                // presentation_start_date
                $row['Presentation_Start_Date__c'],
                // region_id
                $region_id,
				// lab_location_id
				$lab_location_id,
                // service_type_id
                $service_type_id,
                // status_id
                $status_id,
				// title
				$title,
                // testing_requirements
                $row['Testing_Requirements__c']
            );
        } else {
            // update the osr
            $salesforce_osr_id = $salesforce_osr->id;
            $salesforce_osrs_mdl->update($salesforce_osr->id, array(
                'record_type_id' => $record_type_id,
                'name' => $row['Name'],
                'activity_product_release_state' => $row['Activity_Product_Release_State__c'],
                'activity_service' => $row['Activity_Service__c'],
                'activity_stage' => $row['Activity_Stage__c'],
                'activity_type' => $row['Activity_Type__c'],
                'business_unit' => $row['Business_Unit__c'],
                'created_by_id' => $created_by_id,
                'created_date' => self::denormalizeDate($row['CreatedDate']),
                'customer_id' => $customer_id,
                'customer_presentation_location' => $row['Customer_Presentation_Location__c'],
                'is_deleted' => ($row['IsDeleted']) ? 1 : 0,
                'last_modified_by_id' => $last_modified_by_id,
                'last_modified_date' => self::denormalizeDate($row['LastModifiedDate']),
                'owner_id' => $owner_id,
				'tech_id' => $tech_id,
                'preparation_start_date' => $row['Preparation_Start_Date__c'],
                'presentation_start_date' => $row['Presentation_Start_Date__c'],
                'region_id' => $region_id,
				'lab_location_id' => $lab_location_id,
                'service_type_id' => $service_type_id,
                'status_id' => $status_id,
				'title' => $title,
                'testing_requirements' => $row['Testing_Requirements__c']
            ));
        }

		// pull the product and link them all
		$product_ids = array();
        $products = $row['Product_Under_Test__c'];
		if (!empty($products)) {
			$products = explode(';', $products);

			foreach ($products as $product_name) {
				$product = $salesforce_products_mdl->get(array(
					'name' => $product_name
				));
				if (!empty($product)) {
					$product_id = $product->id;
				} else {
					$product_id = $salesforce_products_mdl->insert(
						$product_name
					);
				}

				$link = $salesforce_osr_products_mdl->get(array(
					'osr_id' => $salesforce_osr_id,
					'product_id' => $product_id
				));

				if (empty($link)) {
					$salesforce_osr_products_mdl->insert(
						$salesforce_osr_id,
						$product_id
					);
				}

				$product_ids[] = $product_id;
			}
		}

		// remove any products that are no longer part of this request
		$where = new Where();
		$where->equalTo('osr_id', $salesforce_osr_id);
		if (count($product_ids) > 0) {
			$where->notIn('product_id', $product_ids);
		}

		$salesforce_osr_products_mdl->delete($where);

        // make sure the osr is linked as a service request
        $service_request = $service_requests_mdl->get(array(
            'system_id' => $system_id,
            'request_id' => $salesforce_osr_id
        ));

        if (empty($service_request)) {
            $service_requests_mdl->insert(
                // system_id
                $system_id,
                // request_id
                $salesforce_osr_id
            );
        }
	}

	public function cacheOsrs($where, $offset = 0, $limit = null) {
		$db = $this->sm->get('db');
		$con = $db->getDriver()->getConnection();

		$rows = $this->dump($where, $offset, $limit);
		$commited = true;

		try {
			foreach($rows as $row) {
				$con->beginTransaction();
				$commited = false;

				if($this->hasLogger()) {
					$this->logger->log(Logger::INFO, "caching osr {$row['Name']}");
				}

				$this->cacheOsr($row['Name'], $row);

				$con->commit();
				$commited = true;
			}
		} catch(\Exception $e) {
			if($commited !== true) {
				$con->rollback();
			}

			// re-throw this error
			throw $e;
		}
	}
}
?>
