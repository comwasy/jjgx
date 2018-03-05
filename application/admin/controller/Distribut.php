<?php
/**
 * 会员分红管理
 */
namespace app\admin\controller;
use app\admin\logic\GoodsLogic;
use app\admin\logic\SearchWordLogic;
use think\AjaxPage;
use think\console\command\make\Model;
use think\Loader;
use think\Page;
use think\Db;

class Distribut extends Base {
	//分销商品列表
    function goods_list()
    {
	    $res = getRegion(2980);
	    var_dump($res) ;
        echo '功能开发中';
    }

    //分销商列表
    function distributor_list()
    {
        echo '功能开发中';
    }

    //分销设置
    function set()
    {
        echo '功能开发中';
    }

    //分销商等级
    function grade_list()
    {
        echo '功能开发中';
    }
    //分销关系
    function tree()
    {
        $User = M('Users');
        $topIdInfo = $User->where('pid=0')->find();
        $this->assign('topIdInfo',$topIdInfo); //顶级用户
        return $this->fetch();
    }

    //分成日志
    function rebate_log()
    {
        $Log =  M('rebate_log');
        //搜索
        $keywords = I('keywords/s',false,'trim');
        $user_mobile = I('mobile/s',false,'trim');
        $order_sn = I('order_sn/s',false,'trim');
        $status= I('status/s',false,'trim');
        $start_time= strtotime(I('start_time/s',false,'trim'));
        $end_time= strtotime(I('end_time/s',false,'trim'));
        if($keywords){
            $where['rl.ad_name'] = array('like','%'.$keywords.'%');
        }
        //获佣用户ID
        if($user_mobile)
        {
            $where['u.mobile'] = array('=',$user_mobile);
        }
        //订单编号
        if($order_sn)
        {
            $where['rl.order_sn'] = array('=',$order_sn);
        }
        //创建时间
        if($start_time && $end_time)
        {

            $where['rl.create_time'] = array('between',"$start_time,$end_time");

        }
        //状态
        if($status)
        {
            $where['rl.status'] = array('=',$status);
        }
        $count = $Log->where($where)->count();// 查询满足要求的总记录数
        $Page = $pager = new Page($count,10);// 实例化分页类 传入总记录数和每页显示的记录数

        $list = $Log->alias('rl')
	        ->join('__USERS__ u','rl.user_id = u.user_id','left')
	        ->join('__USERS__ us','rl.buy_user_id = us.user_id','left')
	        ->join('__SUPPLIERS__ s','rl.suppliers_id = s.suppliers_id','left')
	        ->field('rl.*,u.mobile as user_mobile,u.nickname,us.mobile as buy_user_mobile,s.suppliers_phone')
	        ->where($where)
	        ->order('rl.id desc')
	        ->limit($Page->firstRow.','.$Page->listRows)
	        ->select();
		//var_dump($list);exit;
        $_LANG = getRebateLang();
        $show = $Page->show();// 分页显示输出
        $this->assign('list',$list);// 赋值数据集
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('lang',$_LANG);// 赋值分页输出
        $this->assign('pager',$pager);
        return $this->fetch();
    }

    function rebate_new(){
		$order_id = I('order_id');
	    $distribut_switch = tpCache('distribut.switch');
	    if($distribut_switch  == 1 && file_exists(APP_PATH.'common/logic/DistributLogic.php'))
	    {
		    $distributLogic = new \app\common\logic\DistributLogic();
		    $result = $distributLogic->rebate_new($order_id);
		    if($result['status'] == -1){
		    	$this->error($result['msg']);
		    }elseif($result['status'] == 1){
		    	$this->success($result['msg']);
		    }
	    }
    }
    function area_agent_list(){
    	$count = M('area_agent')->count();
	    $Page = $pager = new Page($count,10);// 实例化分页类 传入总记录数和每页显示的记录数
	    $area_agent_list = Db::name('area_agent')->alias('c1')
		    ->field('c1.id as id,c1.user_id as user_id,c1.area_id as area_id,c1.area_sale_order_count as area_sale_order_count,c1.area_sale as area_sale,c1.create_time as create_time,c1.status as status,c2.mobile as mobile')
		    ->join('__USERS__ c2', 'c1.user_id = c2.user_id', 'left')
		    ->order('c1.id desc')
		    ->limit($Page->firstRow.','.$Page->listRows)
		    ->select();
	    if(!empty($area_agent_list)){
	    	foreach ($area_agent_list as $key => $val){
			    $area_agent_list[$key]['area_name'] = getRegionFullName($val['area_id']);
		    }
	    }
	    //var_dump($area_agent_list);exit;
	    $show = $Page->show();// 分页显示输出
	    $this->assign('list',$area_agent_list);// 赋值数据集
	    $this->assign('page',$show);// 赋值分页输出
	    $this->assign('pager',$pager);
    	return $this->fetch();
    }

	function addEditAreaAgent(){
    	$map['id'] = I('id/d',0);
		$p = M('region')->where(array('parent_id'=>0,'level'=> 1))->select();
		$num = M('region')->count();
		$this->assign('province',$p);
    	if(IS_GET){
    		if($map['id']){
			    $result = M('area_agent')->where($map)->find();
			    $area_agent_info = getRegion($result['area_id']);
			    $p2 = M('region')->where(array('parent_id'=> $area_agent_info['province']))->select();
			    if($area_agent_info['district']) $p3 = M('region')->where(array('parent_id'=> $area_agent_info['city']))->select();
			    if($area_agent_info['twon']) $p4 = M('region')->where(array('parent_id'=> $area_agent_info['district']))->select();
			    $area_agent_info['type'] = $result['type'];
			    $area_agent_info['id'] = $map['id'];
			    $user = M('users')->where(array('user_id'=>$result['user_id']))->field('mobile')->find();
			    $area_agent_info['mobile'] = $user['mobile'];
			    $this->assign('area_agent_info', $area_agent_info);
			    $this->assign('city', $p2);
			    if($p3) $this->assign('district', $p3);
			    if($p4)$this->assign('twon', $p4);
		    }
		    return $this->fetch('area_agent');
	    }
		$mothed = I('id/d') > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新
		if(I('is_ajax') == 1){
			if(I('twon/d') > 0){
				$data['area_id'] = I('twon/d');
			}elseif (I('district/d') > 0){
				$data['area_id'] = I('district/d');
			}elseif (I('city/d') > 0){
				$data['area_id'] = I('city/d');
			}else{
				$data['area_id'] = I('province/d');
			}
			$mobile['mobile'] = I('mobile');
			$data['user_id'] = M('users')->where($mobile)->getField('user_id');
			$agent_info = M('area_agent')->where(array('area_id'=>$data['area_id']))->find();
			if(!I('type/d') || I('province') < 1 || I('province') > $num || I('city') < 0 || I('city') > $num ){
				$return_data = array(
					'status' => 0,
					'msg'   => '参数错误',
				);
			}elseif(!$data['user_id']) {
				$return_data = array(
					'status' => 0,
					'msg' => '会员不存在',
				);
			}elseif ($agent_info['user_id']){
				$return_data = array(
					'status' => 0,
					'msg' => '选定区域已有代理商存在,代理商id为'.$agent_info['user_id'],
				);
			}else{
				$data['create_time'] = time();
				$data['type'] = I('type/d');
				if($mothed == 1){
					M('area_agent')->add($data); // 写入数据到数据库
				}else{
					M('area_agent')->where($map)->save($data); // 写入数据到数据库
				}
				$return_data = array(
					'status' => 1,
					'msg'   => '操作成功',
					'data'  => array('url'=>U('Admin/Distribut/area_agent_list')),
				);
			}
			$this->ajaxReturn($return_data);
		}
	}

	function delrebatelog(){
		//1天前未付款的记录
		$time = time()-86400;
		$res = M('rebate_log')->where("create_time < $time and status = 0 or status is null")->delete();
		if($res === false){
			$this->ajaxReturn(array('status'=>-1,'msg'=>'sql语句出错'));
		}elseif ($res === 0){
			$this->ajaxReturn(array('status'=>1,'msg'=>'没有符合条件的数据可删除'));
		}
		$this->ajaxReturn(array('status'=>1,'msg'=>'操作成功'));
	}

}