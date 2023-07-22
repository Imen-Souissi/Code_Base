<?php
namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;
use Zend\Session\Container;

class Notification extends AbstractHelper {
	protected $notifications;
	protected $total;	
	
    public function __construct($notifications, $total) {
		$this->notifications = $notifications;
		$this->total = $total;
    }
	
    public function __invoke() {
        return $this;
    }
	
	public function getNotifications() {
		return $this->notifications;
	}
	
	public function getTotal() {
		return $this->total;
	}
	
	public function hasMore() {
		if(count($this->notifications) < $this->total) {
			return true;
		}
		
		return false;
	}
}
?>