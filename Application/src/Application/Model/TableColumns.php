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

class TableColumns extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = 'id';
	
	public function __construct(Adapter $dbh, $database = 'app') {
		parent::__construct($dbh, new TableIdentifier('table_columns', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['app']);
	}
	
	public function insert(
		$table_id,
		$field,
		$display,
		$sort = 0,
		$show = 'DYNAMIC',
		$active = 1
	) {
		return $this->_insert(array(
            'table_id' => $table_id,
            'field' => $field,
            'display' => $display,
            'sort' => $sort,
			'active' => $active,
			'show' => $show,
			'ctime' => new Expression("NOW()"),
            'etime' => new Expression("NOW()")
		));
	}
	
	public function filter($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table,  $database) {
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
	
	public function filterUser($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table,  $database) {
			$select->columns(array(
				'id',
				'table_id',
				'field',
				'field_display' => 'display',
				'display' => new Expression("IF(TUC.display IS NULL, {$table}.display, TUC.display)"),
				'sort' => new Expression("IF(TUC.sort IS NULL, {$table}.sort, TUC.sort)"),
				'show' => new Expression("IF(TUC.show IS NULL, {$table}.show, TUC.show)")
			));
			
			$select->join(array('TUC' => new TableIdentifier('table_user_columns', $database)),
						  new Expression("{$table}.id = TUC.column_id AND TUC.user_id = ?", $filter['user_id']),
						  array(),
						  Select::JOIN_LEFT);
			
			unset($filter['user_id']);
			
			$columns = array(
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array(new Expression("IF(TUC.sort IS NULL, {$table}.sort, TUC.sort) ASC")), $columns)
				 ->applyRange($select, $range);
			$select->group(array("{$table}.id", "TUC.user_id"));
				 
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
}
?>