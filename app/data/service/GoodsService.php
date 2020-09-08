<?php

namespace app\data\service;

use think\admin\extend\DataExtend;
use think\admin\Service;

/**
 * 商品数据服务
 * Class GoodsService
 * @package app\data\service
 */
class GoodsService extends Service
{

    /**
     * 更新商品库存数据
     * @param string $code
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function syncStock($code)
    {
        // 商品入库统计
        $query = $this->app->db->name('ShopGoodsStock');
        $query->field('goods_code,goods_spec,ifnull(sum(number_stock),0) number_stock');
        $stockList = $query->where(['code' => $code])->group('goods_id,goods_spec')->select()->toArray();
        // 商品销量统计
        $query = $this->app->db->table('shop_order a')->field('b.goods_code,b.goods_spec,ifnull(sum(b.stock_sales),0) stock_sales');
        $query->leftJoin('shop_order_item b', 'a.order_no=b.order_no')->where([['b.code', '=', $code], ['a.status', 'in', [1, 2, 3, 4, 5]]]);
        $salesList = $query->group('b.goods_id,b.goods_spec')->select()->toArray();
        // 组装更新数据
        $dataList = [];
        foreach (array_merge($stockList, $salesList) as $vo) {
            $key = "{$vo['goods_code']}@@{$vo['goods_spec']}";
            $dataList[$key] = isset($dataList[$key]) ? array_merge($dataList[$key], $vo) : $vo;
            if (empty($dataList[$key]['stock_sales'])) $dataList[$key]['stock_sales'] = 0;
            if (empty($dataList[$key]['stock_total'])) $dataList[$key]['stock_total'] = 0;
        }
        unset($salesList, $stockList);
        // 更新商品规格销量及库存
        foreach ($dataList as $vo) {
            $map = ['goods_code' => $code, 'goods_spec' => $vo['goods_spec']];
            $set = ['stock_total' => $vo['stock_total'], 'stock_sales' => $vo['stock_sales']];
            $this->app->db->name('ShopGoodsItem')->where($map)->update($set);
        }
        // 更新商品主体销量及库存
        $this->app->db->name('ShopGoods')->where(['code' => $code])->update([
            'stock_total' => intval(array_sum(array_column($dataList, 'stock_total'))),
            'stock_sales' => intval(array_sum(array_column($dataList, 'stock_sales'))),
        ]);
        return true;
    }

    /**
     * 获取分类数据
     * @param string $type 数据类型
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getCateList($type = 'arr2tree'): array
    {
        $map = ['deleted' => 0, 'status' => 1];
        $query = $this->app->db->name('ShopGoodsCate');
        $query->where($map)->order('sort desc,id desc');
        $query->withoutField('sort,status,deleted,create_at');
        return DataExtend::$type($query->select()->toArray());
    }

    /**
     * 最大分类级别
     * @return integer
     */
    public function getCateLevel(): int
    {
        return 3;
    }

}