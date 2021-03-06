<?php

namespace Site\Websocket\Member\MemberList;

use Lib\Websocket\Context;
use Lib\Config;
use Site\Websocket\CheckLogin;

/**
 * MemberLayerEdit class.
 *
 * @description   会员管理-会员列表-修改会员层级
 * @Author  blake
 * @date  2019-05-08
 * @links  Member/MemberList/MemberLayerEdit {"layer_id":1}
 * 参数：layer_id :当前需要修改的会员的层级id
 * @modifyAuthor   blake
 * @modifyTime  2019-05-08
 */
class MemberLayerEdit extends CheckLogin
{
    public function onReceiveLogined(Context $context, Config $config)
    {
        $staffId = $context->getInfo('StaffId');
        $MasterId = $context->getInfo('MasterId');
        $data = $context->getData();
        $mysql = $config->data_user;
        $layer_id = $data['layer_id'];
        $sql = 'SELECT layer_type FROM layer_info WHERE layer_id=:layer_id';
        $param = [':layer_id' => $layer_id];
        $layer_type = '';
        foreach ($mysql->query($sql, $param) as $row) {
            $layer_type = $row['layer_type'];
        }
        if ($MasterId > 0) {
            $user_layer = [];
            $staff_mysql = $config->data_staff;
            $sql = 'SELECT layer_id FROM staff_layer WHERE staff_id=:staff_id';
            $param = [':staff_id' => $staffId];
            foreach ($staff_mysql->query($sql, $param) as $rows) {
                $user_layer[] = $rows['layer_id'];
            }
            $layers = implode(',', $user_layer);
            $layer_list = array();
            if (!empty($user_layer)) {
                foreach ($user_layer as $item) {
                    $layers_list = array();
                    $sql = 'SELECT layer_id,layer_name FROM layer_info WHERE layer_id=:layer_id';
                    $param = [':layer_id' => $item];
                    $users = '';
                    foreach ($mysql->query($sql, $param) as $row) {
                        $users = $row;
                    }
                    $layers_list['layer_name'] = $users['layer_name'];
                    $layers_list['layer_id'] = $users['layer_id'];
                    $layer_list[] = $layers_list;
                }
            }
            if ($layer_type > 100) {
                $sql = 'SELECT layer_id,layer_name FROM layer_info WHERE layer_id in (:layer) AND layer_type>100';
                $param = [':layer' => $layers];
                $layer_list = iterator_to_array($mysql->query($sql, $param));
            } else {
                $sql = 'SELECT layer_id,layer_name FROM layer_info WHERE layer_id in (:layer) AND layer_type<100';
                $param = [':layer' => $layers];
                $layer_list = iterator_to_array($mysql->query($sql, $param));
            }
        } else {
            if ($layer_type > 100) {
                $sql = 'SELECT layer_id,layer_name FROM layer_info WHERE layer_type>100';
                $layer_list = iterator_to_array($mysql->query($sql));
            } else {
                $sql = 'SELECT layer_id,layer_name FROM layer_info WHERE layer_type<100';
                $layer_list = iterator_to_array($mysql->query($sql));
            }
        }
        $context->reply(['status' => 200, 'msg' => '获取成功', 'list' => $layer_list]);
    }
}
