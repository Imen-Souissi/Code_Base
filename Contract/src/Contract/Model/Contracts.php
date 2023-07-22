<?php
namespace Contract\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Having;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\TableIdentifier;

use Els\Mvc\Model\DbModel;
use Els\Mvc\Model\DbModelFactoryInterface;

use Zend\ServiceManager\ServiceLocatorInterface;

class Contracts extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = 'id';
	protected $gem_database;
	protected $hr_database;

	public function __construct(Adapter $dbh, $database = 'contract', $gem_database = 'gem', $hr_database = 'hr') {
		$this->gem_database = $gem_database;
		$this->hr_database = $hr_database;
		parent::__construct($dbh, new TableIdentifier('contracts', $database));
	}

	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];

		return new self($dbh, $databases['contract'], $databases['gem'], $databases['hr']);
	}


	public function insert($data) {
		return $this->_insert(array(
			'type_id' => $data['type_id'],
			'units' => $data['units'],
			'status_id' => $data['status_id'],
      'vendor_id' => $data['vendor_id'],
			'contract_number' => $data['contract_number'],
			'account_number' => $data['account_number'],
			'pr' => $data['pr'],
			'po' => $data['po'],
			'account_number' => $data['account_number'],
			'ppd_category' => $data['ppd_category'],
			'department_id' => $data['department_id'],
			'department' => $data['department'],
			'requestor_id' => $data['requestor_id'],
			'project' => $data['project'],
			'description' => $data['description'],
			'notes' => $data['notes'],
			'payment_schedule_id' => $data['payment_schedule_id'],
			'notification_schedule_id' => $data['notification_schedule_id'],
			'start_date' => $data['start_date'],
			'end_date' => $data['end_date'],
			'previous_payment_id' => $data['previous_payment_id'],
			'next_payment_id' => $data['next_payment_id'],
			'created_by_id' => $data['created_by_id'],
			'ctime' => new Expression("NOW()"),
			'etime' => new Expression("NOW()"),
			'total_amount' => $data['total_amount']
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
		$gem_database = $this->gem_database;
		$hr_database = $this->hr_database;

		$need_labview_id = $this->usingFilterField($filter, array('labview_id'));

		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database, $gem_database, $hr_database, $need_labview_id) {
			$select->join(array('S' => new TableIdentifier('contract_statuses', $database)),
					"{$table}.status_id = S.id",
					array(
						'status' => 'name',
					),
					Select::JOIN_INNER);
			$select->join(array('T' => new TableIdentifier('contract_types', $database)),
					"{$table}.type_id = T.id",
					array(
						'type' => 'name',
					),
					Select::JOIN_INNER);
			$select->join(array('PS' => new TableIdentifier('payment_schedules', $database)),
					"{$table}.payment_schedule_id = PS.id",
					array(
						'payment_schedule' => 'name',
						'next_payment_months'
					),
					Select::JOIN_INNER);
			$select->join(array('NS' => new TableIdentifier('notification_schedules', $database)),
					"{$table}.notification_schedule_id = NS.id",
					array(
						'notification_schedule' => 'name',
					),
					Select::JOIN_LEFT);
			$select->join(array('V' => new TableIdentifier('vendors', $database)),
					"{$table}.vendor_id = V.id",
					array(
						'vendor' => 'name',
						'vendor_logo' => 'logo'
					),
					Select::JOIN_INNER);
			$select->join(array('P1' => new TableIdentifier('payments', $database)),
					"P1.id = {$table}.previous_payment_id",
					array(
						'previous_payment_date' => 'payment_date',
						'previous_payment_amount' => 'amount',
					),
					Select::JOIN_LEFT);
			$select->join(array('P2' => new TableIdentifier('payments', $database)),
					"P2.id = {$table}.next_payment_id",
					array(
						'next_payment_date' => 'payment_date',
						'next_payment_amount' => 'amount',
					),
					Select::JOIN_LEFT);
			$select->join(array('PT2' => new TableIdentifier('payment_types', $database)),
					"PT2.id = P2.payment_type_id",
					array(
						'next_payment_type' => 'name',
					),
					Select::JOIN_LEFT);
			$select->join(array('CQ2' => new TableIdentifier('calendar_quarters', $database)),
					"P2.payment_date BETWEEN CQ2.start_date AND CQ2.end_date",
					array(
						'next_payment_fiscal_year' => 'fiscal_year',
						'next_payment_fiscal_quarter' => 'fiscal_quarter'
					),
					Select::JOIN_LEFT);
			$select->join(array('U' => new TableIdentifier('hr_users', $hr_database)),
					"{$table}.requestor_id = U.id",
					array(
						'requestor_name' => 'display_name',
					),
					Select::JOIN_LEFT);

			if($need_labview_id) {
				// these are the labviews associated to this contract
				$select->join(array('LVC' => new TableIdentifier('labview_contracts', $gem_database)),
						"{$table}.id = LVC.contract_id",
						array(),
						Select::JOIN_LEFT);
			}

			$select->group("{$table}.id");

			$columns = array(
				'status' => 'S.name',
				'type' => 'T.name',
				'vendor' => 'V.name',
				'next_payment_date' => 'P2.payment_date',
				'next_payment_amount' => 'P2.amount',
				'next_payment_fiscal_year' => 'CQ2.fiscal_year',
				'next_payment_fiscal_quarter' => 'CQ2.fiscal_quarter',
				'labview_id' => 'LVC.labview_id'
			);

			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array("{$table}.id ASC"), $columns)
				 ->applyRange($select, $range);

			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});

		$total = $this->getCalcFoundRows($result, $range);

		return $result;
	}

	public function filterMore($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		$gem_database = $this->gem_database;
		$hr_database = $this->hr_database;

		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database, $gem_database, $hr_database) {
			if(empty($filter['access'])) {
				$filter['access'] = new Expression("1");
			}

			$select->columns(array(
				'*',
				'devices_infodots' => new Expression("GROUP_CONCAT(A.identifier)"),
				'devices_serials' => new Expression("GROUP_CONCAT(A.serial)"),
				'has_access' => $filter['access']
			));

			unset($filter['access']);

			$select->join(array('S' => new TableIdentifier('contract_statuses', $database)),
					"{$table}.status_id = S.id",
					array(
						'status' => 'name',
					),
					Select::JOIN_INNER);
			$select->join(array('T' => new TableIdentifier('contract_types', $database)),
					"{$table}.type_id = T.id",
					array(
						'type' => 'name',
					),
					Select::JOIN_INNER);
			$select->join(array('PS' => new TableIdentifier('payment_schedules', $database)),
					"{$table}.payment_schedule_id = PS.id",
					array(
						'payment_schedule' => 'name',
						'next_payment_months'
					),
					Select::JOIN_INNER);
			$select->join(array('NS' => new TableIdentifier('notification_schedules', $database)),
					"{$table}.notification_schedule_id = NS.id",
					array(
						'notification_schedule' => 'name',
					),
					Select::JOIN_LEFT);
			$select->join(array('V' => new TableIdentifier('vendors', $database)),
					"{$table}.vendor_id = V.id",
					array(
						'vendor' => 'name',
						'vendor_logo' => 'logo'
					),
					Select::JOIN_INNER);
			$select->join(array('P1' => new TableIdentifier('payments', $database)),
					"P1.id = {$table}.previous_payment_id",
					array(
						'previous_payment_date' => 'payment_date',
						'previous_payment_amount' => 'amount',
					),
					Select::JOIN_LEFT);
			$select->join(array('P2' => new TableIdentifier('payments', $database)),
					"P2.id = {$table}.next_payment_id",
					array(
						'next_payment_date' => 'payment_date',
						'next_payment_amount' => 'amount',
					),
					Select::JOIN_LEFT);
			$select->join(array('PT2' => new TableIdentifier('payment_types', $database)),
					"PT2.id = P2.payment_type_id",
					array(
						'next_payment_type' => 'name',
					),
					Select::JOIN_LEFT);
			$select->join(array('CQ2' => new TableIdentifier('calendar_quarters', $database)),
					"P2.payment_date BETWEEN CQ2.start_date AND CQ2.end_date",
					array(
						'next_payment_fiscal_year' => 'fiscal_year',
						'next_payment_fiscal_quarter' => 'fiscal_quarter'
					),
					Select::JOIN_LEFT);
			$select->join(array('CD' => new TableIdentifier('contract_devices', $database)),
					"{$table}.id = CD.contract_id",
					array(
					),
					Select::JOIN_LEFT);
			$select->join(array('A' => new TableIdentifier('assets', $gem_database)),
					"CD.device_id = A.id",
					array(
						'infodot' => 'identifier',
						'serial' => 'serial'
					),
					Select::JOIN_LEFT);
			$select->join(array('CUL' => new TableIdentifier('contract_user_links', $database)),
					"CUL.contract_id = {$table}.id",
					array(
					),
					Select::JOIN_LEFT);
			$select->join(array('LVC' => new TableIdentifier('labview_contracts', $gem_database)),
					"{$table}.id = LVC.contract_id",
					array(),
					Select::JOIN_LEFT);
			$select->join(array('U' => new TableIdentifier('hr_users', $hr_database)),
					"{$table}.requestor_id = U.id",
					array(
							'requestor_name' => 'display_name',
					),
					Select::JOIN_LEFT);

			$select->group("{$table}.id");

			$columns = array(
				'status' => 'S.name',
				'type' => 'T.name',
				'vendor' => 'V.name',
				'next_payment_date' => 'P2.payment_date',
				'next_payment_amount' => 'P2.amount',
				'next_payment_fiscal_year' => 'CQ2.fiscal_year',
				'next_payment_fiscal_quarter' => 'CQ2.fiscal_quarter',
				'labview_id' => 'LVC.labview_id'
			);

			$having_columns = array(
				'devices_infodots' => "GROUP_CONCAT(A.identifier)",
				'devices_serials' => "GROUP_CONCAT(A.serial)"
			);

			$where = new Where();
			$having = new Having();
			$having2 = $having->nest();

			//anything that needs to be in having clause, we'll address here,
			//otherwise we'll let it filter like normal
			foreach($filter AS $key=>$val) {
				if(strpos($key,"|") !== false) {
					$fields = explode("|", $key);
					$having_column_found = 0;

					foreach($having_columns AS $h_key=>$h_val) {
						if(in_array($h_key, $fields)) {
							$having_column_found = 1;
							break;
						}
					}

					if($having_column_found) {
						$val = str_replace("*", "%", $val);
						$any_found = 0;

						foreach($fields AS $field) {
							$field = (isset($columns[$field])) ? $columns[$field] : $field;
							$field = (isset($having_columns[$field])) ? $having_columns[$field] : $field;

							$having2->or->expression("{$field} LIKE '{$val}'");

							$any_found = 1;
						}

						unset($filter[$key]);
					}
				}
			}

			if(!$any_found) {
				$having2->expression("1=1");
			}
			$having2->unnest();
			$having->and->expression("`has_access` = 1");
			$select->having($having);

			$self->applyFilter($select, $where, $filter, $columns)
				->applySort($select, $sort, array("{$table}.id ASC"), $columns)
				->applyRange($select, $range);

			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
			//exit;
		});

		$total = $this->getCalcFoundRows($result, $range);

		return $result;
	}
}
?>
