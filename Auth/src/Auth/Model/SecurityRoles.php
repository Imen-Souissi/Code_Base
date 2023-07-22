<?php
namespace Auth\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\TableIdentifier;

use Els\Mvc\Model\DbModel;
use Els\Mvc\Model\DbModelFactoryInterface;

use Zend\ServiceManager\ServiceLocatorInterface;

class SecurityRoles extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = 'id';
	
	public function __construct(Adapter $dbh, $database = 'app') {
		parent::__construct($dbh, new TableIdentifier('security_roles', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['app']);
	}
	
	public function insert(
		$name,
		$description,
		$rights_level = 0
	) {
		return $this->_insert(array(
			'name' => $name,
			'description' => $description,
			'rights_level' => $rights_level,
			'ctime' => new Expression("NOW()")
		));
	}
	
	public function filter($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database) {
			$select->join(array('RPL' => new TableIdentifier('security_role_permission_links', $database)),
						  "{$table}.id = RPL.role_id",
						  array(
								'permissions' => new Expression("COUNT(DISTINCT RPL.permission_id)")
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('URL' => new TableIdentifier('security_user_role_links', $database)),
						  "{$table}.id = URL.role_id",
						  array(
								'users' => new Expression("COUNT(DISTINCT URL.user_id)")
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('GRL' => new TableIdentifier('security_group_role_links', $database)),
						  "{$table}.id = GRL.role_id",
						  array(
								'groups' => new Expression("COUNT(DISTINCT GRL.group_id)")
						  ),
						  Select::JOIN_LEFT);			
			
			$columns = array(
				'permission_id' => 'RPL.permission_id',
				'user_id' => 'URL.user_id',
				'group_id' => 'GRL.group_id',
				'rights_level' => "{$table}.rights_level"
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array(), $columns)
				 ->applyRange($select, $range);
			$select->group(array("{$table}.id"));
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
	
	public function filterAvailable($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database) {
			$filter['permission_id'] = (empty($filter['permission_id'])) ? 0 : $filter['permission_id'];
			
			$select->columns(array(
				'*',
				'unavailable' => new Expression("MAX(IF(RPL.permission_id = ?, 1, 0))", array($filter['permission_id']))
			));
			unset($filter['permission_id']);
			
			$select->join(array('RPL' => new TableIdentifier('security_role_permission_links', $database)),
						  "{$table}.id = RPL.role_id",
						  array(
								'permissions' => new Expression("COUNT(DISTINCT RPL.permission_id)")
						  ),
						  Select::JOIN_LEFT);
			
			$columns = array(
				'permission_id' => 'RPL.permission_id'
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array(), $columns)
				 ->applyRange($select, $range);
			$select->group(array("{$table}.id"));
			$select->having(array('unavailable' => 0));
			
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
	
	public function getExcludedAvailable($identifiers) {
        $identifiers = $this->normalizeIdentifier($identifiers);
		$result = $this->filterExcludedAvailable($identifiers, array(), array(), $total);
		return $result->current();
    }
	
	public function filterExcludedAvailable($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database) {
			$filter['role_id'] = (empty($filter['role_id'])) ? 0 : $filter['role_id'];
			
			$select->columns(array(
				'*',
				'unavailable' => new Expression("MAX(IF(RER.role_id = ?, 1, 0))", array($filter['role_id']))
			));
			unset($filter['role_id']);
			
			$select->join(array('RER' => new TableIdentifier('security_role_exclude_roles', $database)),
						  "{$table}.id = RER.role_id",
						  array(),
						  Select::JOIN_LEFT);
			
			$columns = array(
				'exclude_role_id' => 'RER.exclude_role_id'
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array(), $columns)
				 ->applyRange($select, $range);
			$select->group(array("{$table}.id"));
			$select->having(array('unavailable' => 0));
			
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
}
?>