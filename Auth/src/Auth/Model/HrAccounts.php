<?php
namespace Auth\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\TableIdentifier;

use Hr\Model\HrAccounts as HrHrAcounts;
use Zend\ServiceManager\ServiceLocatorInterface;

class HrAccounts extends HrHrAcounts {
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
}
?>