<?php

namespace Site\Websocket\Cash\DepositAccount;

use Site\Websocket\CheckLogin;
use Lib\Websocket\Context;
use Lib\Config;

/*
 *
 * @description   现金系统-添加公司入款账号
 * @Author  Rose
 * @date  2019-04-26
 * @links  Cash/DepositAccount/DepositBankUpdate {"passage_id":2,"passage_name":"修改测试通道","risk_control":500000,"acceptable":1,"bank_name":"招商银行","bank_branch":"深圳支行","account_number":"88888888888888888888","account_name":"张三"}
 * @modifyAuthor
 * @modifyDate
 *
 * */

class DepositBankUpdate extends CheckLogin
{
    public function onReceiveLogined(Context $context, Config $config)
    {
        $StaffGrade = $context->getInfo('StaffGrade');
        if ($StaffGrade != 0) {
            $context->reply(['status' => 203, '当前账号没有操作权限']);

            return;
        }
        //验证是否有操作权限
        $auth = json_decode($context->getInfo('StaffAuth'));
        if (!in_array('money_deposit_passage', $auth)) {
            $context->reply(['status' => 202, 'msg' => '你还没有操作权限']);

            return;
        }
        $staffId = $context->getInfo('StaffId');
        $data = $context->getData();
        $mysql = $config->data_staff;
        $passage_id = $data['passage_id'];
        $passage_name = $data['passage_name'];  // 通道名称
        $risk_control = $data['risk_control'];   // 风控金额
        $acceptable = $data['acceptable']; // 启用是否 1启用 2停用
        $bank_name = $data['bank_name']; // 银行名称
        $bank_branch = $data['bank_branch']; //开户网点
        $account_number = $data['account_number']; // 银行账号
        $account_name = $data['account_name'];  //开户名
        if (!is_numeric($passage_id)) {
            $context->reply(['status' => 215, 'msg' => '请选择修改通道']);

            return;
        }
        if (empty($passage_name)) {
            $context->reply(['status' => 203, 'msg' => '请输入入款通道名称']);

            return;
        }
        // 验证规则
        $preg = '/^[\x{4e00}-\x{9fa5}A-Za-z0-9]{4,20}$/u';
        if (!preg_match($preg, $passage_name)) {
            $context->reply(['status' => 205, 'msg' => '入款通道名称,请介于4-20位字符之间']);

            return;
        }

        if (empty($risk_control)) {
            $context->reply(['status' => 204, 'msg' => '请输入金额']);

            return;
        }
        if (!is_numeric($risk_control)) {
            $context->reply(['status' => 205, 'msg' => '请输入正确金额']);

            return;
        }
        if ($risk_control > 9999999.99) {
            $context->reply(['status' => 205, 'msg' => '请输入金额']);

            return;
        }
        if (empty($bank_name)) {
            $context->reply(['status' => 206, 'msg' => '请选择银行']);

            return;
        }

        if (empty($bank_branch)) {
            $context->reply(['status' => 207, 'msg' => '请输入开户行']);

            return;
        }
        if (empty($account_number)) {
            $context->reply(['status' => 208, 'msg' => '请输入银行卡号']);

            return;
        }
        // 验证规则
        $preg = '/^[0-9A-Za-z]{16,20}$/';
        if (!preg_match($preg, $account_number)) {
            $context->reply(['status' => 206, 'msg' => '银行账号请输入16-20位,包含数字和字母']);

            return;
        }
        if (empty($account_name)) {
            $context->reply(['status' => 209, 'msg' => '请输入收款人姓名']);

            return;
        }
        if (mb_strlen($account_name) > 20) {
            $context->reply(['status' => 213, 'msg' => '请偶们输入正确的收款人姓名']);

            return;
        }

        if ($acceptable == 1) {
            $acceptable = 1;
        } elseif ($acceptable == 2) {
            $acceptable = 0;
        } else {
            $acceptable = 1;
        }
        //查找通道名称
        $sql = 'SELECT passage_id FROM deposit_passage WHERE passage_name=:passage_name AND passage_id!=:passage_id';
        $param = [':passage_name' => $passage_name, ':passage_id' => $passage_id];
        $infos = array();
        foreach ($mysql->query($sql, $param) as $row) {
            $infos = $row;
        }
        if (!empty($infos)) {
            $context->reply(['status' => 214, 'msg' => '通道名称已存在']);

            return;
        }
        //查找银行卡号是否在使用
        $sql = 'SELECT * FROM deposit_passage_bank_intact WHERE account_number=:account_number AND passage_id!=:passage_id';
        $param = [':account_number' => $account_number, ':passage_id' => $passage_id];
        $info = array();
        foreach ($mysql->query($sql, $param) as $row) {
            $info = $row;
        }
        if (!empty($info)) {
            $context->reply(['status' => 214, 'msg' => '该银行卡号已经添加']);

            return;
        }
        //更新通道信息
        $sql = 'UPDATE deposit_passage SET passage_name = :passage_name, risk_control=:risk_control, acceptable=:acceptable WHERE passage_id=:passage_id';
        $param = [
            ':passage_name' => $passage_name,
            ':risk_control' => $risk_control,
            ':acceptable' => $acceptable,
            ':passage_id' => $passage_id,
        ];
        try {
            $mysql->execute($sql, $param);
        } catch (\PDOException $e) {
            $context->reply(['status' => 400, 'msg' => '修改失败']);
            throw new \PDOException($e);
        }
        //更新银行信息
        $sql = 'UPDATE deposit_passage_bank SET bank_name=:bank_name,bank_branch=:bank_branch,account_number=:account_number,account_name=:account_name WHERE passage_id=:passage_id';
        $param = [
            ':bank_name' => $bank_name,
            ':bank_branch' => $bank_branch,
            ':account_number' => $account_number,
            ':account_name' => $account_name,
            ':passage_id' => $passage_id,
        ];
        try {
            $mysql->execute($sql, $param);
        } catch (\PDOException $e) {
            $context->reply(['status' => 400, 'msg' => '修改失败']);

            return;
        }
        //记录日志
        $sql = 'INSERT INTO operate_log SET staff_id=:staff_id, operate_key=:operate_key, detail=:detail,client_ip= :client_ip';
        $params = [
            ':staff_id' => $staffId,
            ':client_ip' => ip2long($context->getClientAddr()),
            ':operate_key' => 'money_deposit_passage',
            ':detail' => '修改银行入款账户的信息'.$passage_id,
        ];
        $mysql->execute($sql, $params);
        $context->reply(['status' => 200, 'msg' => '修改成功']);
    }
}
