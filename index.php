<?php

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir . '/tablelib.php');
require_once('reportlib.php');

$startnow = optional_param('startnow',0, PARAM_INT);
$forumid = optional_param('forum',0, PARAM_INT);
$courseid = required_param('id', PARAM_INT);
$groupid = optional_param('group', 0, PARAM_INT);
$type = optional_param('type', 0, PARAM_INT);
$countryid = optional_param('country', '', PARAM_RAW);
$start = optional_param('start', '', PARAM_RAW);
$end = optional_param('end', '', PARAM_RAW);
$tsort = optional_param('tsort', 0, PARAM_RAW);
$treset = optional_param('treset', 0, PARAM_RAW);
$page = optional_param('page', 0, PARAM_RAW);
$pagesize = optional_param('pagesize', 0, PARAM_RAW);
if(strpos($tsort,'fullname')!==FALSE){
    $orderbyname = $tsort;
}else{
    $orderbyname = '';
}
$params['id'] = $courseid;
$course = $DB->get_record('course',array('id'=>$courseid));

require_course_login($course);
$coursecontext = context_course::instance($course->id);

require_capability('report/discussion_metrics:view', $coursecontext, NULL, true, 'noviewdiscussionspermission', 'forum');


if($forumid){
    $params['forum'] = $forumid;
    $forum = $DB->get_record('forum',array('id'=>$forumid));
    $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);
    $modcontext = context_module::instance($cm->id);
    $PAGE->set_title("$course->shortname: $forum->name");
    $PAGE->navbar->add($forum->name);
}

$countries = get_string_manager()->get_list_of_countries();

$mform = new report_form();
$fromform = $mform->get_data();
$paramstr = '?course='.$course->id.'&forum='.$forumid;

if($groupid){
    $params['group'] = $groupid;
    $groupfilter = $groupid;;
    $paramstr .= '&group='.$groupfilter;
    $groupname = groups_get_all_groups($course->id)[$groupid]->name;
/*
}elseif(isset($fromform->group)){
    $groupfilter = $fromform->group;
    $paramstr .= '&group='.$groupfilter;
    $params['group'] = $groupfilter;
    echo $groupfilter
    $groupname = groups_get_all_groups($course->id)[$groupfilter]->name;
*/
}else{
    $groupfilter = 0;
    $groupname = "";
}
if($countryid){
    $params['country'] = $countryid;
    $countryfilter = $countryid;
    $paramstr .= '&country='.$countryfilter;
}elseif(isset($fromform->country)){
    $countryfilter = $fromform->country;
    $paramstr .= '&country='.$countryfilter;
    $params['country'] = $countryfilter;
}else{
    $countryfilter = 0;
}
if(isset($fromform->starttime)){
    $starttime = $fromform->starttime;
    $params['start'] = $starttime;
    $paramstr .= '&start='.$starttime;
}elseif($start){
    $starttime = $start;
    $paramstr .= '&start='.$starttime;
    $params['start'] = $starttime;
}else{
    $starttime = 0;
}
if(isset($fromform->endtime)){
    $endtime = $fromform->endtime;
    $params['end'] = $endtime;
    $paramstr .= '&end='.$endtime;
}elseif($end){
    $endtime = $end;
    $paramstr .= '&end='.$endtime;
    $params['end'] = $endtime;
}else{
    $endtime = 0;
}
if(isset($type)){
    $paramstr .= '&type='.$type;
    $params['type'] = $type;
}
if(isset($pagesize)){
    $paramstr .= '&pagesize='.$pagesize;
    $params['pagesize'] = $pagesize;
}
if(isset($page)){
    $paramstr .= '&page='.$page;
    $params['page'] = $page;
}
$mform->set_data($params);

$PAGE->set_pagelayout('incourse');
/// Output the page
$PAGE->requires->jquery();
$PAGE->requires->js('/report/discussion_metrics/scripts.js');
$PAGE->requires->css('/report/discussion_metrics/styles.css');
$PAGE->set_url($CFG->wwwroot.'/report/discussion_metrics/index.php',$params);
$PAGE->navbar->add('discussion_metrics');
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
$mform->display();

/*
1. リプライ時間平均
2. リプライしたスレッドのばらつき
3. ポスト数
4. リプライ数
5. 文字数
6. マルチメディア数
7. 会話した国籍
8. 会話した人数
*/

$strname = get_string('fullname');
$strfirstname = get_string('firstname');
$strlastname = get_string('lastname');
$strcounrty = get_string('country');
$strposts = get_string('posts');
$strviews = get_string('views','report_discussion_metrics');
$strreplies = get_string('replies','report_discussion_metrics');
$strwordcount = get_string('wordcount','report_discussion_metrics');
$strfp = get_string('firstpost','report_discussion_metrics');
$strlp = get_string('lastpost','report_discussion_metrics');
$strsr = get_string('sendreminder','report_discussion_metrics');
$strcl = get_string('completereport');
$strinstituion = get_string('institution');
$strgroup = get_string('group');
$strmultimedia = get_string('multimedia','report_discussion_metrics');
if($type||$tsort||$treset||$page){
    echo '<br>';
    //echo '<a href="download.php'.$paramstr.'"><button class="btn btn-primary ">'.get_string('download').'</button></a><br><br>';
    
    if($type < 3){
        if($forumid){
            $students = get_users_by_capability($modcontext, 'mod/forum:viewdiscussion','',$orderbyname);
            $discussions = $DB->get_records('forum_discussions',array('forum'=>$forum->id));
        }else{
            $students = get_users_by_capability($coursecontext, 'mod/forum:viewdiscussion','',$orderbyname);
            $discussions = $DB->get_records('forum_discussions',array('course'=>$course->id));
        }
        $discussionarray = '(';
        foreach($discussions as $discussion){
            $discussionarray .= $discussion->id.',';
        }
        $discussionarray .= '0)';
    }
    if($type == 1){
        $studentdata = new report_discussion_metrics\select\get_student_data($students,$courseid,$forumid,$discussions,$discussionarray,$groupfilter,$countryfilter,$starttime,$endtime);
        $data = $studentdata->data;
        //get_enrolled_users(context $context, $withcapability = '', $groupid = 0, $userfields = 'u.*', $orderby = '', $limitfrom = 0, $limitnum = 0)に変えること
        //$students = get_enrolled_users($coursecontext);
        //var_dump($students);
        
        
        $table = new flexible_table('forum_report_table');
        //$table->head = array($strname,$strcounrty,$strposts,$strreplies,$strwordcount,$strviews,$strfp,$strlp,$strsr,$strcl);
        //$table->define_align = array ("center","center","center","center","center","center","center","center","center","center");
        $table->define_baseurl($PAGE->url);
        $table->define_columns(array('fullname','group', 'country', 'institution','discussion','posts', 'replies','repliestoseed','Reply time','l1','l2','l3','l4','maxdepth','avedepth','wordcount', 'views','multimedia','imagenum','videonum','audionum','linknum','participants','multinational'));
        $table->define_headers(array($strname,$strgroup,$strcounrty,$strinstituion,'Discussion',$strposts,$strreplies,'Replies to seed','Reply Time(s)','#L1','#L2','#L3','#L4','Max depth','Average depth',$strwordcount,$strviews,$strmultimedia,'#image','#video','#audio','#link','Participants','Multinational'));
        $table->sortable(true);
        $table->collapsible(true);
        $table->set_attribute('class', 'admintable generaltable');
        $table->setup();
        $sortby = $table->get_sort_columns();
        if($sortby){
            $orderby = array_keys($sortby)[0];
            $ascdesc = ($sortby[$orderby] == 4) ?'ASC':'DESC';
            if(strpos($orderby,'name') !== FALSE){
                $orderbyname = $orderby.' '.$ascdesc;
            }else{
                $orderbyname = '';
            }
        }else{
            $orderbyname = '';
        }
        if($sortby && !$orderbyname){
            usort($data,forum_report_sort($sortby));
        }
        if($pagesize){
            $table->pagesize($pagesize, count($data));
            $data = array_slice($data,$page*$pagesize,$pagesize);
        }
        foreach($data as $row){
            //$table->data[] = array($row->name,$row->country,$row->posts,$row->replies,$row->wordcount,$row->views,$row->firstpost,$row->lastpost,$sendreminder,$complink);
            $trdata = array($row->fullname,$row->group,$row->country,$row->institution,$row->discussion,$row->posts,$row->replies,$row->repliestoseed,$row->replytime,$row->l1,$row->l2,$row->l3,$row->l4,$row->maxdepth,$row->avedepth,$row->wordcount,$row->views,$row->multimedia,$row->imagenum,$row->videonum,$row->audionum,$row->linknum,$row->participants,$row->multinationals);
            $table->add_data($trdata);
        }
    }elseif($type == 2){ //Goupごと
        
        $groupdata = new report_discussion_metrics\select\get_group_data($courseid,$forumid,$discussions,$discussionarray,$groupfilter,$countryfilter,$starttime,$endtime);
        $data = $groupdata->data;
        
        $table = new flexible_table('forum_report_table');
        //$table->head = array($strname,$strcounrty,$strposts,$strreplies,$strwordcount,$strviews,$strfp,$strlp,$strsr,$strcl);
        //$table->define_align = array ("center","center","center","center","center","center","center","center","center","center");
        $table->define_baseurl($PAGE->url);
        $table->define_columns(array('name','users','multinationals','repliestoseed', 'replies','repliedusers','notrepliedusers','wordcount', 'views','multimedia'));
        $table->define_headers(array($strgroup,'#member',$strcounrty,'#threads','#replies','#replied user','#not replied user',$strwordcount,$strviews,$strmultimedia));
        $table->sortable(true);
        $table->collapsible(true);
        $table->set_attribute('class', 'admintable generaltable');
        $table->setup();
        $sortby = $table->get_sort_columns();
        if($sortby && !$orderbyname){
            usort($data,forum_report_sort($sortby));
        }
        if($pagesize){
            $table->pagesize($pagesize, count($data));
            $data = array_slice($data,$page*$pagesize,$pagesize);
        }
        foreach($data as $row){
            //$table->data[] = array($row->name,$row->country,$row->posts,$row->replies,$row->wordcount,$row->views,$row->firstpost,$row->lastpost,$sendreminder,$complink);
            $trdata = array($row->name,$row->users,$row->multinationals,$row->repliestoseed,$row->replies,$row->repliedusers,$row->notrepliedusers,$row->wordcount,$row->views,$row->multimedia);
            $table->add_data($trdata);
        }
    }elseif($type == 3){ //Dialogue(discussion)の集計
        //$allpostssql = 'SELECT * FROM {forum_posts} WHERE discussion IN '.$discussionarray;
        $discussiondata = new report_discussion_metrics\select\get_discussion_data($courseid,$forumid,$groupfilter,$starttime,$endtime);
        $data = $discussiondata->data;
        //Render Table
        $table = new flexible_table('forum_report_table');
        $table->define_baseurl($PAGE->url);
        $table->define_columns(array('forumname','name','posts','bereplied','threads','maxdepth','l1','l2','l3','l4','multimedia','replytime'));
        $table->define_headers(array("Forum",'Discussion','#posts','#been replied to','#threads','Max depth','#L1','#L2','#L3','#L4','#multimedia','Reply time'));
        $table->sortable(true);
        $table->collapsible(true);
        $table->set_attribute('class', 'admintable generaltable');
        $table->setup();
        $sortby = $table->get_sort_columns();
        if($sortby && !$orderbyname){
            usort($data,forum_report_sort($sortby));
        }
        if($pagesize){
            $table->pagesize($pagesize, count($data));
            $data = array_slice($data,$page*$pagesize,$pagesize);
        }
        foreach($data as $row){
            $trdata = array($row->forumname,$row->name,$row->posts,$row->bereplied,$row->threads,$row->maxdepth,$row->l1,$row->l2,$row->l3,$row->l4,$row->multimedia,$row->replytime);
            $table->add_data($trdata);
        }
    }elseif($type == 4){ //DialogueをGroupごと
        //$allpostssql = 'SELECT * FROM {forum_posts} WHERE discussion IN '.$discussionarray;
        $dialoguedata = new report_discussion_metrics\select\get_dialogue_data($courseid,$forumid,$groupfilter,$starttime,$endtime);
        $data = $dialoguedata->data;
        //Render Table
        $table = new flexible_table('forum_report_table');
        $table->define_baseurl($PAGE->url);
        $table->define_columns(array('groupname','forumname','name','posts','bereplied','threads','l1','l2','l3','l4','multimedia','replytime'));
        $table->define_headers(array('Group',"Forum",'Discussion','#post','#been replied to','#replies to seed','#L1','#L2','#L3','#L4','#multimedia','Reply time'));
        $table->sortable(true);
        $table->collapsible(true);
        $table->set_attribute('class', 'admintable generaltable');
        $table->setup();
        $sortby = $table->get_sort_columns();
        if($sortby && !$orderbyname){
            usort($data,forum_report_sort($sortby));
        }
        if($sortby && !$orderbyname){
            usort($data,forum_report_sort($sortby));
        }
        if($pagesize){
            $table->pagesize($pagesize, count($data));
            $data = array_slice($data,$page*$pagesize,$pagesize);
        }
        foreach($data as $row){
            $trdata = array($row->groupname,$row->forumname,$row->name,$row->posts,$row->bereplied,$row->threads,$row->l1,$row->l2,$row->l3,$row->l4,$row->multimedia,$row->replytime);
            $table->add_data($trdata);
        }
    }
   
        echo '<input type="hidden" name="course" id="courseid" value="'.$courseid.'">';
        if($forumid){
            echo '<input type="hidden" name="forum" id="forumid" value="'.$forumid.'">';
        }
        $table->finish_output();
}
echo $OUTPUT->footer();
