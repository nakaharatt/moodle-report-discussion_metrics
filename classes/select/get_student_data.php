<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Plugin event classes are defined here.
 *
 * @package     coursereport_discussion_metrics
 * @copyright   2020 Takahiro Nakahara <nakahara@3strings.co.jp>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_discussion_metrics\select;

defined('MOODLE_INTERNAL') || die();

/**
 * The viewed event class.
 *
 * @package    coursereport_discussion_metrics
 * @copyright  2020 Takahiro Nakahara <nakahara@3strings.co.jp>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_student_data {

    public $data = array();
    
    public function __construct($students,$courseid,$forumid=NULL,$discussions,$discussionarray,$firstposts,$starttime=0,$endtime=0){
        global $DB;
        
        $countries = get_string_manager()->get_list_of_countries();
        
        /*
        foreach($discussions as $discussion){
            $firstposts[] = $discussion->firstpost;
        }
        */
        
        foreach($students as $student){
            $studentdata = new studentdata();

            //Group
            $studentgroups = groups_get_all_groups($courseid, $student->id);
            $tempgroups = array();
            $studentdata->group ="";
            foreach($studentgroups as $studentgroup){
                $tempgroups[] = $studentgroup->name;
            }
            if($tempgroups) $studentdata->group = implode(',',$tempgroups);
            /*
            $ingroups = array_keys($studentgroups);
            if($groupfilter){
                //echo $groupfilter;
                if(!in_array($groupfilter,$ingroups)){
                    continue;
                }
            }
            */
            
            $studentdata->id = $student->id;

            //Name
            $studentdata->fullname = fullname($student);

            //Countryfullname($student);
            @$studentdata->country = $countries[$student->country];

            //Instituion
            $studentdata->institution = $student->institution;

            //Discussion
            $posteddiscussions = array();
            $studentdata->posts = 0;
            $studentdata->replies = 0;
            $studentdata->discussion = 0;
            $studentdata->replytime = '-';
            $studentdata->maxdepth = 0;
            $studentdata->avedepth = 0;
            $studentdata->repliestoseed = 0;
            $studentdata->imagenum = 0;
            $studentdata->audionum = 0;
            $studentdata->videonum = 0;
            $studentdata->linknum = 0;
            $sumtime = 0;
            $depthsum = 0;
            $depths = array();
            $studentdata->wordcount = 0;
            $multimedianum = 0;
            $imgnum = 0;
            $videonum = 0;
            $audionum = 0;
            $linknum = 0;
            $levels = array(0,0,0,0);
            $studentdata->participants = 0;
            $studentdata->multinationals = 0;
            $replytimearr = array();
            $foravedepth = array();
            $allpostssql = 'SELECT * FROM {forum_posts} WHERE userid='.$student->id.' AND discussion IN '.$discussionarray;
            if($starttime){
                $allpostssql = $allpostssql.' AND created>'.$starttime;
            }
            if($endtime){
                $allpostssql = $allpostssql.' AND created<'.$endtime;
            }
            if($allposts = $DB->get_records_sql($allpostssql)){
                foreach($allposts as $post){
                    @$posteddiscussions[$post->discussion] ++; //どのディスカッションに何回返信したかを使う時が来るか？
                    if($post->parent == 0){
                        $studentdata->posts ++;
                    }elseif($post->parent > 0){
                        if(in_array($post->parent,$firstposts)){
                            $studentdata->repliestoseed++;
                        }
                        if($parentdata = $DB->get_record('forum_posts',array('id'=>$post->parent))){
                            $sumtime = $sumtime + ($post->created - $parentdata->created); //for average
                            $replytimearr[] = $post->created - $parentdata->created; //for median
                        }
                        $studentdata->replies ++;
                        
                        //Depth
                        if(!isset($depths[$post->id])){
                            $parent = $post->parent;
                            $depths[$post->id] = 1;
                            while($parent!=0){
                                if($parentpost = $DB->get_record('forum_posts',array('id'=>$parent))){
                                    if($parentpost->userid == $student->id){
                                        if(isset($depths[$parentpost->id])){
                                            unset($depths[$parentpost->id]);
                                        }
                                        $depths[$parentpost->id] = 0;
                                        $depths[$post->id]++;
                                    }
                                    $parent = $parentpost->parent;
                                    $foravedepth[$post->id] = $depths[$post->id];
                                }else{
                                    //The parent data has deleted
                                    $depths[$post->id] = 0;
                                    continue;
                                }
                            }
                            if($studentdata->maxdepth < $depths[$post->id]){
                                $studentdata->maxdepth = $depths[$post->id];
                            }
                            if($depths[$post->id]<4){
                                $levels[$depths[$post->id]-1]++;
                            }else{
                                $levels[3]++; //Over Level 4
                            }
                        }
                    }
                    
                    $wordnum = count_words($post->message);
                    $studentdata->wordcount += $wordnum;
                    if($multimediaobj = get_mulutimedia_num($post->message)){
                        $multimedianum += $multimediaobj->num;
                        $imgnum += $multimediaobj->img;
                        $videonum += $multimediaobj->video;
                        $audionum += $multimediaobj->audio;
                        $linknum += $multimediaobj->link;
                    }
                }
                if($foravedepth) $studentdata->avedepth = round(array_sum($foravedepth)/count($foravedepth),3);
                $studentdata->discussion = count($posteddiscussions);
                $studentdata->multimedia = $multimedianum;
                /*
                if($sumtime){
                    $dif = ceil($sumtime/$studentdata->replies);
                    $dif_time = gmdate("H:i:s", $dif);
                    $dif_days = (strtotime(date("Y-m-d", $dif)) - strtotime("1970-01-01")) / 86400;
                    $studentdata->replytime =  "{$dif_days}days<br>$dif_time";
                }
                */
                //Median replytime
                if($studentdata->replies ==1){
                    $studentdata->replytime = discussion_metrics_format_time($replytimearr[0]);
                }elseif($studentdata->replies ==2){
                    $studentdata->replytime = discussion_metrics_format_time(($replytimearr[1]+$replytimearr[0])/2);
                }elseif($studentdata->replies > 2){
                    sort($replytimearr);
                    $middleval = floor(($studentdata->posts)/2);
                    if($studentdata->replies % 2){
                        $studentdata->replytime = discussion_metrics_format_time($replytimearr[$middleval]);
                    }else{
                        $studentdata->replytime = discussion_metrics_format_time(($replytimearr[$middleval] + $replytimearr[$middleval+1])/2);
                    }
                }
                //if($studentdata->maxdepth) $studentdata->avedepth = $depthsum/$threads;
                //$studentdata->threads = $threads;
                //対話した相手の人数と国籍
                list($discsin,$discsparam) = $DB->get_in_or_equal(array_keys($posteddiscussions));
                $discswhere = "userid <> ? AND discussion {$discsin}";
                $dparam = ['studentid'=>$student->id];
                $dparam += $discsparam;
                if($participants = $DB->get_fieldset_select('forum_posts', 'DISTINCT userid', $discswhere,$dparam)){
                    $studentdata->participants = count($participants);
                    list($partin,$partparam) = $DB->get_in_or_equal($participants);
                    $countrywhere = "id {$partin}";
                    $countryids = $DB->get_fieldset_select('user', 'DISTINCT country', $countrywhere,$partparam);
                    $studentdata->multinationals = count($countryids);
                }
            }else{
                $studentdata->discussion = 0;
            }
            //View
            $logtable = 'logstore_standard_log';
            $eventname = '\\\\mod_forum\\\\event\\\\discussion_viewed';
            if($forumid){
                $cm = get_coursemodule_from_instance('forum', $forumid, $courseid, false, MUST_EXIST);
                $viewsql = "SELECT * FROM {logstore_standard_log} WHERE userid=$student->id AND contextinstanceid=$cm->id AND contextlevel=".CONTEXT_MODULE." AND eventname='$eventname'";
            }else{
                $views = $DB->get_records($logtable,array('userid'=>$student->id,'courseid'=>$courseid,'eventname'=>$eventname));
                $viewsql = "SELECT * FROM {logstore_standard_log} WHERE userid=$student->id AND courseid=$courseid AND eventname='$eventname'";
            }
            if($starttime){
                $viewsql = $viewsql.' AND timecreated>'.$starttime;
            }
            if($endtime){
                $viewsql = $viewsql.' AND timecreated<'.$endtime;
            }
            $views = $DB->get_records_sql($viewsql);
            $studentdata->views = count($views);

            $studentdata->multimedia = $multimedianum;
            $studentdata->imagenum = $imgnum;
            $studentdata->audionum = $audionum;
            $studentdata->videonum = $videonum;
            $studentdata->linknum = $linknum;
            $studentdata->levels = $levels;
            $studentdata->l1 = $levels[0];
            $studentdata->l2 = $levels[1];
            $studentdata->l3 = $levels[2];
            $studentdata->l4 = $levels[3];

            //First post & Last post
            $firstpostsql = 'SELECT MIN(created) FROM {forum_posts} WHERE userid='.$student->id.' AND discussion IN '.$discussionarray;
            if($allposts){

                $firstpostsql = 'SELECT MIN(created) FROM {forum_posts} WHERE userid='.$student->id.' AND discussion IN '.$discussionarray;
                if($starttime){
                    $firstpostsql = $firstpostsql.' AND created>'.$starttime;
                }
                if($endtime){
                    $firstpostsql = $firstpostsql.' AND created<'.$endtime;
                }
                $firstpost = $DB->get_record_sql($firstpostsql);
                $minstr = 'min(created)'; //
                $firstpostdate = userdate($firstpost->$minstr);
                $studentdata->firstpost = $firstpostdate;


                $lastpostsql = 'SELECT MAX(created) FROM {forum_posts} WHERE userid='.$student->id.' AND discussion IN '.$discussionarray;
                if($starttime){
                    $lastpostsql = $lastpostsql.' AND created>'.$starttime;
                }
                if($endtime){
                    $lastpostsql = $lastpostsql.' AND created<'.$endtime;
                }
                $lastpost = $DB->get_record_sql($lastpostsql);
                $maxstr = 'max(created)'; //
                $lastpostdate = userdate($lastpost->$maxstr);
                $studentdata->lastpost = $lastpostdate;
            }else{
                $studentdata->firstpost = '-';
                $studentdata->lastpost = '-';
            }
            $this->data[] = $studentdata;
        }
    }
}

class studentdata{

    public $fullname;
    public $posts;
    public $replies = 0;
    public $maxdepth = 0;
    public $avedepth = 0;
    public $threads = 0;
    public $views = 0;
    public $wordcount = 0;
    public $participants = 0;
    public $multinationals = 0;
    public $multimedia = 0;
    public $imagenum = 0;
    public $videonum = 0;
    public $audionum = 0;
    public $density = 0;
    public $replytime = 0;
    public $repliestoseed = 0;
    public $firstpost;
    public $lastpost;
    public $levels;
    public $l1;
    public $l2;
    public $l3;
    public $l4;
}
