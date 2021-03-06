<?php

namespace Site\Websocket\Cash\WithdrawSetting;

use Site\Websocket\CheckLogin;
use Lib\Websocket\Context;
use Lib\Config;

/*
 *
 * @description  现金系统-出款管理-保存出款审核设置
 * @Author  Rose
 * @date  2019-05-07
 * @links  Cash/WithdrawSetting/ReviewSave {"layer_id":1,"withdraw_audit_amount":500,"withdraw_audit_first":1}
 * @modifyAuthor   Rose
 * @modifyTime  2019-05-08
 * */

class ReviewSave extends CheckLogin
{
    public function onReceiveLogined(Context $context, Config $config)
    {
        $StaffGrade = $context->getInfo('StaffGrade');
        if ($StaffGrade != 0) {
            $context->reply(['status' => 203, 'msg' => '当前账号没有操作权限']);

            return;
        }
        //验证是否有操作权限
        $auth = json_decode($context->getInfo('StaffAuth'));
        if (!in_array('money_setting', $auth)) {
            $context->reply(['status' => 202, 'msg' => '你还没有操作权限']);

            return;
        }
        $staffId = $context->getInfo('StaffId');
        $mysql = $config->data_user;
        $data = $context->getData();
        $layer_id = $data['layer_id'];
        $withdraw_audit_amount = $data['withdraw_audit_amount'];
        if (!isset($data['withdraw_audit_first'])) {
            $context->reply(['status' => 206, 'msg' => '是否首次出款必须选择']);

            return;
        }
        $withdraw_audit_first = $data['withdraw_audit_first'];
        if (!is_numeric($layer_id)) {
            $context->reply(['status' => 204, 'msg' => '层级参数错误']);

            return;
        }
        if (!is_numeric($withdraw_audit_amount)) {
            $context->reply(['status' => 205, 'msg' => '出款审核金额参数错误']);

            return;
        }

        if ($withdraw_audit_first == 1) {
            $audit_first = 1;
        } elseif ($withdraw_audit_first == 0) {
            $audit_first = 0;
        } else {
            $context->reply(['status' => 205, 'msg' => '出款审核金额参数错误']);

            return;
        }
        $sql = 'UPDATE layer_info SET withdraw_audit_amount=:withdraw_audit_amount,withdraw_audit_first=:withdraw_audit_first WHERE layer_id=:layer_id';
        $param = [':withdraw_audit_amount' => $withdraw_audit_amount, ':withdraw_audit_first' => $audit_first, ':layer_id' => $layer_id];
        try {
            $mysql->execute($sql, $param);
        } catch (\PDOException $e) {
            $context->reply(['status' => 400, 'msg' => '修改失败']);
            throw new \PDOException($e);
        }
        //记录日志
        $sqls = 'INSERT INTO operate_log SET staff_id=:staff_id,operate_key=:operate_key,detail=:detail,client_ip=:client_ip';
        $params = [':staff_id' => $staffId, ':operate_key' => 'money_setting', ':client_ip' => ip2long($context->getClientAddr()), ':detail' => '修改出款管理出款审核设置信息'];
        $staff_mysql = $config->data_staff;
        $staff_mysql->execute($sqls, $params);
        $context->reply(['status' => 200, 'msg' => '修改成功']);

        $taskAdapter = new \Lib\Task\Adapter($config->cache_daemon);
        $taskAdapter->plan('NotifyApp', ['path' => 'User/SiteSetting', 'data' => []]);
    }
}
