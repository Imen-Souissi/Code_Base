<?php
namespace Auth\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Session\Container as SessionContainer;
use Zend\Authentication\Storage\Session;

use Els\Sso\User\InfoLoader;

class Sso extends AbstractPlugin {
	protected $field;
	protected $content_field;
	protected $session;
	protected $loader;
	
    public function __construct($field, $content_field, SessionContainer $session, InfoLoader $loader) {
		$this->field = (empty($field)) ? 'permission_server' : $field;
		$this->content_field = (empty($content_field)) ? 'sso' : $content_field;
		
		$this->session = $session;
		$this->loader = $loader;
    }
	
    public function __invoke() {
        return $this;
    }
	
	public function getPermissionServer() {
		return $this->session->{$this->field};
	}
	
	public function setPermissionServer($server = false) {
		$this->session->{$this->field} = ($server !== false) ? $server : $_SERVER['SERVER_NAME'];
		$content = $this->session->{$this->content_field};
		$content['previous'][$_SERVER['SERVER_NAME']] = 1;
		$this->session->{$this->content_field} = $content;
	}
	
	public function getPreviousJump() {
		return $this->session->{$this->content_field}['previous'][$_SERVER['SERVER_NAME']];
	}
	
	public function isJump() {
		$server = $this->getPermissionServer();
		if(empty($server)) {
			// since there is no permission server set we will assume this is a jump
			return true;
		} else {
			// if the server does not equal our current server address, then this is a jump
			return $server != $_SERVER['SERVER_NAME'];
		}
	}
	
	public function isFirstJump() {
		if($this->getPreviousJump() == 1) {
			return false;
		} else {
			return true;
		}
	}
	
	public function markSuccessJump() {
		$this->setPermissionServer();
	}
	
	public function loadUserInfo($user_id) {
		if($this->loader->load($user_id)) {		
			return array(
				'user_id' => $this->loader->getUserId(),
				'username' => $this->loader->getUsername(),
				'first_name' => $this->loader->getFirstName(),
				'last_name' => $this->loader->getLastName(),
				'full_name' => $this->loader->getFullName(),
				'display_name' => $this->loader->getDisplayName(),
				'email' => $this->loader->getEmail(),
				'phone' => $this->loader->getPhone(),
				'department_number' => $this->loader->getDepartmentNumber(),
				'department' => $this->loader->getDepartment(),
				'groups' => $this->loader->getGroups(),
				'picture' => $this->loader->getPicture()
			);
		} else {
			return false;
		}
	}
}
?>