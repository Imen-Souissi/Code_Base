<?php
namespace Api\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Log\Logger;

class IndexController extends AbstractActionController {
	public function indexAction() {
		return new ViewModel();
	}
}
?>