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

class PaymentRunRates extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = 'id';
	protected $gem_database;
	
	public function __construct(Adapter $dbh, $database = 'contract', $gem_database = 'gem') {
		$this->gem_database = $gem_database;
		parent::__construct($dbh, new TableIdentifier('payment_run_rates', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['contract'], $databases['gem']);
	}
	
	public function insert($data) {
		return $this->_insert(array(
			'contract_id' => $data['contract_id'],
            'month' => $data['month'],
			'year' => $data['year'],
			'year_month' => $data['year_month'],
			'amount' => $data['amount'],
			'num' => $data['num']
		));
	}
	
	public function get($identifiers) {
		$identifiers = $this->normalizeIdentifiers($identifiers);
		$result = $this->filter($identifiers, array(), array(), $total);
		return $result->current();
	}

	public function getRollup($identifiers) {
		$identifiers = $this->normalizeIdentifiers($identifiers);
		$result = $this->filterRollup($identifiers, array(), array(), $total);
		return $result->current();
	}
	
	public function filter($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		$gem_database = $this->gem_database;
		
		$need_labview_id = $this->usingFilterField($filter, array('labview_id'));
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database, $gem_database, $need_labview_id) {
			if(empty($filter['access'])) {
				$filter['access'] = new Expression("1");
			}
			
			$select->columns(array(
				'*',
				'has_access' => $filter['access']
			));
				
			if(!empty($filter['access'])) {
				$having['has_access'] = 1;
			}
			unset($filter['access']);
			
			$select->join(array('CM' => new TableIdentifier('calendar_months', $database)),
					"CM.id = {$table}.month",
					array(
						'month_name' => 'name',
					),
					Select::JOIN_INNER);
			$select->join(array('C' => new TableIdentifier('contracts', $database)),
					"C.id = {$table}.contract_id",
					array(
						'contract_number',
						'contract_description' => 'description',
					),
					Select::JOIN_INNER);
			$select->join(array('CT' => new TableIdentifier('contract_types', $database)),
					"CT.id = C.type_id",
					array(
						'contract_type' => 'name',
					),
					Select::JOIN_INNER);
			$select->join(array('V' => new TableIdentifier('vendors', $database)),
					"V.id = C.vendor_id",
					array(
						'vendor' => 'name',
					),
					Select::JOIN_INNER);
			$select->join(array('CUL' => new TableIdentifier('contract_user_links', $database)),
					"CUL.contract_id = C.id",
					array(
					),
					Select::JOIN_LEFT);
			$select->join(array('LVC' => new TableIdentifier('labview_contracts', $gem_database)),
					"C.id = LVC.contract_id",
					array(),
					Select::JOIN_LEFT);
			$select->join(array('CS' => new TableIdentifier('contract_statuses', $database)),
					"C.status_id = CS.id",
					array(
						'status' => 'name',
					),
					Select::JOIN_INNER);

			$columns = array(
				'month_name' => 'CM.name',
				'status' => 'CS.name'
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array("{$table}.id ASC"), $columns)
				 ->applyRange($select, $range);
			$select->group(array("{$table}.id"));
			$select->having($having);
				 
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
	
	public function filterRollup($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		$gem_database = $this->gem_database;

		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database, $gem_database) {
			$select->join(array('CM' => new TableIdentifier('calendar_months', $database)),
					"CM.id = {$table}.month",
					array(
						'month_name' => 'name',
					),
					Select::JOIN_INNER);
			
			$select->group(new Expression("{$table}.month, {$table}.year"));

			//all payment_run_rates related columns
			$columns = array(
				'month',
				'year',
				'amount' => new Expression('SUM(amount)')
			);
			$select->columns($columns);
			
			//additional columns from joined tables
			$columns['month_name'] = 'CM.name';
			
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array("{$table}.year ASC", "{$table}.month ASC"), $columns)
				 ->applyRange($select, $range);
				 
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
}
?>