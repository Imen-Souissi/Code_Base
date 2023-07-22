<?php
namespace Auth\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Log\Logger;

use Els\Mvc\Controller\PublicInterface;

class PermissionErrorController extends AbstractActionController {
    public function indexAction() {
		// we need to grab the resource and action that the permission error is for		
		$view = new ViewModel(array(
			'resource' => $this->params()->fromQuery('resource'),
			'action' => $this->params()->fromQuery('action')
		));
		
		// this user is logged in, we should change the layout
		$this->layout('layout/layout');
		
		return $view;
    }
}
