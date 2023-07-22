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

class FootprintsTickets extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = 'ticket';
	protected $hr_database;
	
	public function __construct(Adapter $dbh, $database = 'ticket', $hr_database = 'hr') {
		$this->hr_database = $hr_database;
		parent::__construct($dbh, new TableIdentifier('footprints_tickets', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['ticket'], $databases['hr']);
	}
	
	public function insert(
		$ticket,
		$title,
		$priority_id,
		$contact_id,
		$submit_by_id,
		$submit_date,
		$update_date,
		$activity_id,
		$site_id,
		$lab_id,
		$type_id,
		$category_id,
		$sub_category_id,
		$eng_project_id,
		$weight,
		$status_id,
		$status_date,
		$status_by_id
	) {
		return $this->_insert(array(
			'ticket' => $ticket,
			'title' => $title,
			'priority_id' => $priority_id,
			'contact_id' => $contact_id,
			'submit_by_id' => $submit_by_id,
			'submit_date' => $submit_date,
			'update_date' => $update_date,
			'activity_id' => $activity_id,
			'site_id' => $site_id,
			'lab_id' => $lab_id,
			'type_id' => $type_id,
			'category_id' => $category_id,
			'sub_category_id' => $sub_category_id,
			'eng_project_id' => $eng_project_id,
			'weight' => $weight,
			'status_id' => $status_id,
			'status_date' => $status_date,
			'status_by_id' => $status_by_id,
			'etime' => new Expression("NOW()")
		));
	}
	
	public function replace(
		$ticket,
		$title,
		$priority_id,
		$contact_id,
		$submit_by_id,
		$submit_date,
		$update_date,
		$activity_id,
		$site_id,
		$lab_id,
		$type_id,
		$category_id,
		$sub_category_id,
		$eng_project_id,
		$weight,
		$status_id,
		$status_date,
		$status_by_id
	) {
		return $this->_replace(array(
			'ticket' => $ticket,
			'title' => $title,
			'priority_id' => $priority_id,
			'contact_id' => $contact_id,
			'submit_by_id' => $submit_by_id,
			'submit_date' => $submit_date,
			'update_date' => $update_date,
			'activity_id' => $activity_id,
			'site_id' => $site_id,
			'lab_id' => $lab_id,
			'type_id' => $type_id,
			'category_id' => $category_id,
			'sub_category_id' => $sub_category_id,
			'eng_project_id' => $eng_project_id,
			'weight' => $weight,
			'status_id' => $status_id,
			'status_date' => $status_date,
			'status_by_id' => $status_by_id,
			'etime' => new Expression("NOW()")
		));
	}
	
	public function get($identifiers) {
		$identifiers = $this->normalizeIdentifiers($identifiers);
		$result = $this->filter($identifiers, array(), array(), $total);
		
		return $result->current();
	}
	
	public function filter($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table) {
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
	
	public function getSimple($identifiers) {
		$identifiers = $this->normalizeIdentifiers($identifiers);
		$result = $this->filterSimple($identifiers, array(), array(), $total);
		
		return $result->current();
	}
	
	public function filterSimple($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		$hr_database = $this->hr_database;
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database, $hr_database) {
			$select->columns(array(
				'ticket',
				'title',
				'submit_date',
				'contact_id',
				'site_id'
			));
			
			$select->join(array('FU' => new TableIdentifier('footprints_users', $database)),
						  "{$table}.contact_id = FU.id",
						  array(),
						  Select::JOIN_LEFT);
			$select->join(array('HU' => new TableIdentifier('hr_users', $hr_database)),
						  "FU.hr_user_id = HU.id",
						  array(
								'contact_hr_id' => 'id',
								'department_id'
						  ),
						  Select::JOIN_LEFT);			
			$select->join(array('S' => new TableIdentifier('footprints_sites', $database)),
						  "{$table}.site_id = S.id",
						  array(
								'site' => 'name'
						  ),
						  Select::JOIN_LEFT);
			
			$columns = array(
				'department_id' => 'HU.department_id',
				'site' => 'S.name'
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
	
	public function getDetail($identifiers) {
		$identifiers = $this->normalizeIdentifiers($identifiers);
		$result = $this->filterDetail($identifiers, array(), array(), $total);
		
		return $result->current();
	}
	
	public function filterDetail($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		$hr_database = $this->hr_database;
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database, $hr_database) {
			$select->join(array('FU1' => new TableIdentifier('footprints_users', $database)),
						  "{$table}.contact_id = FU1.id",
						  array(),
						  Select::JOIN_LEFT);
			$select->join(array('HU1' => new TableIdentifier('hr_users', $hr_database)),
						  "FU1.hr_user_id = HU1.id",
						  array(
								'contact_hr_id' => 'id',
								'contact_name' => 'display_name'
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('FU2' => new TableIdentifier('footprints_users', $database)),
						  "{$table}.submit_by_id = FU2.id",
						  array(),
						  Select::JOIN_LEFT);
			$select->join(array('HU2' => new TableIdentifier('hr_users', $hr_database)),
						  "FU2.hr_user_id = HU2.id",
						  array(
								'submit_by_name' => 'display_name'
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('FU3' => new TableIdentifier('footprints_users', $database)),
						  "{$table}.status_by_id = FU3.id",
						  array(),
						  Select::JOIN_LEFT);
			$select->join(array('HU3' => new TableIdentifier('hr_users', $hr_database)),
						  "FU3.hr_user_id = HU3.id",
						  array(
								'status_by_name' => 'display_name'
						  ),
						  Select::JOIN_LEFT);			
			$select->join(array('FS' => new TableIdentifier('footprints_statuses', $database)),
						  "{$table}.status_id = FS.id",
						  array(
								'status' => 'name'
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('FP' => new TableIdentifier('footprints_priorities', $database)),
						  "{$table}.priority_id = FP.id",
						  array(
								'priority' => 'name'
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('FA' => new TableIdentifier('footprints_activities', $database)),
						  "{$table}.activity_id = FA.id",
						  array(
								'activity' => 'name'
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('FSS' => new TableIdentifier('footprints_sites', $database)),
						  "{$table}.site_id = FSS.id",
						  array(
								'site' => 'name'
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('FL' => new TableIdentifier('footprints_labs', $database)),
						  "{$table}.lab_id = FL.id",
						  array(
								'lab' => 'name'
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('FT' => new TableIdentifier('footprints_types', $database)),
						  "{$table}.type_id = FT.id",
						  array(
								'type' => 'name'
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('FC' => new TableIdentifier('footprints_categories', $database)),
						  "{$table}.category_id = FC.id",
						  array(
								'category' => 'name'
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('FSC' => new TableIdentifier('footprints_sub_categories', $database)),
						  "{$table}.sub_category_id = FSC.id",
						  array(
								'sub_category' => 'name'
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('FEP' => new TableIdentifier('footprints_eng_projects', $database)),
						  "{$table}.eng_project_id = FEP.id",
						  array(
								'eng_project' => 'name'
						  ),
						  Select::JOIN_LEFT);
			
			$columns = array(
				'contact_name' => 'HU1.display_name',
				'submit_by_name' => 'HU2.display_name',
				'status_by_name' => 'HU3.display_name',
				'status' => 'FS.name',
				'priority' => 'FP.name',
				'activity' => 'FA.name',
				'site' => 'FSS.name',
				'lab' => 'FL.name',
				'type' => 'FT.name',
				'category' => 'FC.name',
				'sub_category' => 'FSC.name',
				'eng_project' => 'FEP.name'
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array(), $columns)
				 ->applyRange($select, $range);
			$select->group(array("{$table}.ticket"));
			
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
}
?>