<?php
namespace Ticket\Controller\Script;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Log\Logger;
use Zend\View\Model\ConsoleModel;

use Ticket\Footprints\Cache;

class FootprintsController extends AbstractActionController {
	public function importAction() {
		$sm = $this->getServiceLocator();
		
		$logger = $sm->get('console_logger');
		$config = $sm->get('Config');
		$db = $sm->get('db');
		
		$result = new ConsoleModel();
		
		// parameters
		$start = $this->params()->fromRoute('start');
		$end = $this->params()->fromRoute('end');
		$ticket = $this->params()->fromRoute('ticket');
		
		$round = intval($this->params()->fromRoute('round'));
		$round = (empty($round)) ? 0 : $round;
		
		$perround = intval($this->params()->fromRoute('perround'));
		$perround = (empty($perround)) ? 100 : $perround;
		
		if(!empty($start)) {
			$ts = strtotime($start);
			if($ts !== false) {
				if(preg_match("/^[0-9]{4}\-[0-9]{2}\-[0-9]{2}$/", $start)) {
					$start = date('Y-m-d 00:00:00', $ts);
				} else {
					$start = date('Y-m-d H:i:s', $ts);
				}
			} else {
				throw new \Exception("invalid start given");
			}
		}
		
		if(!empty($end)) {
			$ts = strtotime($end);
			if($ts !== false) {
				if(preg_match("/^[0-9]{4}\-[0-9]{2}\-[0-9]{2}$/", $end)) {
					$end = date('Y-m-d 00:00:00', $ts);
				} else {
					$end = date('Y-m-d H:i:s', $ts);
				}
			} else {
				throw new \Exception("invalid end given");
			}
		}
		
		$logger->log(Logger::INFO, "starting footprints import");
		$cache = Cache::factory($sm);
		$cache->setLogger($logger);
		
		try {
			$where = array();
			
			if(!empty($start)) {
				$where[] = "M.MRUPDATEDATE >= '{$start}'";
			}
			if(!empty($end)) {
				$where[] = "M.MRUPDATEDATE <= '{$end}'";
			}
			if(!empty($ticket)) {
				$where[] = "M.MRID = '{$ticket}'";
			}
			
			$totalrounds = 0;
			$total = 0;
			
			$total = $cache->total($where);
			$totalrounds = ceil($total / $perround);
			
			if($totalrounds == 0) {
				$logger->log(Logger::INFO, "unable to determine total rounds to process");
			} else {
				$logger->log(Logger::INFO, "found {$total} tickets to process, dividing into {$totalrounds} rounds");
				
				for($currentround = $round; $currentround < $totalrounds; $currentround++) {
					$rowstart = $currentround * $perround;					
					$cache->cacheTickets($where, $rowstart, $perround);
				}
				
				$logger->log(Logger::INFO, "successfully imported footprints");
			}
		} catch(\Exception $e) {
			$p = $e->getPrevious();
			if($p) {
				$e = $p;
			}
			
			$result->setResult($e->getMessage() . "\n" . $e->getTraceAsString());
			$result->setErrorLevel(1);
		}
		
		$logger->log(Logger::INFO, "finished footprints import");
		
		return $result;
	}
}

?>