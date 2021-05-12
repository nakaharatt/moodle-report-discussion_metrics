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
class get_discussion_data {
    
    public $data = array();
    
    public function __construct($students,$discussions,$groupid,$starttime=0,$endtime=0){
        global $DB;
        
        if($groupid){
            $wheregroupusers = '(';
            foreach($students as $student){
                $wheregroupusers .= $student->id.',';
            }
            $wheregroupusers .= '0)';
        }

        foreach($discussions as $discussion){
            $threads = 0;
            $firstpostdata = $DB->get_record('forum_posts',array('id'=>$discussion->firstpost));
            $firstpost = $firstpostdata->created;
            $lastpost = 0;
            $replytimearr = array();
            $depthsum = 0;
            $replies = 0;
            $bereplied = 0;
            $levels = array(0,0,0,0);
            $discussiondata = new discussiondata();
            $forum = $DB->get_record('forum',array('id'=>$discussion->forum));
            $discussiondata->forumname = $forum->name;
            $discussiondata->name = $discussion->name." (id=".$discussion->id.")";
            $discswhere = "discussion=?";
            $dparam = ['discussionid'=>$discussion->id];
            if($participants = $DB->get_fieldset_select('forum_posts', 'DISTINCT userid', $discswhere,$dparam)){
                $discussiondata->participants += count($participants);
                list($partin,$partparam) = $DB->get_in_or_equal($participants);
                $countrywhere = "id {$partin}";
                $countryids = $DB->get_fieldset_select('user', 'DISTINCT country', $countrywhere,$partparam);
                $discussiondata->multinationals += count($countryids);
            }
            if($groupid){
                $postssql = 'SELECT * FROM {forum_posts} WHERE userid IN '.$wheregroupusers.' AND discussion = '.$discussion->id. " AND id <> ".$discussion->firstpost;
            }else{
                $postssql = 'SELECT * FROM {forum_posts} WHERE discussion = '.$discussion->id. " AND id <> ".$discussion->firstpost;
            }
            if($starttime){
                $postssql = $postssql.' AND created>'.$starttime;
            }
            if($endtime){
                $postssql = $postssql.' AND created<'.$endtime;
            }
            $posts = $DB->get_records_sql($postssql);
            $discussiondata->posts = count($posts);
            foreach($posts as $post){
                //Word count
                $discussiondata->wordcount += count_words($post->message);
                //Multimedia
                if($multimediaobj = get_mulutimedia_num($post->message)){
                    $discussiondata->multimedia += $multimediaobj->num;
                }
                //Be replied
                if($DB->get_records('forum_posts',array('parent'=>$post->id))){
                    $bereplied++;
                }

                //Depth
                $parent = $post->parent;
                if($parent == $discussion->firstpost) $threads++;
                if($parent){
                    //if(!$DB->get_records('forum_posts',array('parent'=>$post->id))){ //Mean that it is last post of the thread
                        $depth = 0;
                        while($parent!=0){
                            if($parentpost = $DB->get_record('forum_posts',array('id'=>$parent))){
                                $depth++;
                                $parent = $parentpost->parent;
                            }
                        }
                        if($discussiondata->maxdepth < $depth){
                            $discussiondata->maxdepth = $depth;
                        }
                        $depthsum += $depth;
                        if($depth<4){
                            $levels[$depth-1]++;
                        }else{
                            $levels[3]++; //Over Level 4
                        }
                    //}
                    $discussiondata->replies++;
                }

                //TempTimes
                if($firstpost > $post->created) $firstpost = $post->created;
                if($lastpost < $post->created) $lastpost = $post->created;
                $replytimearr[] = $post->created;
            }

            //if($discussiondata->maxdepth) $discussiondata->avedepth = $depthsum/$threads;
            $discussiondata->threads = $threads;
            //$discussiondata->threadsperstudent = $threads/$groupusernum;
            //$discussiondata->threadspercountry = $threads/$countrynum;
            $discussiondata->levels = $levels;
            $discussiondata->l1 = $levels[0];
            $discussiondata->l2 = $levels[1];
            $discussiondata->l3 = $levels[2];
            $discussiondata->l4 = $levels[3];
            $discussiondata->bereplied = $bereplied;

            //Median replytime
            if($discussiondata->posts ==1){
                $discussiondata->replytime = discussion_metrics_format_time($lastpost - $firstpost);
            }elseif($discussiondata->posts > 1){
                sort($replytimearr);
                $middleval = floor(($discussiondata->posts)/2);
                if($discussiondata->posts % 2){
                    $discussiondata->replytime = discussion_metrics_format_time($replytimearr[$middleval-1] - $firstpost);
                }else{
                    $discussiondata->replytime = discussion_metrics_format_time(($replytimearr[$middleval-1] + $replytimearr[$middleval])/2 - $firstpost);
                }
            }

            //Density of discussion
            if($discussiondata->posts>0){
                $discussiondata->density = discussion_metrics_format_time(($lastpost-$firstpost)/$discussiondata->posts);
            }
            $this->data[$discussion->id] = $discussiondata;
        }
    }
}

class discussiondata{
    
    public $forumname;
    public $name;
    public $posts;
    public $replies = 0;
    public $bereplied = 0;
    public $maxdepth = 0;
    public $avedepth = 0;
    public $threads = 0;
    public $views = 0;
    public $wordcount = 0;
    public $participants = 0;
    public $multinationals = 0;
    public $multimedia = 0;
    public $density = 0;
    public $replytime = 0;
    public $l1;
    public $l2;
    public $l3;
    public $l4;
}
