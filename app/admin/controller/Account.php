<?php

namespace app\admin\controller;

use think\facade\Db;
use think\facade\Request;
use think\facade\Session;

class Account extends Base
{

    public function index(){
        // 验证登录
        if (!Session::get('is_login')){ return redirect('/admin/index/login'); }

        $account = Db::name('account')->order('pk_id ASC')->select()->each(function($v){
            $where = [];
            $where[] = ['account', '=', $v['account']];
            $where[] = ['is_close', '=', 0];
            $v['slice_num_now'] = Db::name('slice')->where($where)->count();
            return $v;
        });
        $data = [];
        $data['account'] = $account;
        $data['slice_num'] = getKV('slice_num');
        $data['slice_open_num'] = getKV('slice_open_num');

        return view('account/index', $data);
    }

    public function view(){
        // 验证登录
        if (!Session::get('is_login')){ return redirect('/admin/index/login'); }

        $account = input("account");

        # 获取账号信息
        $findAccount = Db::name('account')->where('account', $account)->find();

        $hold = [
            'buy_num' => 0,
            'buy_volume' => 0,
            'sell_num' => 0,
            'sell_volume' => 0,
        ];

        # 获取账号持仓详细
        $where = [];
        $where[] = ['account', '=', $findAccount['account']];
        $where[] = ['is_close', '=', 0];
        $selectSlice = Db::name('slice')->where($where)->order('create_date DESC')->select()->each(function($v) use(&$hold){
            # 手续费计算
            $v['open_charge'] = calculate_profit_open($v['open_price']);
            # 持仓数量
            $v['slice_today'] = Db::name('slice_today')->where('account', $v['account'])->where('date', date('Y-m-d', strtotime($v['create_date'])))->find();
            # 统计多空数量
            if ($v['buy_or_sell'] == 'buy'){ $hold['buy_num']++; $hold['buy_volume'] += $v['volume']; }
            if ($v['buy_or_sell'] == 'sell'){ $hold['sell_num']++; $hold['sell_volume'] += $v['volume']; }
            return $v;
        });
        $findAccount['slice_num_now'] = count($selectSlice);

        # 获取账号已平交易明细
        $where = [];
        $where[] = ['account', '=', $findAccount['account']];
        $where[] = ['is_close', '=', 1];
        $selectSliceIsClose = Db::name('slice')->where($where)->order('create_date DESC')->select()->each(function($v){

            # 手续费计算
            $v['open_charge'] = calculate_profit_open($v['open_price']);
            $v['close_charge'] = calculate_profit_open($v['close_price']);

            # 盈利
            if ($v['buy_or_sell'] == 'buy'){ $v['slice_profit_num'] = (($v['close_price'] - $v['open_price']) * 10 - $v['open_charge'] - $v['close_charge']) * $v['volume']; }
            if ($v['buy_or_sell'] == 'sell'){ $v['slice_profit_num'] = (($v['open_price'] - $v['close_price']) * 10 - $v['open_charge'] - $v['close_charge']) * $v['volume']; }

            # 持仓数量
            $v['slice_today'] = Db::name('slice_today')->where('account', $v['account'])->where('date', date('Y-m-d', strtotime($v['create_date'])))->find();

            return $v;
        });

        # 获取账号真实持仓信息
        $where = [];
        $where[] = ['account', '=', $findAccount['account']];
        $selectAccountPosition = Db::name('account_position')->where($where)->order('create_date DESC, buy_or_sell')->limit(10)->select();


        # display data 1
        $monthSlice = Account::getMonthSlice($account, date('Y'), date('m'));

        # display data 2
        $clientEquity = Account::getAccountClientEquity($account);

        # display data 3
        $field = [
            'DATE(close_date) AS close_day',
            "
                SUM(
                    CASE 
                            WHEN buy_or_sell = 'buy' 	THEN ( ( close_price - open_price ) * 10 - open_charge - close_charge ) * volume
                            WHEN buy_or_sell = 'sell' THEN ( ( open_price - close_price ) * 10 - open_charge - close_charge ) * volume
                            ELSE 0
                    END
                ) AS day_profit
	        "
        ];
        $selectSliceDay = Db::name('slice')
            ->field($field)
            ->where('account', $account)
            ->whereNotNull('close_date')
            ->group('close_day')
            ->order('close_day ASC')
            ->fetchSql(0)
            ->select();

        if (!isset($selectSliceDay[0]['close_day'])){
            $dateRange = [];
        }else{
            $dateRange = getDateRange($selectSliceDay[0]['close_day'], $selectSliceDay[count($selectSliceDay)-1]['close_day']);
        }

        $sliceCLoseListDay = [];
        $totalClose = 0;
        foreach ($dateRange as $v3){
            $in = 0;
            foreach ($selectSliceDay as $v4){
                if ($v3 == $v4['close_day']){
                    $totalClose += $v4['day_profit'];
                    $sliceCLoseListDay[] = ['close_day' => $v4['close_day'], 'day_profit' => $v4['day_profit'], 'total' => $totalClose];
                    $in = 1;
                }
            }
            if ($in == 1){ continue; }
            $sliceCLoseListDay[] = ['close_day' => $v3, 'day_profit' => 0, 'total' => $totalClose];
        }

        $data = [];
        $data['account'] = $findAccount;
        $data['account_position'] = $selectAccountPosition;
        $data['slice'] = $selectSlice;
        $data['slice_is_close'] = $selectSliceIsClose;
        $data['slice_num'] = getKV('slice_num');
        $data['slice_open_num'] = getKV('slice_open_num');
        $data['month_day_profit'] = $monthSlice;
        $data['client_equity'] = $clientEquity;
        $data['slice_close_list_day'] = $sliceCLoseListDay;
        $data['hold'] = $hold;
        return view('account/view', $data);
    }

    public function viewList(){
        // 验证登录
        if (!Session::get('is_login')){ return redirect('/admin/index/login'); }

        $account = input("account");

        # 获取账号信息
        $findAccount = Db::name('account')->where('account', $account)->find();

        # 获取账号持仓详细
        $where = [];
        $where[] = ['account', '=', $findAccount['account']];
        $where[] = ['is_close', '=', 0];
        $selectSlice = Db::name('slice')->where($where)->order('create_date DESC')->select();
        $findAccount['slice_num_now'] = count($selectSlice);

        # 获取账号全部记录
        $where = [];
        $where[] = ['account', '=', $findAccount['account']];
        $selectSliceIsClose = Db::name('slice')->where($where)->order('create_date DESC')->select()->each(function($v){

            # 手续费计算
            $v['open_charge'] = calculate_profit_open($v['open_price']);
            $v['close_charge'] = calculate_profit_open($v['close_price']);

            # 盈利
            if ($v['buy_or_sell'] == 'buy'){ $v['slice_profit_num'] = (($v['close_price'] - $v['open_price']) * 10 - $v['open_charge'] - $v['close_charge']) * $v['volume']; }
            if ($v['buy_or_sell'] == 'sell'){ $v['slice_profit_num'] = (($v['open_price'] - $v['close_price']) * 10 - $v['open_charge'] - $v['close_charge']) * $v['volume']; }

            # 持仓数量
            $v['slice_today'] = Db::name('slice_today')->where('account', $v['account'])->where('date', date('Y-m-d', strtotime($v['create_date'])))->find();

            return $v;
        });

        $data = [];
        $data['account'] = $findAccount;
        $data['slice'] = $selectSlice;
        $data['slice_is_close'] = $selectSliceIsClose;
        $data['slice_num'] = getKV('slice_num');
        $data['slice_open_num'] = getKV('slice_open_num');
        return view('account/viewList', $data);
    }

    // 手动平仓记录
    public function updateCloseSlice(){
        if (Request::method() == 'GET'){
            $sliceId = input('slice_id');

            # 获取这分仓信息
            $findSlice = Db::name('slice')->where('pk_id', $sliceId)->find();
            
            # 获取账号信息
            $findAccount = Db::name('account')->where('account', $findSlice['account'])->find();

            $data = [
                'account' => $findAccount,
                'slice' => $findSlice,
            ];
            return view('account/updateCloseSlice', $data);
        }else{
            $sliceId = input('slice_id');
            $closePrice = input('close_price');

            $data = [
                'close_price' => $closePrice,
                'close_charge' => calculate_profit_open($closePrice),
                'is_close' => 1,
                'close_date' => date('Y-m-d H:i:s'),
                'note' => '手动平仓记录'
            ];
            $where = [
                ['pk_id', '=', $sliceId],
                ['is_close', '=', 0],
            ];
            $update = Db::name('slice')->where($where)->update($data);
            if (empty($update)){ return error('account'); }
            return success('account');
        }
    }

    // 手动修改账号名备注
    public function updateAccountName(){
        if (Request::method() == 'GET'){
            $account = input('account');

            # 获取账号信息
            $findAccount = Db::name('account')->where('account', $account)->find();

            if (empty($findAccount)){ return error('account'); }

            $data = [
                'account' => $findAccount,
            ];
            return view('account/updateAccountName', $data);
        }else{
            $account = input('account');
            $accountName = input('account_name');

            $data = [
                'account_name' => $accountName,
            ];
            $update = Db::name('account')->where('account', $account)->update($data);
            if (empty($update)){ return error('account/updateAccountName'); }
            return success('account');
        }
    }

    // 设置观察用户
    public function updateMember(){
        if (Request::method() == 'GET'){
            $account = input('account');

            # 获取账号信息
            $findAccount = Db::name('account')->where('account', $account)->find();
            if (empty($findAccount)){ return error('account', '获取账号信息错误'); }

            # 获取用户信息
            $findMember = Db::name('member')->where('account', $account)->find();

            $data = [
                'account' => $findAccount,
                'member' => $findMember,
            ];
            return view('account/updateMember', $data);
        }
        if (Request::method() == 'POST' AND input('action') == 'create'){
            $account = input('account');
            $username = input('username');
            $password = input('password');

            # 获取账号信息
            $findAccount = Db::name('account')->where('account', $account)->find();
            if (empty($findAccount)){ return error('account', '获取账号信息错误'); }

            # 用户名不能重复
            $findMember = Db::name('member')->where('username', $username)->find();
            if (!empty($findMember)){ return error('account', '用户名不能重复'); }

            $data = [
                'account' => $account,
                'username' => $username,
                'password' => password_hash($password, PASSWORD_BCRYPT),
                'create_date' => date('Y-m-d H:i:s'),
            ];
            $insertMember = Db::name('member')->insert($data);
            if (empty($insertMember)){ return error('account'); }
            return success('account');
        }
        if (Request::method() == 'POST' AND input('action') == 'update'){
            $account = input('account');
            $memberId = input('member_id');
            $username = input('username');
            $password = input('password');

            # 获取账号信息
            $findAccount = Db::name('account')->where('account', $account)->find();
            if (empty($findAccount)){ return error('account', '获取账号信息错误'); }

            # 用户名不能重复
            $findMember = Db::name('member')->where('username', $username)->find();
            if (!empty($findMember) AND ($findMember['pk_id'] != $memberId)){ return error('account', '用户名不能重复'); }

            $data = [
                'account' => $account,
                'username' => $username,
                'create_date' => date('Y-m-d H:i:s'),
            ];
            if (!empty($password)){
                $data['password'] = password_hash($password, PASSWORD_BCRYPT);
            }
            $insertMember = Db::name('member')->where('pk_id', $memberId)->update($data);
            if (empty($insertMember)){ return error('account'); }
            return success('account');
        }
    }

    // 获取每日收益情况
    public function download(){
        $account = input('account');

        # 获取账号信息
        $findAccount = Db::name('account')->where('account', $account)->find();
        if (empty($findAccount)){ return '未找到账号'; }

        # 获取账号已平交易明细
        $where = [];
        $where[] = ['account', '=', $findAccount['account']];
        $selectSlice = Db::name('slice')->where($where)->order('create_date ASC')->select()->each(function($v){
            # 手续费计算
            $v['open_charge'] = calculate_profit_open($v['open_price']);
            $v['close_charge'] = calculate_profit_open($v['close_price']);

            # 盈利
            if ($v['buy_or_sell'] == 'buy'){ $v['slice_profit_num'] = (($v['close_price'] - $v['open_price']) * 10) * $v['volume']; }
            if ($v['buy_or_sell'] == 'sell'){ $v['slice_profit_num'] = (($v['open_price'] - $v['close_price']) * 10) * $v['volume']; }

            # 手续费
            $v['total_charge'] = ($v['open_charge'] + $v['close_charge']) * $v['volume'];

            # 持仓数量
            $v['slice_today'] = Db::name('slice_today')->where('account', $v['account'])->where('date', date('Y-m-d', strtotime($v['create_date'])))->find();

            return $v;
        });

        # 下载
        $filename = $findAccount['account'] . "_" . date('Ymd') . ".csv";
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");
        $csvField = ['交易时间', '开仓合约', '方向', '开仓价格', '开仓手数', '平仓总手数', '持仓总手数', '平仓时间', '平仓价格', '盈利情况', '手续费'];
        fputcsv($output, $csvField);
        foreach ($selectSlice as $v) {
            $need = [
                dateTimeToDate($v['create_date']),
                $v['code'],
                $v['buy_or_sell'] == 'buy'? '多': '空',
                $v['open_price'],
                $v['volume'],
                $v['slice_today']['close_volume'],
                $v['slice_today']['hold_volume'],
                dateTimeToDate($v['close_date']),
                $v['is_close'] == 0? '-': $v['close_price'],
                $v['is_close'] == 0? '-': $v['slice_profit_num'],
                $v['is_close'] == 0? '-': $v['total_charge'],
            ];
            fputcsv($output, $need);
        }
        fclose($output);
    }

    // 获取每日收益情况 20240921
    public function download20240921(){
        $account = input('account');

        # 获取账号信息
        $findAccount = Db::name('account')->where('account', $account)->find();
        if (empty($findAccount)){ return '未找到账号'; }

        # 获取账号已平交易明细
        $where = [];
        $where[] = ['account', '=', $findAccount['account']];
        $selectSlice = Db::name('slice')->where($where)->order('create_date ASC')->select()->each(function($v){
            # 手续费计算
            $v['open_charge'] = calculate_profit_open($v['open_price']);
            $v['close_charge'] = calculate_profit_open($v['close_price']);

            # 盈利
            if ($v['buy_or_sell'] == 'buy'){ $v['slice_profit_num'] = (($v['close_price'] - $v['open_price']) * 10) * $v['volume']; }
            if ($v['buy_or_sell'] == 'sell'){ $v['slice_profit_num'] = (($v['open_price'] - $v['close_price']) * 10) * $v['volume']; }

            # 手续费
            $v['total_charge'] = ($v['open_charge'] + $v['close_charge']) * $v['volume'];

            # 持仓数量
            $v['slice_today'] = Db::name('slice_today')->where('account', $v['account'])->where('date', date('Y-m-d', strtotime($v['create_date'])))->find();

            return $v;
        });

        # 下载
        $filename = $findAccount['account'] . "_" . date('Ymd') . ".csv";
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");
        $csvField = [
            '开仓合约', '开仓日期', '买卖', '开平', '手数', '成交价格', '成交额', '手续费',
            '平仓日期', '买卖', '开平', '手数', '成交价格', '成交额', '手续费',
            '平仓盈亏'
        ];
        fputcsv($output, $csvField);
        foreach ($selectSlice as $v) {
            $need = [
                $v['code'],

                dateTimeToDate($v['create_date']),
                $v['buy_or_sell'] == 'buy'?'买':'卖',
                '开',
                $v['volume'],
                $v['open_price'],
                $v['open_price'] * $v['volume'] * 10,
                $v['open_charge'] * $v['volume'],

                $v['is_close'] == 0? '-': (dateTimeToDate($v['close_date'])),
                $v['is_close'] == 0? '-': ($v['buy_or_sell'] == 'buy'?'卖':'买'),
                $v['is_close'] == 0? '-': ('平'),
                $v['is_close'] == 0? '-': ($v['volume']),
                $v['is_close'] == 0? '-': ($v['close_price']),
                $v['is_close'] == 0? '-': ($v['close_price'] * $v['volume'] * 10),
                $v['is_close'] == 0? '-': ($v['close_charge'] * $v['volume']),
                $v['is_close'] == 0? '-': ($v['slice_profit_num'] * $v['volume']),

            ];
            fputcsv($output, $need);
        }
        fclose($output);
    }

    // 获取每日收益情况 20240930
    public function download20240930(){
        $account = input('account');

        # 获取账号信息
        $findAccount = Db::name('account')->where('account', $account)->find();
        if (empty($findAccount)){ return '未找到账号'; }

        # 获取所有日期
        $sliceData = [];

        $subQuery = Db::name('slice')->field('DATE(create_date) AS date')->where('account', $findAccount['account'])->order('create_date ASC')->buildSql();
        Db::table($subQuery)
            ->alias('date')
            ->field(['date'])
            ->order('date ASC')
            ->group('date')
            ->select()
            ->each(function($v) use($account, &$sliceData){

                $date = $v['date'];

                // 日期
                $sliceData[$date]['date'] = $date;

                // 做多
                $sliceData[$date]['open_buy'] = Db::name('slice')->where(['account' => $account, 'buy_or_sell' => 'buy'])->whereDay('create_date', $date)->fetchSql(0)->find();
                // 多放手数统计
                $sliceData[$date]['open_buy_volume'] = Db::name('account_position')
                    ->where(['account' => $account, 'buy_or_sell' => 'buy'])
                    ->whereDay('date', $date)
                    ->fetchSql(0)
                    ->value('position');
                //var_dump($sliceData[$date]['open_buy_volume']);

                // 做空
                $sliceData[$date]['open_sell'] = Db::name('slice')->where(['account' => $account, 'buy_or_sell' => 'sell'])->whereDay('create_date', $date)->find();
                // 多放手数统计
                $sliceData[$date]['open_sell_volume'] = Db::name('account_position')
                    ->where(['account' => $account, 'buy_or_sell' => 'sell'])
                    ->whereDay('date', $date)
                    ->fetchSql(0)
                    ->value('position');

                // 动态权益
                $clientEquity = Db::name('account_day_client_equity')->where(['account' => $account])->whereDay('date', $date)->value('client_equity');
                // 出入金变化
                $io = Db::name('io_balance')
                    ->field("sum(balance) AS balance")
                    ->where(['account' => $account])
                    ->whereDay('date', $date)
                    ->whereBetween('date', ['2024-01-01', $date])
                    ->select();
                $ioBalance = empty($io[0]['balance']) ? 0 : $io[0]['balance'];
                $sliceData[$date]['io_balance'] = $ioBalance; // 出入金变化 用于补正动态权益变化

                $ioBalance = -$ioBalance;
                $sliceData[$date]['client_equity'] = $clientEquity + $ioBalance;

                return $v;
            });


        # 下载
        $filename = $findAccount['account'] . "_" . date('Ymd') . ".csv";
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");
        $csvField = [
            '交易日期',
            '开仓合约', '做空价格', '手数', '空总手数', '平仓时间', '平仓价格', '盈利情况', ' ',
            '开仓合约', '做多价格', '手数', '多总手数', '平仓时间', '平仓价格', '盈利情况', '动态权益', // '出入金',
        ];
        fputcsv($output, $csvField);

        foreach ($sliceData as $v) {
            $need = [
                dateTimeToDate($v['date']),

                isset($v['open_sell'])?      $v['open_sell']['code']:         '-',
                isset($v['open_sell'])?      $v['open_sell']['open_price']:         '-',
                isset($v['open_sell'])?      $v['open_sell']['volume']:         '-',
                isset($v['open_sell_volume'])?      $v['open_sell_volume']:         '-',
                isset($v['open_sell'])?      dateTimeToDate($v['open_sell']['close_date']):         '-',
                isset($v['open_sell'])?      ($v['open_sell']['is_close'] == 0? '-':$v['open_sell']['close_price']):         '-',
                isset($v['open_sell'])?      $v['open_sell']['is_close'] == 0? '-': (($v['open_sell']['open_price'] - $v['open_sell']['close_price']) * $v['open_sell']['volume'] * 10):         '-',

                ' ',

                isset($v['open_buy'])?      $v['open_buy']['code']:         '-',
                isset($v['open_buy'])?      $v['open_buy']['open_price']:         '-',
                isset($v['open_buy'])?      $v['open_buy']['volume']:         '-',
                isset($v['open_buy_volume'])?      $v['open_buy_volume']:         '-',
                isset($v['open_buy'])?      dateTimeToDate($v['open_buy']['close_date']):         '-',
                isset($v['open_buy'])?      ($v['open_buy']['is_close'] == 0? '-':$v['open_buy']['close_price']):         '-',
                isset($v['open_buy'])?      $v['open_buy']['is_close'] == 0? '-': (($v['open_buy']['close_price'] - $v['open_buy']['open_price']) * $v['open_buy']['volume'] * 10):         '-',

                isset($v['client_equity'])? number_format($v['client_equity'],2,'.',','):         '-',
                // isset($v['io_balance'])? number_format($v['io_balance'],2,'.',','):         '-',

            ];
            fputcsv($output, $need);
        }
        fclose($output);
    }

    /**
     * @param string $year 年份
     * @param string $month 月份
     * @return array
     */
    public static function getMonthSlice(string $account, string $year, string $month){
        $currentYear = $year;
        $currentMonth = $month;
        $date = new \DateTime("$currentYear-$currentMonth-01");
        $lastDayOfMonth = (clone $date)->modify('first day of next month')->modify('-1 day');
        $daysInMonth = $lastDayOfMonth->format('d');

        $oneData = []; // 每日统计
        $listData = []; // 每日叠加累计
        $totalData = 0; // 本月累计

        // 循环运行每日的操作
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateString = sprintf('%s-%02d-%02d', $currentYear, $currentMonth, $day);

            $startDate = $dateString.' 00:00:00';
            $endDate = $dateString.' 23:59:59';

            $field = ['*'];
            $result = Db::name('slice')
                ->field($field)
                ->where('is_close', 1)
                ->where('account', $account)
                ->whereBetween('close_date', [$startDate, $endDate])
                ->order('create_date', 'ASC')
                ->select()
                ->toArray();

            //var_dump($result);
            $dayPrice = 0; // 今日总收入
            foreach ($result as $v){
                if ($v['buy_or_sell'] == 'buy'){ $dayPrice += (($v['close_price'] - $v['open_price']) * 10 - $v['open_charge'] - $v['close_charge']) * $v['volume']; }
                if ($v['buy_or_sell'] == 'sell'){ $dayPrice += (($v['open_price'] - $v['close_price']) * 10 - $v['open_charge'] - $v['close_charge']) * $v['volume']; }
            }

            $oneData[$dateString] = $dayPrice;
            $totalData += $dayPrice;
            $listData[$dateString] = $totalData;
        }

        $data = [
            'day_profit' => $oneData, // 每日盈利单独统计
            'total_profit' => $listData, // 每日盈利连续统计
            'total_profit_price' => $totalData, // 本月总盈利额
        ];
        return $data;
    }

    /**
     * 获取账号动态权益每日
     * @param string $account
     */
    public static function getAccountClientEquity(string $account){
        // 获取初始价格
        $initial_margin = Db::name('account')->where('account', $account)->value('balance');

        // 获取历史价格
        $field = ['client_equity', 'date'];
        $selectAccount = Db::name('account_day_client_equity')->field($field)->where('account', $account)->order('date', 'ASC')->select()->toArray();


        foreach ($selectAccount as $k => $v){
            $selectAccount[$k]['limit_init_profit'] = ($v['client_equity'] - $initial_margin); // 对比初始化金额盈利
            $selectAccount[$k]['limit_init_profit_rate'] = truncateFloat(($v['client_equity'] - $initial_margin) / $initial_margin, 4); // 对比初始化金额盈利比例
            $selectAccount[$k]['limit_init_profit_rate_string'] = floatToPercentage($selectAccount[$k]['limit_init_profit_rate']); // 对比初始化金额盈利比例
        }

        return $selectAccount;
    }
}
