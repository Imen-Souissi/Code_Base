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

class FootprintsTicketLinks extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = 'id';
	protected $hr_database;
	
	const TYPE_STATIC = 'STATIC';
	const TYPE_DYNAMIC = 'DYNAMIC';
	const TYPE_PARENT = 'PARENT';
	const TYPE_CHILD = 'CHILD';
	
	public function __construct(Adapter $dbh, $database = 'ticket', $hr_database = 'hr') {
		$this->hr_database = $hr_database;
		parent::__construct($dbh, new TableIdentifier('footprints_ticket_links', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['ticket'], $databases['hr']);
	}
	
	public function insert($ticket, $sub_ticket, $type = self::TYPE_STATIC) {
		return $this->_insert(array(
			'ticket' => $ticket,
			'sub_ticket' => $sub_ticket,
			'type' => $type,
			'ctime' => new Expression('NOW()')
		));
	}
	
	public function filter($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table) {
			$columns = array(
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array("ticket ASC"), $columns)
				 ->applyRange($select, $range);
				 
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
	
	public function getTicket($identifiers) {
		$identifiers = $this->normalizeIdentifiers($identifiers);
		$result = $this->filterTicket($identifiers, array(), array(), $total);
		return $result->current();
	}
	
	public function filterTicket($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		$hr_database = $this->hr_database;
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database, $hr_database) {
			$select->join(array('T' => new TableIdentifier('footprints_tickets', $database)),
						  "{$table}.sub_ticket = T.ticket",
						  array(
								'title'
						  ),
						  Select::JOIN_INNER);
			$select->join(array('U1' => new TableIdentifier('footprints_users', $database)),
						  "T.contact_id = U1.id",
						  array(
								'contact_username' => 'username'
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('HU1' => new TableIdentifier('hr_users', $hr_database)),
						  "U1.hr_user_id = HU1.id",
						  array(
								'contact_name' => 'display_name'
						  ),
						  Select::JOIN_LEFT);			
			$select->join(array('U2' => new TableIdentifier('footprints_users', $database)),
						  "T.submit_by_id = U2.id",
						  array(
								'submit_by_username' => 'username'
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('HU2' => new TableIdentifier('hr_users', $hr_database)),
						  "U2.hr_user_id = HU2.id",
						  array(
								'submit_by_name' => 'display_name'
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('U3' => new TableIdentifier('footprints_users', $database)),
						  "T.status_by_id = U3.id",
						  array(
								'status_by_username' => 'username'
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('HU3' => new TableIdentifier('hr_users', $hr_database)),
						  "U3.hr_user_id = HU3.id",
						  array(
								'status_by_name' => 'display_name'
						  ),
						  Select::JOIN_LEFT);			
			$select->join(array('FS' => new TableIdentifier('footprints_statuses', $database)),
						  "T.status_id = FS.id",
						  array(
								'status' => 'name'
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('FP' => new TableIdentifier('footprints_priorities', $database)),
						  "T.priority_id = FP.id",
						  array(
								'priority' => 'name'
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('FA' => new TableIdentifier('footprints_activities', $database)),
						  "T.activity_id = FA.id",
						  array(
								'activity' => 'name'
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('FSS' => new TableIdentifier('footprints_sites', $database)),
						  "T.site_id = FSS.id",
						  array(
								'site' => 'name'
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('FL' => new TableIdentifier('footprints_labs', $database)),
						  "T.lab_id = FL.id",
						  array(
								'lab' => 'name'
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('FT' => new TableIdentifier('footprints_types', $database)),
						  "T.type_id = FT.id",
						  array(
								'type' => 'name'
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('FC' => new TableIdentifier('footprints_categories', $database)),
						  "T.category_id = FC.id",
						  array(
								'category' => 'name'
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('FSC' => new TableIdentifier('footprints_sub_categories', $database)),
						  "T.sub_category_id = FSC.id",
						  array(
								'sub_category' => 'name'
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('FEP' => new TableIdentifier('footprints_eng_projects', $database)),
						  "T.eng_project_id = FEP.id",
						  array(
								'eng_project' => 'name'
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('FTA' => new TableIdentifier('footprints_ticket_assignees', $database)),
						  "T.ticket = FTA.ticket",
						  array(),
						  Select::JOIN_LEFT);
			$select->join(array('U4' => new TableIdentifier('footprints_users', $database)),
						  "FTA.user_id = U4.id",
						  array(),
						  Select::JOIN_LEFT);
			$select->join(array('HU4' => new TableIdentifier('hr_users', $hr_database)),
						  "U4.hr_user_id = HU4.id",
						  array(
								'assignees' => new Expression("GROUP_CONCAT(HU4.display_name ORDER BY HU4.display_name SEPARATOR ', ')")
						  ),
						  Select::JOIN_LEFT);
			
			$columns = array(
				'link_type' => "{$table}.type",
				'title' => 'T.title',
				'contact_username' => 'U1.username',
				'contact_name' => 'HU1.display_name',
				'submit_by_username' => 'U2.username',
				'submit_by_name' => 'HU2.display_name',
				'status_by_username' => 'U3.username',
				'status_by_name' => 'HU3.display_name',
				'status' => 'FS.name',
				'priority' => 'FP.name',
				'activity' => 'FA.name',
				'site' => 'FSS.name',
				'lab' => 'FL.name',
				'type' => 'FT.name',
				'category' => 'FC.name',
				'sub_category' => 'FSC.name',
				'eng_project' => 'FEP.name',
				'assignees' => 'HU4.name'
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array("sub_ticket ASC"), $columns)
				 ->applyRange($select, $range);
			$select->group(array("{$table}.sub_ticket"));
			
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
}
?>