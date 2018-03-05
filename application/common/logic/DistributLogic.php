<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/11/27
 * Time: 21:02
 */

namespace app\common\logic;

use think\Model;

class DistributLogic extends Model
{
	public $goodsPrefecture;
    public $orderGoodsModel;
    public function __construct(){
    	parent::__construct();
	    if(!cache('goods_prefecture')){
		    $goods_prefecture_list = M('goods_prefecture')->select();
		    foreach ($goods_prefecture_list as $val){
			    $goods_prefecture[$val['id']] = $val;
		    }
		    cache('goods_prefecture',$goods_prefecture);
	    }
	    $this->goodsPrefecture = cache('goods_prefecture');
        $this->orderGoodsModel = M('order_goods');
    }

    /**
     * 获取分单信息
     * @param $id   订单id
     * @return array
    **/
    public function getOrderGoodsInfo($id){
        $map['order_id'] = $id;
	    $orderGoodsInfo = $this->orderGoodsModel->where($map)->select();
        return $orderGoodsInfo;
    }

	/**
	 * 记录分润信息
	 * @param $order 订单信息
	 */
	public function rebate_log($order){
		$user_id = $order['user_id'];
		$orderGoodsInfo = $this->getOrderGoodsInfo($order['order_id']);
		$date = tpCache('distribut.date');
		$userInfo = get_user_info($user_id);
		//三级分润涉及id
		$leader_array = array(
			'self_rate'     => $user_id,
			'first_rate'    => $userInfo['first_leader'],
			'second_rate'   => $userInfo['second_leader'],
			'third_rate'    => $userInfo['third_leader'],
		);
		//累计业绩等级
		$sale_level_rate = array(
			'0'  => 0,
			'1'  => tpCache('distribut.level_1_rate'),
			'2'  => tpCache('distribut.level_2_rate'),
			'3'  => tpCache('distribut.level_3_rate'),
			'4'  => tpCache('distribut.level_4_rate')
		);
		$area =  array(
			'provincial'    =>$order['province'],//省id
			'city'          =>$order['city'],   //市id
			'county'        =>$order['district'],//县id
			'town'          =>$order['twon'],   //镇id
		);
		foreach ($orderGoodsInfo as $val){
			$data1 = array(
				'buy_user_id'       => $userInfo['user_id'], //购买人ID
				'nickname'          => $userInfo['nickname'],   //购买人姓名
				'order_sn'          => $order['order_sn'],  //订单编号
				'order_id'          => $order['order_id'],  //订单编号
				'goods_price'       => $val['goods_price']*$val['goods_num'], //订单商品总额
				'create_time'       => time(),          //分成记录生成时间
				'status'            => $order['order_status'] ? $order['order_status'] : 0,    //订单状态
				'remark'            => '',  //如果是取消, 有取消备注
			);
			$suppliers_phone = M('goods')->alias('g')
				->join('__SUPPLIERS__ s','g.suppliers_id = s.suppliers_id','left')
				->field('s.suppliers_id')
				->where("g.goods_id={$val['goods_id']} and g.suppliers_id > 0")
				->find();
			if($suppliers_phone){
				$suppliers_data = array(
					'suppliers_id'      => $suppliers_phone['suppliers_id'], //供应商id
					'money'             => $val['supplier_price']*$val['goods_num'],   //获佣金额
					'rebate_type'       => 'suppliers_money', //供应商货款
				);
				$data = array_merge($data1,$suppliers_data);
				M('rebate_log')->add($data);
			}
			//如果有设置分佣金额则按分佣金额算，没有则：计算商品利润 = 商城销售价 - 商品成本价
			if($val['commission']){
				$profit = $val['commission'];
			}else{
				$profit = $val['cost_price'] != 0 ? ($val['goods_price'] - $val['cost_price'])*$val['goods_num'] : 0;
			}
			//有利润才走分润区间
			if($profit > 0 ){
				$goods_prefecture = $this->goodsPrefecture[$val['prefecture_id']];//一级/二级/三级返佣比率
				//分红开始
				$sale_level = 0;
				foreach ($leader_array as $k=>$v){
					//$v为0时表示此分销等级为系统
					$leader_info = $v ? get_user_info($v,0) : 0;
					if($val['prefecture_id'] == '3' && $k == 'first_rate' && $leader_info['level'] == '1') {
						//商品为购送专区商品，会员等级为普通会员，分润只分一级
						$money = distribution(3, $profit);
					}else{
						//商品为非购送专区商品，会员等级为VIP会员
						$money = $goods_prefecture[$k] ? distribution($goods_prefecture[$k],$profit) : 0;
						if($val['prefecture_id'] != '3' && $leader_info['sale_level'] > 0){  //商品为购送专区时不参与累计业绩分红
							$prev_level = isset($sale_level) ? $sale_level : 0 ;
							if($leader_info && $leader_info['sale_level'] > $prev_level){
								$level_gap_rate = $sale_level_rate[$leader_info['sale_level']] - $sale_level_rate[$prev_level];
								$sale_level_money = distribution($level_gap_rate,$profit);
								$personal_income = $goods_prefecture['personal_income_tax_rate'] > 0 ? distribution($goods_prefecture['personal_income_tax_rate'],$sale_level_money) : 0;//个人所得税
								$love = $goods_prefecture['love_rate'] > 0 ? distribution($goods_prefecture['love_rate'],$sale_level_money) : 0;//爱心基金
								$pay_points = $goods_prefecture['revisit_pay_points_rate'] >0 ? distribution($goods_prefecture['revisit_pay_points_rate'],$sale_level_money) : 0;//复消币
								$maintenance_cost = $goods_prefecture['maintenance_cost_rate'] > 0 ? distribution($goods_prefecture['maintenance_cost_rate'],$sale_level_money) : 0;//平台维护费
								$sale_level_money = $sale_level_money - $personal_income - $love - $pay_points - $maintenance_cost;
								$data2 = array(
									'user_id'           => $v, //获佣人ID
									'money'             => $sale_level_money,   //获佣金额
									'personal_income'   => $personal_income,     //个人所得税
									'love'              => $love,     //个人所得税
									'pay_points'        => $pay_points,     //复消币
									'maintenance_cost'  => $maintenance_cost,     //平台维护费
									'level'             => $leader_info['level'], //获佣用户级别
									'rebate_type'       => $val['prefecture_id'].',level_'.$leader_info['sale_level'].'_rate', //分红类型(商品专区,分红级别)如$k=first_rate为一级分红
								);
								$data = array_merge($data1,$data2);
								M('rebate_log')->add($data);
							}
						}
					}
					$personal_income = $goods_prefecture['personal_income_tax_rate'] > 0 ? distribution($goods_prefecture['personal_income_tax_rate'],$money) : 0;//个人所得税
					$love = $goods_prefecture['love_rate'] > 0 ? distribution($goods_prefecture['love_rate'],$money) : 0;//爱心基金
					$pay_points = $goods_prefecture['revisit_pay_points_rate'] >0 ? distribution($goods_prefecture['revisit_pay_points_rate'],$money) : 0;//复消币
					$maintenance_cost = $goods_prefecture['maintenance_cost_rate'] > 0 ? distribution($goods_prefecture['maintenance_cost_rate'],$money) : 0;//平台维护费
					$money = $money - $personal_income - $love - $pay_points - $maintenance_cost;
					$data3 = array(
						'user_id'           => $v, //获佣人ID
						'money'             => $money,              //获佣金额
						'personal_income'   => $personal_income,     //个人所得税
						'love'              => $love,     //个人所得税
						'pay_points'        => $pay_points,     //复消币
						'maintenance_cost'  => $maintenance_cost,     //平台维护费
						'level'             => $leader_info['level'], //获佣用户级别
						'rebate_type'       => $val['prefecture_id'].','.$k, //分红类型(商品专区,分红级别)如$k=first_rate为一级分红
					);
					if($leader_info['sale_level']){
						//如果当前会员销售业绩等级高于前一级则
						$sale_level = $leader_info['sale_level'] > $prev_level ? $leader_info['sale_level'] : $prev_level;
					}
					$data = array_merge($data1,$data3);
					if($money > 0 && $leader_info)
						M('rebate_log')->add($data);
				}
				//生成区域代理分红
				foreach ($area as $k=>$v){
					if($v > 0) $area_agent = M('area_agent')->field('user_id,type')->where(array('area_id'=>$v,'status'=>'1'))->find();

					if($area_agent){
						switch ($area_agent['type']){
							//镇区代理3%
							case '1':
								$money = distribution(3,$profit);
								$area_type = 'area_1_rate';
								break;
							//市县代理2%
							case '2':
								$money = distribution(2,$profit);
								$area_type = 'area_2_rate';
								break;
							case '3':
								$money = distribution(1,$profit);
								$area_type = 'area_3_rate';
								break;
							default:
								$money = 0;
								break;
						}

						if($money > 0 && $area_agent){
							$personal_income = $goods_prefecture['personal_income_tax_rate'] > 0 ? distribution($goods_prefecture['personal_income_tax_rate'],$money) : 0;//个人所得税
							$love = $goods_prefecture['love_rate'] > 0 ? distribution($goods_prefecture['love_rate'],$money) : 0;//爱心基金
							$pay_points = $goods_prefecture['revisit_pay_points_rate'] >0 ? distribution($goods_prefecture['revisit_pay_points_rate'],$money) : 0;//复消币
							$maintenance_cost = $goods_prefecture['maintenance_cost_rate'] > 0 ? distribution($goods_prefecture['maintenance_cost_rate'],$money) : 0;//平台维护费
							$money = $money - $personal_income - $love - $pay_points - $maintenance_cost;
							$leader_info = get_user_info($area_agent['user_id'],0);
							$data4 = array(
								'user_id'           => $area_agent['user_id'], //获佣人ID
								'money'             => $money,              //获佣金额
								'personal_income'   => $personal_income,     //个人所得税
								'love'              => $love,     //个人所得税
								'pay_points'        => $pay_points,     //复消币
								'maintenance_cost'  => $maintenance_cost,     //平台维护费
								'level'             => $leader_info['level'], //获佣用户级别
								'rebate_type'       => $val['prefecture_id'].','.$area_type, //分红类型(商品专区,分红级别)如$k=first_rate为一级分红
							);
							$data = array_merge($data1,$data4);
							M('rebate_log')->add($data);
						}

					}
				}
			}
		}
	}

	public function auto_confirm(){
		$auto_confirm_date = tpCache('distribut.date') * (60 * 60 * 24); //自动分红时间
		$time = time() - $auto_confirm_date; // 比如7天以前的可用自动分红
		$data = array(
			'confirm_time'  => time(),
			'status'        => 3,
			'remark'        => '已分红'
		);

		$data2 = array(
			'confirm_time'  => time(),
			'status'        => 4,
			'remark'        => '非VIP会员无法获得分红'
		);
		$order_id = array();
		$rebate_info = M('rebate_log')->where("status = 2 and confirm < $time")->field('id,user_id,suppliers_id,buy_user_id,order_id,order_sn,goods_price,money,pay_points,level,rebate_type')->select();

		if($rebate_info){
			foreach ($rebate_info as $v){
				$map['id'] = $v['id'];
				//修改订单表订单为已分成
				if(!in_array($v['order_id'],$order_id)){
					array_push($order_id,$v['order_id']);
					$res['is_distribut'] = '1';
					M('order')->where(array('order_id'=>$v['order_id']))->save($res);
					$orderGoods = M('order_goods')->field('goods_num,goods_price')->where(array('order_id'=>$v['order_id']))->select();
					foreach ($orderGoods as $val){
						$sale_count += $val['goods_num'] * $val['goods_price'];
					}
				}
				//增加购买会员及单线上级累计销售业绩
				update_underline_sale($v['buy_user_id'],$sale_count);

				$desc = getRebateLang();
				if($v['level'] == 1 && $v['user_id']){
					//获佣会员级别level为普通会员时取消分红
					M('rebate_log')->where($map)->save($data2);
				}else{
					//获佣会员级别level为非普通会员时执行分红
					M('rebate_log')->where($map)->save($data);
					accountLog($v['user_id'],$v['money'],0,$desc[$v['rebate_type']],$v['money'],$v['order_id'],$v['order_sn'],$v['pay_points'],$v['suppliers_id']);
				}
			}
		}
	}

	public function rebate_new($order_id){
		$data = array(
			'confirm_time'  => time(),
			'status'        => 3,
			'remark'        => '已分红'
		);
		$data2 = array(
			'confirm_time'  => time(),
			'status'        => 4,
		);
		$rebate_info = M('rebate_log')->where("order_id = $order_id")->field('id,user_id,suppliers_id,buy_user_id,order_id,order_sn,goods_price,money,pay_points,level,rebate_type')->select();

		if($rebate_info){
			foreach ($rebate_info as $v){
				$map['id'] = $v['id'];
				$desc = getRebateLang();
				if($v['user_id'] && stripos($v['rebate_type'],'area') && $v['level'] == 1 ){
					//获佣会员级别level为普通会员时取消分红
					M('rebate_log')->where($map)->save($data2);
				}else{
					//获佣会员级别level为非普通会员时执行分红
					M('rebate_log')->where($map)->save($data);
					$res1 = accountLog($v['user_id'],$v['money'],0,$desc[$v['rebate_type']],$v['money'],$v['order_id'],$v['order_sn'],$v['pay_points'],$v['suppliers_id']);
					if(!$res1)
						return ['status'=>-1,'msg'=>'写入账户记录表失败'];
				}
			}
			$orderGoods = M('order_goods')->field('goods_num,goods_price')->where(array('order_id'=>$order_id))->select();
			foreach ($orderGoods as $val) {
				$sale_count += $val['goods_num'] * $val['goods_price'];
			}
			//增加购买会员及单线上级累计销售业绩
			update_underline_sale($v['buy_user_id'],$sale_count);
			//修改订单表订单为已分成
			$res['is_distribut'] = '1';
			$result = M('order')->where(array('order_id'=>$v['order_id']))->save($res);
			if(!$result){
				return ['status'=>-1,'msg'=>'变更订单分润状态失败'];
			}else{
				return ['status'=>1,'msg'=>"手动分红,订单：$order_id,成功！"];
			}
		}
	}
}