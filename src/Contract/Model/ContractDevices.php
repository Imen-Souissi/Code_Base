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

class ContractDevices extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = 'id';
	protected $gem_database;
	
	public function __construct(Adapter $dbh, $database = 'contract', $gem_database = 'gem') {
		$this->gem_database = $gem_database;
		parent::__construct($dbh, new TableIdentifier('contract_devices', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['contract'], $databases['gem']);
	}
	
	public function insert(
        $contract_id,
        $device_id,
		$notes
    ) {
		return $this->_insert(array(
            'contract_id' => $contract_id,
            'device_id' => $device_id,
			'notes' => $notes
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
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database, $gem_database) {
			$select->join(array('A' => new TableIdentifier('assets', $gem_database)),
					"{$table}.device_id = A.id",
					array(
							'infodot' => 'identifier',
							'serial' => 'serial',
							'asset' => 'asset'
					),
					Select::JOIN_LEFT);
			$select->join(array('D' => new TableIdentifier('asset_devices', $gem_database)),
					"A.id = D.id",
					array(
					),
					Select::JOIN_LEFT);
			// this is the rack this device is in
			$select->join(array('R' => new TableIdentifier('assets', $gem_database)),
					"D.rack_id = R.id",
					array(
							'rack' => 'name'
					),
					Select::JOIN_LEFT);
			// this is the lab this device is in
			$select->join(array('L' => new TableIdentifier('assets', $gem_database)),
					"D.lab_id = L.id",
					array(
							'lab' => 'name'
					),
					Select::JOIN_LEFT);
			// this is the site this device is in
			$select->join(array('S' => new TableIdentifier('assets', $gem_database)),
					"D.site_id = S.id",
					array(
							'site' => 'name'
					),
					Select::JOIN_LEFT);
			$select->join(array('MM' => new TableIdentifier('manufacturer_models', $gem_database)),
					"A.model_id = MM.id",
					array(
							'model' => 'name'
					),
					Select::JOIN_LEFT);
			$select->join(array('M' => new TableIdentifier('manufacturers', $gem_database)),
					"MM.manufacturer_id = M.id",
					array(
							'manufacturer' => 'name'
					),
					Select::JOIN_LEFT);
			
			$columns = array(
				'infodot' => 'A.identifier',
				'serial' => 'A.serial',
				'asset' => 'A.asset',
				'rack' => 'R.name',
				'lab' => 'L.name',
				'site' => 'S.name',
				'manufacturer' => 'M.name',
				'model' => 'MM.name'
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
}
?>