<?php
namespace Contract\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\TableIdentifier;

use Els\Mvc\Model\DbModel;
use Els\Mvc\Model\DbModelFactoryInterface;

use Zend\ServiceManager\ServiceLocatorInterface;

class ContractUserLinks extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = 'id';
	protected $hr_database;
	
	public function __construct(Adapter $dbh, $database = 'contract', $hr_database = 'hr') {
		$this->hr_database = $hr_database;
		parent::__construct($dbh, new TableIdentifier('contract_user_links', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['contract'], $databases['hr']);
	}
	
	public function insert($contract_id, $user_id) {
		return $this->_insert(array(
			'contract_id' => $contract_id,
			'user_id' => $user_id
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
		$database = $this->getDatabaseName();
		$hr_database = $this->hr_database;
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database, $hr_database) {
			$select->join(array('U' => new TableIdentifier('hr_users', $hr_database)),
					"{$table}.user_id = U.id",
					array(
						'name' => 'full_name',
						'email',
						'department'
					),
					Select::JOIN_INNER);
			$columns = array(
				'name' => 'U.full_name',
				'email' => 'U.email'
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