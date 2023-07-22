<?php
namespace Application\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\TableIdentifier;

use Els\Mvc\Model\DbModel;
use Els\Mvc\Model\DbModelFactoryInterface;

use Zend\ServiceManager\ServiceLocatorInterface;

class Settings extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = 'id';
	
	public function __construct(Adapter $dbh, $database = 'app') {
		parent::__construct($dbh, new TableIdentifier('settings', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['app']);
	}
	
	public function insert(
		$user_id,
		$app,
		$field,
		$value
	) {
		return $this->_insert(array(
            'user_id' => $user_id,
            'app' => $app,
            'field' => $field,
            'value' => $value,
			'ctime' => new Expression("NOW()"),
            'etime' => new Expression("NOW()")
		));
	}
	
	public function filter($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database) {
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