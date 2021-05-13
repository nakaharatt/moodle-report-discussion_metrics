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
class get_group_country_data {
    
    public $data = array();
    
    public function __construct($students,$courseid,$forumid=NULL,$discussions,$discussionarray,$firstposts,$allgroups=NULL,$starttime=0,$endtime=0){
        global $DB;
        $countries = get_string_manager()->get_list_of_countries();
        if(!$allgroups){
            $allgroups = groups_get_all_groups($courseid);
        }
        foreach($allgroups as $group){
            $countryusers = array();
            $groupusers = groups_get_members($group->id, 'u.id', 'u.id ASC');
            foreach($groupusers as $guser){
                $guserdata = $DB->get_record('user',array('id'=>$guser->id));
                $countryusers[$guserdata->country][$guserdata->id] = $guserdata;
            }
            foreach($countryusers as $key=>$students){
                $groupcountrydata = new groupcountrydata;
                if($key){
                    $groupcountrydata->country = $countries[$key];
                }else{
                    $groupcountrydata->country = get_string("none");
                }
                $groupcountrydata->users = 0;
                $groupcountrydata->views = 0;
                $groupcountrydata->posts = 0;
                $groupcountrydata->replies = 0;
                $groupcountrydata->discussion = 0;
                $groupcountrydata->replytime = 0;
                $groupcountrydata->wordcount = 0;
                $groupcountrydata->participants = 0;
                $groupcountrydata->multinationals = 0;
                $groupcountrydata->multimedia = 0;
                $groupcountrydata->notrepliedusers = 0;
                $groupcountrydata->repliedusers = 0;
                $sumtime = 0;

                foreach($students as $student){
                    $studentdata = (object)"";
                    $studentdata->id = $student->id;

                    //Discussion
                    $posteddiscussions = array();
                    $studentdata->posts = 0;
                    $studentdata->replies = 0;
                    $studentdata->repliestoseed = 0;
                    $studentdata->discussion = 0;
                    $studentdata->replytime = 0;
                    $studentdata->wordcount = 0;
                    $multimedianum = 0;
                    $studentdata->participants = 0;
                    $studentdata->multinationals = 0;
                    $allpostssql = 'SELECT * FROM {forum_posts} WHERE parent>0 AND userid='.$student->id.' AND discussion IN '.$discussionarray;
                    if($starttime){
                        $allpostssql = $allpostssql.' AND created>'.$starttime;
                    }
                    if($endtime){
                        $allpostssql = $allpostssql.' AND created<'.$endtime;
                    }
                    if($allposts = $DB->get_records_sql($allpostssql)){
                        foreach($allposts as $post){
                            @$posteddiscussions[$post->discussion] ++; //どのディスカッションに何回投稿したかを使う時が来るか？
                            if($post->parent == 0){
                                $groupcountrydata->posts ++;
                            }elseif($post->parent > 0){
                                if(in_array($post->parent,$firstposts)){
                                    $groupcountrydata->repliestoseed++;
                                }
                                if($parent = $DB->get_record('forum_posts',array('id'=>$post->parent))){
                                    $sumtime = $sumtime + ($post->created - $parent->created);
                                }
                                $groupcountrydata->replies ++;
                            }
                            /*
                            //Depth
                            if(!isset($depths[$post->id])){
                                $parent = $post->parent;
                                $depths[$post->id] = 1;
                                while($parent!=0){
                                    if($parentpost = $DB->get_record('forum_posts',array('id'=>$parent))){
                                        if(in_array($parentpost->userid ,$gropuserlist)){ // in_array
                                            if(isset($depths[$parentpost->id])){
                                                unset($depths[$parentpost->id]);
                                            }
                                                $depths[$parentpost->id] = 0;

                                            $depths[$post->id]++;
                                        }
                                        $parent = $parentpost->parent;
                                    }else{
                                        //The parent data has deleted
                                        $depths[$post->id] = 0;
                                        continue;
                                    }
                                }
                                if($groupcountrydata->maxdepth < $depths[$post->id]){
                                    $groupcountrydata->maxdepth = $depths[$post->id];
                                }
                            }
                            $depths = array_filter($depths);
                            if($depths) $groupcountrydata->avedepth = round(array_sum($depths)/count($depths),3);
                            */
                            $wordnum = count_words($post->message);
                            $groupcountrydata->wordcount += $wordnum;
                            if($multimediaobj = get_mulutimedia_num($post->message)){
                                $multimedianum += $multimediaobj->num;
                            }
                        }
                        $groupcountrydata->discussion += count($posteddiscussions);
                        $groupcountrydata->multimedia += $multimedianum;

                        /*
                        //対話した相手の人数と国籍
                        list($discsin,$discsparam) = $DB->get_in_or_equal(array_keys($posteddiscussions));
                        $discswhere = "userid <> ? AND discussion {$discsin}";
                        $dparam = ['studentid'=>$student->id];
                        $dparam += $discsparam;
                        if($participants = $DB->get_fieldset_select('forum_posts', 'DISTINCT userid', $discswhere,$dparam)){
                            $groupcountrydata->participants += count($participants);
                            list($partin,$partparam) = $DB->get_in_or_equal($participants);
                            $countrywhere = "id {$partin}";
                            $countryids = $DB->get_fieldset_select('user', 'DISTINCT country', $countrywhere,$partparam);
                            $groupcountrydata->countryids += $countryids;
                        }
                        */

                        //Replyした
                        $groupcountrydata->repliedusers++;
                    }else{
                        $studentdata->discussion = 0;
                        $groupcountrydata->notrepliedusers++;
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
                    $groupcountrydata->views += count($views);

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
                        if(!@$groupcountrydata->firstpost || $groupcountrydata->firstpost > $firstpostdate){
                            $groupcountrydata->firstpost =  $firstpostdate;
                        }

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
                        if(!@$groupcountrydata->lastpost || $groupcountrydata->lastpost < $lastpostdate){
                            $groupcountrydata->lastpost =  $lastpostdate;
                        }
                    }else{
                        $studentdata->firstpost = '-';
                        $studentdata->lastpost = '-';
                    }
                    $groupcountrydata->users++;
                }
                if($sumtime){
                    $dif = ceil($sumtime/$groupcountrydata->replies);
                    $dif_time = gmdate("H:i:s", $dif);
                    $dif_days = (strtotime(date("Y-m-d", $dif)) - strtotime("1970-01-01")) / 86400;
                    $groupcountrydata->replytime =  "{$dif_days}days<br>$dif_time";
                }
                //$groupcountrydata->participants = round($groupcountrydata->participants/$groupcountrydata->users,3);
                $groupcountrydata->multinationals = round($groupcountrydata->multinationals/$groupcountrydata->users,3);
                //$groupcountrydata->discussion = round($groupcountrydata->discussion/$groupcountrydata->users,3);
                $groupcountrydata->posts = $groupcountrydata->posts;//round($groupcountrydata->posts/$groupcountrydata->users,3);
                $groupcountrydata->replies = $groupcountrydata->replies;//round($groupcountrydata->replies/$groupcountrydata->users,3);
                $groupcountrydata->groupname = $group->name;
                $this->data[$group->id][$key] = $groupcountrydata;
            }
        }
    }

}

class groupcountrydata{
    public $country;
    public $groupname;
    public $posts;
    public $replies = 0;
    //public $maxdepth = 0;
    //public $avedepth = 0;
    public $threads = 0;
    public $views = 0;
    public $wordcount = 0;
    public $participants = 0;
    public $multinationals = 0;
    public $multimedia = 0;
    public $density = 0;
    public $replytime = 0;
    public $users = 0;
    public $notrepliedusers = 0;
    public $repliedusers = 0;
    public $repliestoseed = 0;
    public $firstpost;
    public $lastpost;
    public $countryids = array();

}
