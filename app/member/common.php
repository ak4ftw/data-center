<?php
// 应用公共文件

// 计算鸡蛋期货单方向手续费
function calculate_profit_open($price){
    $fee_rate = 0.00015; // 手续费比例
    $charge = $price * 10 * $fee_rate;
    $charge = ceil($charge * 100) / 100;
    return $charge;
}

// 空单平后盈利计算
function calculate_profit($open_price, $close_price) {
    $profit_after_fee = ($open_price - $close_price) * 10 - calculate_profit_open($open_price) - calculate_profit_open($close_price);
    return $profit_after_fee;
}

// 浮点数转为字符串
function floatToPercentage($float) {
    return sprintf("%.2f%%", $float * 100);
}

// 保留小数
function truncateFloat($float, $decimals) {
    $factor = pow(10, $decimals);
    return floor($float * $factor) / $factor;
}

// 保留小数
function getKV($key) {
    $value = \think\facade\Db::name('kv')->where(['key' => $key])->value("value");
    return $value;
}

// 获取配置文件
function getConfig(){
    $select = \think\facade\Db::name('kv')->order('pk_id ASC')->select();
    $kvList = [];
    foreach ($select as $v){ $kvList[$v['key']] = $v['value']; }
    $data['select'] = $select;
    $data['kv'] = $kvList;
    return $data;
}

// 账户数量
function getAccountCount(){
    $count = \think\facade\Db::name('account')->count();
    return $count;
}

// uuid
function uuid(){
    $chars = md5(uniqid(mt_rand(), true));
    $uuid = substr ( $chars, 0, 8 ) . '-'
        . substr ( $chars, 8, 4 ) . '-'
        . substr ( $chars, 12, 4 ) . '-'
        . substr ( $chars, 16, 4 ) . '-'
        . substr ( $chars, 20, 12 );
    return $uuid;
}

function jump($msg, $to, $data = [],$sec = 3){
    $ssl = \think\facade\Request::isSsl()?'https':'http';
    $host = \think\facade\Request::host();
    $url = \think\facade\Request::url();
    $nowUrl = "{$ssl}://{$host}{$url}";
    $toUrl = "{$ssl}://{$host}/admin/{$to}";
    \think\facade\View::assign([
        'title'  => 'jump',
        'msg'  => $msg,
        'sec'  => $sec,
        'now_url'  => $nowUrl,
        'to_url'  => $toUrl,
    ]);
    return \think\facade\View::fetch('public/jump');
}

function success($to){
    return jump($msg = "success", $to);
}

function error($to){
    return jump($msg = "error", $to);
}

// 获取所有间隔日期
function getDateRange($startDate, $endDate) {
    $dates = [];

    // 创建 DateTime 对象
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);

    // 如果结束日期小于开始日期，直接返回空数组
    if ($end < $start) {
        return $dates;
    }

    // 迭代每一天，直到结束日期
    while ($start <= $end) {
        $dates[] = $start->format('Y-m-d');
        $start->modify('+1 day');
    }

    return $dates;
}

// 获取下一天日期
function dateTimeToDate($date){
    if (empty($date)){ return '-'; }
    return date('Y-m-d', strtotime($date));
}

