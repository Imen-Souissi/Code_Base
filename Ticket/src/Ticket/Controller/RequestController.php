<?php
namespace Ticket\Controller;

use Zend\Console\Getopt;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Log\Logger;
use Els\Mvc\Controller\PublicInterface;
use Elstools\Model\SecurityResources;

use Ticket\Model\FootprintsTickets;
use Ticket\Model\FootprintsStatuses;

class RequestController extends AbstractActionController implements PublicInterface {

	public function indexAction() {

        return new ViewModel();
        
    }
	
}
?>        