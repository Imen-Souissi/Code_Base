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

class FootprintsTicketAssignees extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = null;
	protected $hr_database;
	
	public function __construct(Adapter $dbh, $database = 'ticket', $hr_database = 'hr') {
		$this->hr_database = $hr_database;
		parent::__construct($dbh, new TableIdentifier('footprints_ticket_assignees', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['ticket'], $databases['hr']);
	}
	
	public function insert($ticket, $user_id) {
		return $this->_insert(array(
			'ticket' => $ticket,
			'user_id' => $user_id
		));
	}
	
	public function replace($ticket, $user_id) {
		return $this->_replace(array(
			'ticket' => $ticket,
			'user_id' => $user_id
		));
	}
	
	public function filter($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		$hr_database = $this->hr_database;
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database, $hr_database) {			
			$select->join(array('FU' => new TableIdentifier('footprints_users', $database)),
						  "{$table}.user_id = FU.id",
						  array(
								'assignee' => new Expression("IF(HU.id IS NULL, FU.username, HU.display_name)")
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('HU' => new TableIdentifier('hr_users', $hr_database)),
						  "FU.hr_user_id = HU.id",
						  array(),
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