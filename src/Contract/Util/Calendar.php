<?php
namespace Contract\Util;

use Contract\Model\CalendarQuarters;

class Calendar {
	protected $sm;
	
	public function __construct($sm) {
		$this->sm = $sm;
	}
	
	public function addNextQuarter() {
		$sm = $this->sm;
	
		$calendar_quarters_mdl = CalendarQuarters::factory($sm);
	
		$calendar = $calendar_quarters_mdl->filter(array(), array(
			'start' => 0,
			'end' => 0
		), array(
			'end_date DESC'
		), $calendar_total);
	
		$calendar = $calendar->toArray();
	
		//get last calendar entry
		$start_date = $calendar[0]['start_date'];
		$end_date = $calendar[0]['end_date'];
		$fiscal_year = $calendar[0]['fiscal_year'];
		$fiscal_quarter = $calendar[0]['fiscal_quarter'];
	
		$fiscal_quarter_number = substr($fiscal_quarter, -1);
	
		$start_date = new \DateTime($start_date);
		$end_date = new \DateTime($end_date);
		$interval = new \DateInterval('P13W');
	
		$start_date->add($interval);
		$end_date->add($interval);
		if($fiscal_quarter_number==4) {
			$fiscal_quarter_number = 1;
			$fiscal_year++;
		}
		else {
			$fiscal_quarter_number++;
		}
	
		$quarter_id = $calendar_quarters_mdl->insert(
			$start_date->format('Y-m-d'),
			$end_date->format('Y-m-d'),
			$fiscal_year,
			'Q'.$fiscal_quarter_number
		);
		
		return array(
			'id' => $quarter_id,
			'start_date' => $start_date->format('Y-m-d'),
			'end_date' => $end_date->format('Y-m-d'),
			'fiscal_year' => $fiscal_year,
			'fiscal_quarter' => 'Q'.$fiscal_quarter_number
		);
	}
	
	/**
	 * Check to see if we have a quarter for this date. If not, we'll attempt to add it.
	 * @param date $date
	 */
	public function checkQuarter($date) {
		$max_adds = 20;
		$sm = $this->sm;
		
		$calendar_quarters_mdl = CalendarQuarters::factory($sm);
		
		$calendar = $calendar_quarters_mdl->filter(array(), array(
			'start' => 0,
			'end' => 0
		), array(
			'end_date DESC'
		), $calendar_total);
		
		$calendar = $calendar->toArray();
		
		//get end_date for last calendar entry
		$end_date = $calendar[0]['end_date'];
		$i = 0;
		
		while(strtotime($date) > strtotime($end_date)) {
			$result = $this->addNextQuarter();
			if($result) {
				$end_date = $result['end_date'];
			}
			else {
				break;
			}
			
			$i++;
			
			//safety value
			if($i>=$max_adds) {
				break;
			}
		}
	}
}
?>