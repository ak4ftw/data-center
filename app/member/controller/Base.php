<?php

namespace app\member\controller;

use app\member\BaseController;
use think\facade\Db;

class Base extends BaseController {
    public function initialize(){
        parent::initialize();
    }
    // 检查账号信息登录
    protected function checkLogin($username, $password): bool{
        $find = Db::name('member')->where(['username' => $username])->find();
        if (empty($find)){ return false; }
        if (!password_verify($password, $find['password'])){ return false; }
        return true;
    }
}