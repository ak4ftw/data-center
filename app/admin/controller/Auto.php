<?php

namespace app\admin\controller;

use think\facade\Db;

// 自动运行
class Auto extends Base
{

    // 每日持仓信息保存
    public function dayTradeDataSave(){
        $date = input('date'); // 指定日期

        Db::name('account')->select()->each(function ($v) use($date) {
            $account = $v['account'];
            $dateString = date("Y-m-d");
            if (!empty($date)){ $dateString = $date; }
            $startDate = $dateString.' 00:00:00';
            $endDate = $dateString.' 23:59:59';

            # 今日开仓手数
            $openVolume = Db::name('slice')
                ->field("sum(volume) AS volume")
                ->where('account', $account)
                ->whereBetween('create_date', [$startDate, $endDate])
                ->select();
            $v['open_volume'] = empty($openVolume[0]['volume'])? 0: $openVolume[0]['volume'];

            // 如果今日没有新开不统计今日
            // if (empty($v['open_volume'])){ return $v; }

            # 今日平仓手数
            $closeVolume = Db::name('slice')
                ->field("sum(volume) AS volume")
                ->where('account', $account)
                ->whereBetween('close_date', [$startDate, $endDate])
                ->select();
            $v['close_volume'] = empty($closeVolume[0]['volume'])? 0: $closeVolume[0]['volume'];

            # 今日持仓收数
            $holdVolume = Db::name('slice')
                ->field("sum(volume) AS volume")
                ->where('account', $account)
                ->where('is_close', 0)
                ->select();
            $v['hold_volume'] = empty($holdVolume[0]['volume'])? 0: $holdVolume[0]['volume'];

            # 插入
            $data = [
                'account' => $account,
                'open_volume' => $v['open_volume'],
                'close_volume' => $v['close_volume'],
                'hold_volume' => $v['hold_volume'],
                'date' => $dateString,
                'create_date' => date("Y-m-d H:i:s"),
            ];
            $insertSliceToday = Db::name('slice_today')->data($data)->insert();
            return $v;
        });
        return json(['code' => 1]);
    }
}
