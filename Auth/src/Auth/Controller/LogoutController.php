<?php
namespace Auth\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Log\Logger;

class LogoutController extends AbstractActionController {
    public function indexAction() {
		$this->authentication()->clearAuthentication();
		
		// should we clear up all session data as well?
		$session = $this->getServiceLocator()->get('session');
		$session->getManager()->destroy();
		
		$this->authentication()->redirectToLoginPage();
    }
}
