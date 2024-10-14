<?php

namespace app\admin\controller;

use think\facade\Db;
use think\facade\Request;

// 数据中心
class DataCenter extends Base
{
    // 接受请求数据
    public function index(){
        // 验证登录
        #if (!Session::get('is_login')){ return redirect('/admin/index/login'); }
        $input = input();
        $username = Request::header('username');
        $password = Request::header('password');

        // 验证登录
        if (!$this->checkToken($username, $password)){ return json(['code' => 0, 'msg' => '验证失败']); }

        // 账户
        if (!empty($input['account'])){
            foreach ($input['account'] as $v){
                if (!Db::name('account')->where('account', $v['account'])->find()){
                    $data = [
                        'account' => $v['account'],
                        'account_name' => $v['account_name'],
                        'slice_num' => $v['slice_num'],
                        'slice_margin' => $v['slice_margin'],
                        'balance' => $v['balance'],
                        'frozen' => $v['frozen'],
                        'create_date' => $v['create_date'],
                    ];
                    Db::name('account')->data($data)->insert();
                }
            }
        }

        // 账户动态权益变化
        if (!empty($input['account_day_client_equity'])){
            foreach ($input['account_day_client_equity'] as $v){
                if (!Db::name('account_day_client_equity')->where('account', $v['account'])->where('date', $v['date'])->find()){
                    $data = [
                        'account' => $v['account'],
                        'client_equity' => $v['client_equity'],
                        'date' => $v['date'],
                        'create_date' => $v['create_date'],
                    ];
                    if (isset($v["commission"])){ $data['commission'] = $v['commission']; }
                    Db::name('account_day_client_equity')->data($data)->insert();
                }
            }
        }

        // 账户真实持仓
        if (!empty($input['account_position'])){
            foreach ($input['account_position'] as $v){
                if (!Db::name('account_position')->where('account', $v['account'])->where('buy_or_sell', $v['buy_or_sell'])->where('date', $v['date'])->find()){
                    $data = [
                        'account' => $v['account'],
                        'buy_or_sell' => $v['buy_or_sell'],
                        'code' => $v['code'],
                        'exchange' => $v['exchange'],
                        'position' => $v['position'],
                        'position_close' => $v['position_close'],
                        'open_volume' => $v['open_volume'],
                        'close_volume' => $v['close_volume'],
                        'used_margin' => $v['used_margin'],
                        'date' => $v['date'],
                        'create_date' => $v['create_date'],
                    ];
                    Db::name('account_position')->data($data)->insert();
                }
            }
        }

        // 开仓
        if (!empty($input['slice_open'])){
            foreach ($input['slice_open'] as $v){
                if (!Db::name('slice')->where('pk_id', $v['pk_id'])->find()){
                    $data = [
                        'pk_id' => $v['pk_id'],
                        'account' => $v['account'],
                        'buy_or_sell' => $v['buy_or_sell'],
                        'name' => $v['name'],
                        'code' => $v['code'],
                        'volume' => $v['volume'],
                        'open_price' => $v['open_price'],
                        'open_charge' => $v['open_charge'],
                        'open_order_id' => $v['open_order_id'],
                        'close_price' => $v['close_price'],
                        'close_charge' => $v['close_charge'],
                        'close_order_id' => $v['close_order_id'],
                        'is_close' => $v['is_close'],
                        'note' => $v['note'],
                        'close_date' => $v['close_date'],
                        'create_date' => $v['create_date'],
                    ];
                    Db::name('slice')->data($data)->insert();
                }
            }
        }

        // 平仓
        if (!empty($input['slice_close'])){
            foreach ($input['slice_close'] as $v){
                $data = [
                    'close_price' => $v['close_price'],
                    'close_charge' => $v['close_charge'],
                    'close_order_id' => $v['close_order_id'],
                    'is_close' => $v['is_close'],
                    'note' => $v['note'],
                    'close_date' => $v['close_date'],
                ];
                Db::name('slice')->data($data)->where('pk_id', $v['pk_id'])->update();
            }
        }

        $data = [];
        $json = ['code' => 1, 'msg' => 'success', 'data' => $data];
        return json($json);
    }

}
