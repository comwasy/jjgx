<?php
/**

 * Author: dyr
 * Date: 2016-08-23
 */

namespace app\home\model;

use think\Model;
use think\Db;

/**
 * @package Home\Model
 */
class Goods extends Model
{

    public function getDiscountAttr($value, $data)
    {
        return 1;
    }
}