<?php

namespace Site\Websocket\AgentRebate\AgentLayer;

use Lib\Websocket\Context;
use Lib\Config;
use Site\Websocket\CheckLogin;

/**
 * Layer_Edit.php
 *
 * @description   代理层级设置--修改代理层级
 * @Author  rose
 * @date  2019-04-07
 * @links 参数：AgentRebate/AgentLayer/LayerEdit {"layer_id":19}
 * @modifyAuthor   Luis
 * @modifyTime  2019-04-23
 */

class LayerEdit extends CheckLogin
{
    public function onReceiveLogined(Context $context, Config $config)
    {
        $auth = json_decode($context->getInfo('StaffAuth'));
        if (!in_array('broker_layer_update', $auth)) {
            $context->reply(['status' => 202, 'msg' => '你还没有操作权限']);

            return;
        }
        $StaffGrade = $context->getInfo('StaffGrade');
        if ($StaffGrade != 0) {
            $context->reply(['status' => 203, "msg"=>'当前账号没有操作权限权限']);

            return;
        }
        $data = $context->getData();
        $mysql = $config->data_user;
        $layer_id = $data['layer_id'];
        if (empty($layer_id)) {
            $context->reply(['status' => 203, 'msg' => '编辑的代理等级名称不能为空']);

            return;
        }
        if (!is_numeric($layer_id)) {
            $context->reply(['status' => 204, 'msg' => '编辑的代理层级类型不正确']);

            return;
        }
        $sql = 'SELECT layer_name,min_deposit_amount as deposit,min_bet_amount as bet,min_deposit_user as users FROM layer_info WHERE layer_id=:layer_id';
        $param = [':layer_id' => $layer_id];
        $info = array();
        foreach ($mysql->query($sql, $param) as $row) {
            $info = $row;
        }
        $auths = [];
        $sql = 'SELECT operate_key FROM layer_permit WHERE layer_id=:layer_id';
        foreach ($mysql->query($sql, $param) as $row) {
            $auths[] = $row['operate_key'];
        }
        $info['auth'] = $auths;
        $context->reply([
            'status' => 200,
            'msg' => '信息获取成功',
            'info' => $info,
        ]);
        $cache = $config->cache_site;
        $sql = 'select layer_name,layer_id from layer_info where layer_type>100';
        $agentLayer = iterator_to_array($mysql->query($sql));
        $cache->hset('LayerList', 'agentLayer', json_encode($agentLayer));

        $sql = 'select layer_id,layer_name from layer_info';
        $allLayer = iterator_to_array($mysql->query($sql));
        $cache->hset('LayerList', 'allLayer', json_encode($allLayer));
    }
}
