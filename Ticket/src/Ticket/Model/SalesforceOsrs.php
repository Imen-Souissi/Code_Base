<?php
namespace Ticket\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\TableIdentifier;

use Els\Mvc\Model\DbModel;
use Els\Mvc\Model\DbModelFactoryInterface;

use Zend\ServiceManager\ServiceLocatorInterface;

class SalesforceOsrs extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = 'id';
    protected $hr_database;
	
	public function __construct(Adapter $dbh, $database = 'ticket', $hr_database = 'hr') {
		$this->hr_database = $hr_database;
		parent::__construct($dbh, new TableIdentifier('salesforce_osrs', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['ticket'], $databases['hr']);
	}
	
	public function insert(
        $sfdc_id,
        $record_type_id,
        $name,
        $activity_product_release_state,
        $activity_service,
        $activity_stage,
        $activity_type,
        $business_unit,
        $created_by_id,
        $created_date,
		$contact_id,
        $customer_id,
        $customer_presentation_location,
        $is_deleted,
        $last_modified_by_id,
        $last_modified_date,
        $owner_id,
		$tech_id,
        $preparation_start_date,
        $presentation_start_date,
        $region_id,
		$lab_location_id,
        $service_type_id,
        $status_id,
		$title,
        $testing_requirements
    ) {
		return $this->_insert(array(
			'sfdc_id' => $sfdc_id,
            'record_type_id' => $record_type_id,
            'name' => $name,
            'activity_product_release_state' => $activity_product_release_state,
            'activity_service' => $activity_service,
            'activity_stage' => $activity_stage,
            'activity_type' => $activity_type,
            'business_unit' => $business_unit,
            'created_by_id' => $created_by_id,
            'created_date' => $created_date,
			'contact_id' => $contact_id,
            'customer_id' => $customer_id,
            'customer_presentation_location' => $customer_presentation_location,
            'is_deleted' => $is_deleted,
            'last_modified_by_id' => $last_modified_by_id,
            'last_modified_date' => $last_modified_date,
            'owner_id' => $owner_id,
			'tech_id' => $tech_id,
            'preparation_start_date' => $preparation_start_date,
            'presentation_start_date' => $presentation_start_date,
            'region_id' => $region_id,
			'lab_location_id' => $lab_location_id,
            'service_type_id' => $service_type_id,
            'status_id' => $status_id,
			'title' => $title,
            'testing_requirements' => $testing_requirements
		));
	}
    
    public function get($identifiers) {
		$identifiers = $this->normalizeIdentifiers($identifiers);
		$result = $this->filter($identifiers, array(), array(), $total);
		
		return $result->current();
	}
	
	public function filter($filter, $range, $sort, &$total) {
		$self = $this;
		$database = $this->getDatabaseName();
		$table = $this->getTableName();
		$hr_database = $this->hr_database;
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $database, $table, $hr_database) {
			$select->join(array('CU' => new TableIdentifier('salesforce_users', $database)),
                          "{$table}.contact_id = CU.id",
                          array(),
                          Select::JOIN_LEFT);
			$select->join(array('CHU' => new TableIdentifier('hr_users', $hr_database)),
                          "CU.hr_user_id = CHU.id",
                          array(
								'contact' => 'display_name',
								'contact_email' => 'email',
								'contact_department' => 'department'
						  ),
                          Select::JOIN_LEFT);
			$select->join(array('OU' => new TableIdentifier('salesforce_users', $database)),
                          "{$table}.owner_id = OU.id",
                          array(),
                          Select::JOIN_LEFT);
			$select->join(array('OHU' => new TableIdentifier('hr_users', $hr_database)),
                          "OU.hr_user_id = OHU.id",
                          array(
								'owner' => 'display_name'
						  ),
                          Select::JOIN_LEFT);
			$select->join(array('LMU' => new TableIdentifier('salesforce_users', $database)),
                          "{$table}.last_modified_by_id = LMU.id",
                          array(),
                          Select::JOIN_LEFT);
			$select->join(array('LMHU' => new TableIdentifier('hr_users', $hr_database)),
                          "LMU.hr_user_id = LMHU.id",
                          array(
								'last_modified_by' => 'display_name',
								'last_modified_by_email' => 'email'
						  ),
                          Select::JOIN_LEFT);
			$select->join(array('S' => new TableIdentifier('salesforce_statuses', $database)),
                          "{$table}.status_id = S.id",
                          array(
								'status' => 'name'
						  ),
                          Select::JOIN_LEFT);
			$select->join(array('LL' => new TableIdentifier('salesforce_lab_locations', $database)),
                          "{$table}.lab_location_id = LL.id",
                          array(
								'lab_location' => 'name'
						  ),
                          Select::JOIN_LEFT);
			$select->join(array('R' => new TableIdentifier('salesforce_regions', $database)),
                          "{$table}.region_id = R.id",
                          array(
								'region' => 'name'
						  ),
                          Select::JOIN_LEFT);
			
			$columns = array(
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array(), $columns)
				 ->applyRange($select, $range);
				 
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
}
?>