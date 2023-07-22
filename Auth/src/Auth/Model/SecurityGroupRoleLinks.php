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

class SecurityGroupRoleLinks extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = null;
	protected $hr_database;
	
	public function __construct(Adapter $dbh, $database = 'app', $hr_database = 'hr') {
		$this->hr_database = $hr_database;
		parent::__construct($dbh, new TableIdentifier('security_group_role_links', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['app'], $databases['hr']);
	}
	
	public function insert(
		$group_id,
		$role_id
	) {
		return $this->_insert(array(
			'group_id' => $group_id,
			'role_id' => $role_id
		));
	}
	
	public function filter($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		$hr_database = $this->hr_database;
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database, $hr_database) {
			$select->join(array('G' => new TableIdentifier('hr_groups', $hr_database)),
						  "{$table}.group_id = G.id",
						  array(
								'group' => 'name'
						  ),
						  Select::JOIN_INNER);
			$select->join(array('R' => new TableIdentifier('security_roles', $database)),
						  "{$table}.role_id = R.id",
						  array(
								'role' => 'name',
								'role_rights_level' => 'rights_level'
						  ),
						  Select::JOIN_INNER);
			$select->join(array('D' => new TableIdentifier('hr_domains', $hr_database)),
						  "G.domain_id = D.id",
						  array(
								'domain' => 'name'
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('UGL' => new TableIdentifier('hr_user_group_links', $hr_database)),
						  "G.id = UGL.group_id",
						  array(
								'users' => new Expression("COUNT(DISTINCT UGL.user_id)")
						  ),
						  Select::JOIN_LEFT);
			
			$columns = array(
				'user_id' => 'UGL.user_id',
				'group' => 'G.name',
				'role' => 'R.name',
				'domain' => 'D.name'
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array(), $columns)
				 ->applyRange($select, $range);
			$select->group(array("{$table}.role_id", "{$table}.group_id"));
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
}
?>