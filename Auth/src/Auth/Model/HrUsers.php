<?php
namespace Auth\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\TableIdentifier;

use Hr\Model\HrUsers as HrHrUsers;
use Zend\ServiceManager\ServiceLocatorInterface;

class HrUsers extends HrHrUsers {
	protected $auth_database;
	
	public function __construct(Adapter $dbh, $database = 'hr', $auth_database = 'app') {
		$this->auth_database = $auth_database;
		parent::__construct($dbh, $database);
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['hr'], $databases['app']);
	}
	
	public function filterAvailable($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		$auth_database = $this->auth_database;
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database, $auth_database) {
			$select->columns(array(
				'id',
				'display_name',
				'unavailable' => new Expression("MAX(IF(URL.role_id = ?, 1, 0))", array($filter['role_id'])),
			));
			
			$having = array();
			if($filter['role_id']) {
				$having['unavailable'] = 0;
			}
			unset($filter['role_id']);
			
			$select->join(array('URL' => new TableIdentifier('security_user_role_links', $auth_database)),
						  "{$table}.id = URL.user_id",
						  array(),
						  Select::JOIN_LEFT);
			
			$columns = array(
				'role_id' => 'URL.role_id'
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array("{$table}.full_name ASC"), $columns)
				 ->applyRange($select, $range);
			$select->group(array("{$table}.id"));
			$select->having($having);
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
}
?>