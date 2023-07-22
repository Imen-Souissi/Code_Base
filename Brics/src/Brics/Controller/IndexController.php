<?php
namespace Brics\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Log\Logger;

class IndexController extends AbstractActionController {
	public function indexAction() {
		$this->layout('layout/simple');
		$this->layout()->setVariable('layout_type', 'h');
		
		return new ViewModel(array());
	}
}
?>