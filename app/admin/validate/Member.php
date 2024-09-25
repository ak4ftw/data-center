<?php
namespace app\admin\validate;

use think\Validate;

class Member extends Validate
{
    protected $rule =   [
        'username'  => 'require|min:5|max:30',
        'password'  => 'require|min:5|max:30',
    ];

    protected $message  =   [
        'name.require' => '用户名不能为空',
        'name.min' => '用户名最小长度5',
        'name.max' => '用户名最大长度30',
        'password.require' => '密码不能为空',
        'password.min' => '密码最小长度5',
        'password.max' => '密码最大长度30',
    ];

}