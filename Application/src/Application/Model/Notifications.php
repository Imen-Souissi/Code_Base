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

class Notifications extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = 'id';
	protected $hr_database;
	
	const DANGER = 5;
	const WARNING = 4;
	const PRIMARY = 3;
	const SUCCESS = 2;
	const DEFAULTED = 1;
	
	public function __construct(Adapter $dbh, $database = 'app', $hr_database = 'hr') {
		$this->hr_database = $hr_database;
		parent::__construct($dbh, new TableIdentifier('notifications', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['app'], $databases['hr']);
	}
	
	public function insert(
        $title,
		$level,
		$url,
		$icon,
        $from_user_id,
        $to_user_id,
        $seen = 0,
        $seen_on = null,
        $emailed = 0,
        $emailed_on = null,
		$email_template = null,
		$email_params = '',
		$triggered_on = null,
		$ctime = null,
		$etime = null
    ) {
		$null = new Expression("NULL");
		$now = new Expression("NOW()");
		
		$seen = (empty($seen)) ? 0 : $seen;
		$seen_on = (empty($seen_on)) ? $null : $seen_on;		
		
		$emailed = (empty($emailed)) ? 0 : $emailed;
		$emailed_on = (empty($emailed_on)) ? $null : $emailed_on;
		
		$triggered_on = (empty($triggered_on)) ? $null : $triggered_on;
		
		$ctime = (empty($ctime)) ? $now : $ctime;
		$etime = (empty($etime)) ? $now : $etime;
		
		return $this->_insert(array(
            'title' => $title,
			'level' => $level,
			'url' => $url,
			'icon' => $icon,
			'from_user_id' => $from_user_id,
			'to_user_id' => $to_user_id,
			'seen' => $seen,
			'seen_on' => $seen_on,			
			'emailed' => $emailed,
			'emailed_on' => $emailed_on,
			'email_template' => $email_template,
			'email_params' => $email_params,
			'triggered_on' => $triggered_on,
			'ctime' => $ctime,
			'etime' => $etime
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
	
	public function filterUserAndTemplate($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		$hr_database = $this->hr_database;
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database, $hr_database) {
			$select->columns(array(
				'to_user_id',
				'email_template'
			));
			
			$select->join(array('U' => new TableIdentifier('hr_users', $hr_database)),
						  "{$table}.to_user_id = U.id",
						  array(
								'email',
								'display_name'
						  ),
						  Select::JOIN_LEFT);
			
			$columns = array(
				'email' => 'U.email',
				'display_name' => 'U.display_name'
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array(), $columns)
				 ->applyRange($select, $range);
			$select->group(array("{$table}.to_user_id", "{$table}.email_template"));
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
}
?>