<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 深圳和万众科技有限公司，并保留所有权利。
 * 网站地址: http://www.hwz315.com
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: IT宇宙人 2015-08-10 $
 *
 */ 
namespace app\home\controller; 
use think\Controller;
use think\Url;
use think\Config;
use think\Page;
use think\Verify;
use think\Db;
use think\Cache;
class Test extends Controller {
    
    public function index(){      
	   $mid = 'hello'.date('H:i:s');
       //echo "测试分布式数据库$mid";
       //echo "<br/>";
       //echo $_GET['aaa'];       
       //  M('config')->master()->where("id",1)->value('value');
       //echo M('config')->cache(true)->where("id",1)->value('value');
       //echo M('config')->cache(false)->where("id",1)->value('name');
       echo $config = M('config')->cache(false)->where("id",1)->value('value');
        // $config = DB::name('config')->cache(true)->query("select * from __PREFIX__config where id = :id",['id'=>2]);
         print_r($config);
       /*
       //DB::name('member')->insert(['mid'=>$mid,'name'=>'hello5']);
       $member = DB::name('member')->master()->where('mid',$mid)->select();
	   echo "<br/>";
       print_r($member);
       $member = DB::name('member')->where('mid',$mid)->select();
	   echo "<br/>";
       print_r($member);
	*/   
//	   echo "<br/>";
//	   echo DB::name('member')->master()->where('mid','111')->value('name');
//	   echo "<br/>";
//	   echo DB::name('member')->where('mid','111')->value('name');
         echo C('cache.type');
    }  
    
    public function redis(){
        Cache::clear();
        $cache = ['type'=>'redis','host'=>'192.168.0.201'];        
        Cache::set('cache',$cache);
        $cache = Cache::get('cache');
        print_r($cache);         
        S('aaa','ccccccccccccccccccccccc');
        echo S('aaa');
    }
    
    public function table(){
        $t = Db::query("show tables like '%tp_goods_2017%'");
        print_r($t);
    }
    
        public function t(){
                
         //echo $queue = \think\Cache::get('queue');
         //\think\Cache::inc('queue',1);
         //\think\Cache::dec('queue');
        $res = DB::name('config')->cache(true)->find();
        print_r($res);
              DB::name('config')->update(['id'=>1,'name'=>'http://www.hwz315.com11111']);
        $res = DB::name('config')->cache(true)->find();
        print_r($res);
        
        
    }
}