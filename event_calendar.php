<?php

require_once 'framework/calendar.php';
class EventCalendar
{
	
function setAttribute ($a, $v) 
{
		
$this->calendar->setAttribute ($a, $v);

}

	
function EventCalendar (&$event_view_dispenser) 
{
		

$this->calendar = new MonthBox ();

$this->event_getter =& $event_view_dispenser;
}

function setMonthAndYear ($month, $year) 
{
		
if (!is_numeric($month)) 
{
			
$month = date ("n", validateDate ($month));
}

$dt = validateDate ("{$month}/1/{$year}");

$this->time = $dt;

list ($first_day, $days_in_month, $month, $year) = explode ("|", date ("l|t|F|Y", $dt));
 
$this->calendar->setDaysInMonth ($days_in_month);

$this->calendar->setFirstDayInMonth ($first_day);

$this->title = "$month $year";
	
}

function addActivityOnDay ($activity, $day)
 {
		
$this->calendar->addMiddleOnDay ($this->event_getter->get ($activity), $day);
	
}

function addActivity ($activity) 
{

static $eoarr = array ();

foreach ($activity as $event) 

{

$ev = $this->event_getter->get($event);

$start = $event->getStart();

$end = $event->getEnd();

$st = validateDate ($start->dateTime);

$et = validateDate ($end->dateTime);



$start_day = date ('j', $st);

$end_day   = date ('j', $et);

$my = date ('n|Y', $this->time);

if(date('m', $et)< date('m', $this->time))
{
				
continue;

}
			
if(date('m', $st) > date('m', $this->time))
{
				
continue;

}

if (date ('n', $st) < date ('n', $et)) 
{
				
if (date ('n|Y', $st) == $my) 
{
	
$end_day   = date ('t', $st);

} 
else 
{
					
$start_day = 1;
	
}
			
}
	
$styles = $ev->getAttribute ('class');

for ($i = $start_day; $i <= $start_day; $i++) 
{
	
if (isset($eoarr[$i]) && $eoarr[$i]) 
{
	
$ev->setAttribute ('class', "{$styles} even_link");

} 
else 
{
					
$ev->setAttribute ('class', "{$styles} odd_link");

$eoarr[$i] = (!(isset($eoarr[$i]) && $eoarr[$i]));

$this->calendar->addMiddleOnDay ($ev, $i);
			
}
}
	
}

	
function Render () 
{
		
if (validateDate (date ('m/1/Y')) === $this->time) 
{
			
$this->calendar->addClassByDay ('today',date('d', validateDate ('today')));
		
}
		
return $this->calendar->Render ();
	
}

	
function getTitle ()
{
		
return $this->title;
	
}

}


?>
