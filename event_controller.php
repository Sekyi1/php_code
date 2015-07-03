<?php

require_once 'framework/framework.php';

require_once 'util/mvc/controller.php';

require_once 'util/camelcase.php';

require_once 'event_factory.php';

require_once 'event_view.php';

require_once 'globals.php';

require_once 'util/mvc/controller_plugin.php';

require_once 'employees/employee_factory.php';

require_once 'employee_events/employee_event_factory.php';

require_once 'total_times/total_time_factory.php';

require_once 'util/database/transactional_operation.php';



function debug_boolean ($value, $statement)
 {
	
	xdebug_var_dump ("$statement: $value");
	
        return $value;

 }



class EventController extends Controller 
{
   
ar $css = array ();
   
var $js  = array ();

   
function getCSS () 
{
      
return $this->css;

}

   
function getJS () 
{
      
return $this->js;
   
}
   
	
function EventController () 
{
		
$this->myFactory =& EventFactory::getFactory ();

$this->registerAction   ('index');

$this->registerAction   ('edit');
     
$this->registerActionAs ('edit_new', 'StoreNew');
      
$this->registerActionAs ('Create New', 'StoreNew');
      
$this->registerAction   ('Update');
      
$this->registerAction   ('Create');
      
$this->registerAction   ('view');
      
$this->registerActionAs ('list', 'dolist');
      
$this->registerActionAs ('Make New', 'makenew');
      
$this->registerActionAs ('Delete', 'doDelete');

		
if (get_request_variable ('event_id') && get_request_variable ('action') != 'index') 
{
			
$this->event =& $this->myFactory->getEvent (
get_request_variable ('event_id'));
$this->setupTargets ();
if (!isAuthorized ('RESOURCE_EVENT_ADMIN', getLoggedInUsername ())) 
{
				
trigger_error ('IMPERSONAL');

if ($this->is_personal) 
{
					
trigger_error ('PERSONAL');

$eef =& EmployeeEventFactory::getFactory ();
$ee  = $eef->getEmployeeEventForEventId ($this->event->getId ());

if ($ee != null) 
{

if ($ee->isLocked () || getLoggedInUsername () != $ee->getNetid ()) 
{
							
$this->registerActionAs ('edit', 'view');

}

}

} 
else 
{

trigger_error ('IMPERSONAL');

redirect ('index.php');
	
return;
}

}
} 
else if (in_array (get_request_variable ('action'), array ('edit_new', 'Create New'))) 
{

		
} 
else
{
if (!isAuthorized ('RESOURCE_EVENT_ADMIN', getLoggedInUsername ()))
{
					
trigger_error ('NOT NEW / NO EVENT / INDEX -- NO AUTH');

redirect ('index.php');

return;

}

}

$this->handleAction (get_request_variable ('action'));
	
}

	
function setupTargets () 
{
		
if ($this->event->isPersonal ()) 
{
			
$repo =& Repository::getRepository ();
$app =& $repo->getValue ('application');
$app->setTitle ('Outage Editor');
$app->setName  ('Outage Editor');
$app->addTab   ('Outage Editor', null);

$mo = date ('F\%\2\0Y', validateDate ($this->event->getStartTime ()));
$this->is_personal = true;

$this->list_page = "employee_events.php?viewmode=calendar&month={$mo}";

} 
else 
{

$this->is_personal = false;
$this->list_page = "events.php";
}
	
}

	
/**************************************
	Helper routines
	**************************************/
	
function setDataFromPost () 
{
	  
$model =& $this->myFactory->getDBModel ();


foreach (array_keys($model->columns) as $var) 
{
		 
$method = 'set' .  underscore_to_camel_case ($var);
$this->event->$method (get_request_variable ($var));

}
	
}

	
/**************************************
	Action Handlers
	**************************************/
	
function Create () 
{
		
$this->event =& $this->myFactory->getNewEvent ();

$this->setDataFromPost ();

$this->event->Store();

$this->setupTargets ();

MessageWrite ("Event created");

$this->Edit ();
	
}

	
function handleOverlap () 
{
		
MessageWrite ('Overlap detected.  Data not saved.');

}

	
function handleBudgetOverrun () 
{
				
MessageWrite ("This event would put you over-budget.  If you need to make special arrangements, please contact the departmental administrator or your manager.");

}

	
function handleBudgetOverrunNew () 
{
		
unset ($this->event->event_id);
		
unset ($this->employee_event->employee_event_id);

$this->handleBudgetOverrun ();
	
}

	
function handleBudgetOverrunExisting () 
{
		
$this->event = $this->myFactory->getEvent ($this->event->getId ());

$this->handleBudgetOverrun ();
	
}

	
function validateOverlap () 
{
		
$q =<<<EOQ
	select count(1) as num_overages from over_outage_view where employee_emp_id = ?
EOQ;

$db = new dbWrapper ();

$db->Open ('vacation', $q);

$rset = $db->Read (array ($this->emp_id));

return $rset->NUM_OVERAGES[0] == 0;

}

	
function validateEventBudget () 
{
		
$ttf =& TotalTimeFactory::getFactory ();

$paf =& EmployeeEventFactory::getFactory ();

$pac = $paf->getEmployeeEventForEventId ($this->event->getId ());

$start_totals = $ttf->getRemainingTimesForUserAtTime (
$pac->getEmployeeEmpId (),$this->event->getStartTime ());

$end_totals   = $ttf->getRemainingTimesForUserAtTime (
$pac->getEmployeeEmpId (), $this->event->getEndTime ());

if ($this->event->isPersonal ()) 
{
			
$patf   =& PersonalActivityFactory::getFactory ();

$activity = $patf->getPersonalActivity ($aid = $this->event->getActivityTypeId ());

if ($activity->isBudgeted ()) 
{
				
if ($start_totals[$aid] < 0 || $end_totals[$aid] < 0) 
{
					
/// failure - Let the user know as much.

return false;

}

}

}
		
return true;
	
}

	
function Update () 
{
		
if ($this->event->isPersonal ()) 
{
	
$to = new TransactionalOperation ('vacation');

$to->registerTest (new OperationTest (array (&$this, 'validateEventBudget'),
array (&$this, 'handleBudgetOverrunExisting'),
null
));
$to->registerTest (
new OperationTest (
array (&$this, 'validateOverlap'),
array (&$this, 'handleOverlap'),
null
)
;

}

$rolled_back = false;
$start = $this->event->getStartTime ();

$end   = $this->event->getEndTime ();

$this->setDataFromPost ();

$nstart = $this->event->getStartTime ();

$nend   = $this->event->getEndTime ();


$change_range_warn = false;
		
if (validateDate($start) != validateDate($nstart) || validateDate($end) != validateDate($nend))  
{
			
$change_range_warn = true;
}

$this->event->Store();

if ($this->event->isPersonal ()) 
{
   	   
$paf =& EmployeeEventFactory::getFactory ();

$pac = $paf->getEmployeeEventForEventId ($this->event->getId ());
$this->emp_id = $pac->getEmployeeEmpId ();

switch (get_request_variable ('status')) 
{
				
case 'l': $pac->lock (); 
break;
case 'u': $pac->unlock (); 
break;

default: break;

}

$pac->Store ();
}
		
$days = $this->event->getEventDays ();

$other_days = get_request_variable ('other_value');
		
foreach (array_keys ($days) as $i) 
{
			
$coded_day = $days[$i]->getCodedDay ();

$ed = get_request_variable ('event_day');

switch ($ed[$coded_day]) 
{
				
case 'full':$days[$i]->setDecimalOutPortion (1); 
break;
				
case 'half':$days[$i]->setDecimalOutPortion (.5); 
break;
case 'other':if (is_numeric ($other_days[$coded_day])) 
{
						
if (0 < $other_days[$coded_day] && 1 > $other_day[$coded_day]) 
{

$days[$i]->setDecimalOutPortion ($other_days[$coded_day]);

} 
else 
{
							
MessageWrite ('Notice: Outage must be between 0 and 1 days (exclusive)');

}

} 
else 
{
						
MessageWrite ('Can\'t parse "other" value supplied.');

}

}
$days[$i]->Store ();
}
		
if ($this->event->isPersonal ()) 
{
	
$rolled_back = !$to->doOperation ();

} 

		
if (!$rolled_back) 
{
			
MessageWrite ("Event updated");
if ($change_range_warn) 
{
				
MessageWrite ("Warning: Date range has changed.  Please double check to ensure that the outage fraction for each day is what you intend.");

}

}

		
$this->Edit ();
	
}

	
function doDelete () 
{
		
if ($this->event->isPersonal ()) 
{
			
echo <<<EOE
Event deleted.  To return to the calender, click the calendar tab.
EOE;

} 
else 
{
			
MessageWrite ("Event deleted");

$this->index ();

}
		
$this->event->Destroy();

}

	
/**************************************
	Display Views
	**************************************/
	
function View () 
{
		
if (!$this->event) 
{
			
$this->index ();
return;
		
}

$disp = new EventView ();

$disp->setObject ($this->event);

$disp->showView ('view');

}

	
function StoreNew () 
{
		
$eef   =& EmployeeEventFactory::getFactory ();
      
$efac  =& EmployeeFactory::getFactory ();

if (
!(request_variable_set ('employee_id')) || 
!isAuthorized ('RESOURCE_EVENT_ADMIN', getLoggedInUsername ()) ||
 (null == ($emp = $efac->getEmployee (get_request_variable ('employee_id'))))) 
{

$emp = $efac->getEmployeeByNetid (getLoggedInUsername ());

}
		
$this->emp_id = $emp->getId ();
		
$atf   =& ActivityTypeFactory::getFactory ();

		
$to = new TransactionalOperation ('vacation');
		
$to->registerTest (
new OperationTest (
array (&$this, 'validateEventBudget'),
array (&$this, 'handleBudgetOverrunNew'),
null
)
);

$to->registerTest (
new OperationTest (
array (&$this, 'validateOverlap'),
array (&$this, 'handleOverlap'),
null
)
);
  
/// STARTING TRANSACTION HERE ...
		
$this->event = $this->myFactory->createNew ();
		
$this->event->setStartTime (get_request_variable ('start_time'));

		
$this->event->setEndTime (get_request_variable ('end_time'));
		
$this->event->setActivityTypeId ($aid = get_request_variable ('activity_type_id'));
		
$this->event->setNote (get_request_variable ('note'));
		
$this->event->store ();
		
$activity = $atf->getActivityType ($aid);

		
$this->employee_event = $eef->createNew ();
		
$this->employee_event->setEventEventId ($this->event->getId ());
		
$this->employee_event->setEmployeeEmpId ($emp->getId ());
		
$this->employee_event->store ();
		
$days = $this->event->getEventDays ();
		
$other_days = get_request_variable ('other_value');
		
foreach (array_keys ($days) as $i)
{
			
$coded_day = $days[$i]->getCodedDay ();
			
$ed = get_request_variable ('event_day');
			
switch ($ed[$coded_day]) 
{
				
case 'full':
$days[$i]->setDecimalOutPortion (1); 
break;
				
case 'half':
$days[$i]->setDecimalOutPortion (.5); 
break;
				
case 'other':
if (is_numeric ($other_days[$coded_day])) 
{
						
if (0 < $other_days[$coded_day] && 1 > $other_day[$coded_day]) 
{
							
$days[$i]->setDecimalOutPortion ($other_days[$coded_day]);
						
} 
else 
{
							
MessageWrite ('Notice: Outage must be between 0 and 1 days (exclusive)');

}
					
} 
else 
{
						
MessageWrite ('Can\'t parse "other" value supplied.');
	
}
			
}
			
$days[$i]->Store ();
		
}

 
/// Now we've created all our stuff and we need to see if we validate ...
		
if ($to->doOperation ()) 
{
			
MessageWrite ('Event Created');
		
} 
else 
{
			
unset ($this->event->event_id);
			
unset ($this->employee_event->employee_event_id);
		
}

		
$this->Edit ();
	
}

	
function Edit () 
{
		
if (!$this->event) 
{
			
$this->index ();
			
return;
		
}
		
$disp = new EventView ();
		
$disp->list_page = $this->list_page;
		
$disp->setObject ($this->event);
		
$disp->showView ('edit');
	
}

	
function makeNew () 
{
		
$disp = new EventView ();
		
$disp->setObject ($this->myFactory->getNewEvent ());
		
$disp->showView ('create');
	
}

	
function index () 
{
		
if ($this->is_personal) 
{
			
redirect ("{$this->list_page}");	
			
return;
		
}
		
$disp = new EventView ();
		
$disp->controller =& $this;
		
$disp->setObject (null);
		
$disp->showView ('index');
	
}

	
function doList () 
{
		
$event = $this->myFactory->getNonPersonalEvents ();
		
$disp = new EventView ();
		
if (count ($event))
{
			
$disp->setObject ($event);
			
$disp->showView ('list');
		
		
} 
else 
{
			
$disp->setObject (null);
			
$disp->showView ('empty');
		
}
	
}

}


class EventControllerPlugin extends MVCControllerPlugin 
{
	
function EventControllerPlugin () 
{
		
MVCControllerPlugin::MVCControllerPlugin ();
		
$this->controller_class = "EventController";
	
}

	
function name ()
{
		
return "Event";
	
}

	
function isAuthorized ()
{
		
/* PLEASE IMPLEMENT ME!!! */
		
return 'RESOURCE_EVENT_ADMIN';
	
}

}

?>

