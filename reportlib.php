<?php
require_once("$CFG->libdir/formslib.php");

class report_form extends moodleform {
    //Add elements to form
    public function definition() {
        global $CFG,$DB,$COURSE;
        $mform = $this->_form;

        $mform->addElement('header','filter', get_string('reportfilter','report_discussion_metrics'));
        
        $type = array('1' => 'Individual','2'=>'Groups','3'=>'Dialogue','4'=>'Dialogue/Group');
        $mform->addElement('select', 'type', "Type", $type);
        
        $forumdata = $DB->get_records('forum',array('course'=>$COURSE->id));
        foreach($forumdata as $forum){
            $forums[$forum->id] = $forum->name;
        }
        $forums = array('0' => get_string('all')) + $forums;
        $mform->addElement('select', 'forum', get_string('forum','forum'), $forums);
        
      
        $allgroups = groups_get_all_groups($COURSE->id);
        if(count($allgroups)){
            $groupoptions = array('0'=>get_string('allgroups'));
            foreach($allgroups as $group){
                $groupoptions[$group->id] = $group->name;
            }
            $mform->addElement('select', 'group', get_string('group'), $groupoptions);
        }
        
        $mform->addElement('hidden', 'id', $COURSE->id);
        $mform->setType('id',PARAM_INT);
        
        // Open and close dates.
        $mform->addElement('date_time_selector', 'starttime', get_string('reportstart', 'report_discussion_metrics'),array('optional'=>true,'startyear' => 2000, 'stopyear' => date("Y"),'step' => 5));

        $mform->addElement('date_time_selector', 'endtime', get_string('reportend', 'report_discussion_metrics'),array('optional'=>true,'startyear' => 2000, 'stopyear' => date("Y"),'step' => 5));
        /*
        $mform->addElement('header','normalization', "Normalization");
        $mform->setExpanded('normalization', false);
        $mform->addElement('checkbox', 'usernormlization', "Use normlization");
        
        $attributes=array('size'=>'5');
        $mform->addElement('text', 'depth', "Depth", $attributes);
        $mform->setType('depth',PARAM_INT);
        $mform->addRule('depth', get_string('error'), 'numeric');
        */
        $perpage = array('0' => 'All','10'=>'10','20'=>'20','30'=>'30','50'=>'50','100'=>'100');
        $mform->addElement('select', 'pagesize', "Reports per page", $perpage);
        
        //Seedを含むか
        //$mform->addElement('checkbox','containseed','Contains seed post');
        
        $mform->closeHeaderBefore('changefilter');
        $mform->addElement('submit', 'changefilter', get_string('showreport','report_discussion_metrics'));
    }
}

function forum_report_sort($sortby){
    return function($a,$b) use ($sortby){
        foreach($sortby as $key=>$order){
            if(strpos($key,"name")!==FALSE){
                if($order == 4){
                    $cmp = strcmp($a->$key,$b->$key);
                }else{
                    $cmp = strcmp($b->$key,$a->$key);
                }
            }else{
                if($order == 4){
                    return ($a->$key < $b->$key) ? -1 : 1;
                }else{
                    return ($a->$key > $b->$key) ? -1 : 1;
                }
            }
            break;
        }
        return $cmp;
    };
}

function get_mulutimedia_num($text) {
    global $CFG, $PAGE;

    if (!is_string($text) or empty($text)) {
        // non string data can not be filtered anyway
        return 0;
    }

    if (stripos($text, '</a>') === false && stripos($text, '</video>') === false && stripos($text, '</audio>') === false && (stripos($text, '<img') === false)) {
        // Performance shortcut - if there are no </a>, </video> or </audio> tags, nothing can match.
        return 0;
    }

    // Looking for tags.
    $matches = preg_split('/(<[^>]*>)/i', $text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
    $count = new stdClass;
    $count->num = 0;
    $count->img = 0;
    $count->video = 0;
    $count->audio = 0;
    $count->link = 0;
    if (!$matches) {
        return 0;
    }else{
              // Regex to find media extensions in an <a> tag.
      $embedmarkers = core_media_manager::instance()->get_embeddable_markers();
      $re = '~<a\s[^>]*href="([^"]*(?:' .  $embedmarkers . ')[^"]*)"[^>]*>([^>]*)</a>~is';
      $tagname = '';
        foreach ($matches as $idx => $tag) {
          if (preg_match('/<(a|img|video|audio)\s[^>]*/', $tag, $tagmatches)){
            $tagname = strtolower($tagmatches[1]);
            if($tagname === "a" && preg_match($re,$tag)){
              $count->num++;
                $count->link++;
            }else{
                if($tagname == "img"){
                    $count->img++;
                }elseif($tagname == "video"){
                    $count->video++;
                }elseif($tagname == "audio"){
                    $count->audio++;
                }
              $count->num++;
            }
          }
        }
    }
    return $count;
}

function second2days($seconds) {

    $days = floor($seconds/86400);
    $hours = floor($seconds / 3600) % 24;
    $minutes = floor(($seconds / 60) % 60);
    $seconds = $seconds % 60;

    $dhms = sprintf("%ddays %02d:%02d:%02d", $days,$hours, $minutes, $seconds);

    return $dhms;

}

function discussion_metrics_format_time($totalsecs, $str = null) {

    $totalsecs = abs($totalsecs);

    if (!$str) {
        // Create the str structure the slow way.
        $str = new stdClass();
        $str->day   = get_string('day');
        $str->days  = get_string('days');
        $str->hour  = get_string('hour');
        $str->hours = get_string('hours');
        $str->min   = get_string('min');
        $str->mins  = get_string('mins');
        $str->sec   = get_string('sec');
        $str->secs  = get_string('secs');
        $str->year  = get_string('year');
        $str->years = get_string('years');
    }

    $years     = floor($totalsecs/YEARSECS);
    $remainder = $totalsecs - ($years*YEARSECS);
    $days      = floor($remainder/DAYSECS);
    $remainder = $totalsecs - ($days*DAYSECS);
    $hours     = floor($remainder/HOURSECS);
    $remainder = $remainder - ($hours*HOURSECS);
    $mins      = floor($remainder/MINSECS);
    $secs      = $remainder - ($mins*MINSECS);

    $ss = ($secs == 1)  ? $str->sec  : $str->secs;
    $sm = ($mins == 1)  ? $str->min  : $str->mins;
    $sh = ($hours == 1) ? $str->hour : $str->hours;
    $sd = ($days == 1)  ? $str->day  : $str->days;
    $sy = ($years == 1)  ? $str->year  : $str->years;

    $oyears = '';
    $odays = '';
    $ohours = '';
    $omins = '';
    $osecs = '';

    if ($years) {
        $oyears  = $years .' '. $sy;
    }
    if ($days) {
        $odays  = $days .' '. $sd;
    }
    if ($hours) {
        $ohours = $hours .' '. $sh;
    }
    if ($mins) {
        $omins  = $mins .' '. $sm;
    }
    if ($secs) {
        $osecs  = $secs .' '. $ss;
    }
    
    if ($years) {
        return trim($oyears .' '. $odays.' '. $ohours.' '. $omins.' '. $osecs);
    }
    if ($days) {
        return trim($odays .' '. $ohours.' '. $omins.' '. $osecs);
    }
    if ($hours) {
        return trim($ohours .' '. $omins.' '. $osecs);
    }
    if ($mins) {
        return trim($omins .' '. $osecs);
    }
    if ($secs) {
        return $osecs;
    }
    return "-";
}
