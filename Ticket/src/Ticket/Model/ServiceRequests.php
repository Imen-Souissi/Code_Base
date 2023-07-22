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

class ServiceRequests extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = 'id';
	protected $hr_database;
    
	public function __construct(Adapter $dbh, $database = 'ticket', $hr_database = 'hr') {
        $this->hr_database = $hr_database;
		parent::__construct($dbh, new TableIdentifier('service_requests', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['ticket'], $databases['hr']);
	}
	
	public function insert(
        $system_id,
        $request_id
    ) {
		return $this->_insert(array(
            'system_id' => $system_id,
            'request_id' => $request_id,
			'ctime' => new Expression('NOW()'),
			'etime' => new Expression('NOW()')
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
    
    public function filterFootprints($filter, $range, $sort, &$total) {
		$self = $this;
        $database = $this->getDatabaseName();
		$hr_database = $this->hr_database;
        $table = $this->getTableName();
        
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $database, $hr_database, $table) {
            $select->join(array('FT' => new TableIdentifier('footprints_tickets', $database)),
                          "{$table}.request_id = FT.ticket",
                          array(
                                'title',
                                'number' => 'ticket',
                                'submit_date'
                          ),
                          Select::JOIN_LEFT);
            $select->join(array('FS' => new TableIdentifier('footprints_statuses', $database)),
                          "FT.status_id = FS.id",
                          array(
                                'status' => 'name'
                          ),
                          Select::JOIN_LEFT);
            $select->join(array('FU' => new TableIdentifier('footprints_users', $database)),
                          "FT.contact_id = FU.id",
                          array(),
                          Select::JOIN_LEFT);
            $select->join(array('U' => new TableIdentifier('hr_users', $hr_database)),
                          "FU.hr_user_id = U.id",
                          array(
                                'contact' => 'display_name'
                          ),
                          Select::JOIN_LEFT);
            
			$columns = array(
                'title' => 'FT.title',
                'number' => 'FT.ticket',
                'submit_date' => 'FT.submit_date',
                'status' => 'FS.name',
				'status_id' => 'FT.status_id',
                'contact' => 'U.display_name',
				'hr_contact_id' => 'U.id'
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
    
    public function filterSalesforce($filter, $range, $sort, &$total) {
		$self = $this;
        $database = $this->getDatabaseName();
		$hr_database = $this->hr_database;
        $table = $this->getTableName();
        
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $database, $hr_database, $table) {
            $select->join(array('SO' => new TableIdentifier('salesforce_osrs', $database)),
                          "{$table}.request_id = SO.id",
                          array(
                                'title' => 'title',
                                'number' => 'name',
                                'submit_date' => 'created_date'
                          ),
                          Select::JOIN_LEFT);
            $select->join(array('SS' => new TableIdentifier('salesforce_statuses', $database)),
                          "SO.status_id = SS.id",
                          array(
                                'status' => 'name'
                          ),
                          Select::JOIN_LEFT);
            $select->join(array('SU' => new TableIdentifier('salesforce_users', $database)),
                          "SO.contact_id = SU.id",
                          array(),
                          Select::JOIN_LEFT);
			$select->join(array('U' => new TableIdentifier('hr_users', $hr_database)),
                          "SU.hr_user_id = U.id",
                          array(
                                'contact' => 'display_name'
                          ),
                          Select::JOIN_LEFT);
			
			$columns = array(
                'title' => 'SO.title',
                'number' => 'SO.name',
                'submit_date' => 'SO.created_date',                
                'status' => 'SS.name',
				'status_id' => 'SO.status_id',
				'contact' => 'U.display_name',
				'hr_contact_id' => 'U.id'
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