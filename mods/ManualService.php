<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2018/10/3
 * Time: 10:51 AM
 */

class ManualService extends ModBase
{
    const mod_level = 200;

    const host_alias = [
        "627577391" => "鲸鱼",
        "2257685948" => "Crane"
    ];

    public function __construct(CrazyBot $main, $data) {
        parent::__construct($main, $data);
        $this->function_call = true;
        if ($this->execute(explodeMsg($data['message'])) !== false) return;
        if ($this->isInSession($this->getUserId(), $this->getRobotId())) {
            $data2 = $this->getSessionData($this->getUserId(), $this->getRobotId());
            if ($data2 === null) return;
            if ($this->getUserId() == $data2["host_user_id"] && $this->getMessageType() == $data2["host_message_type"]) { //如果是客服发的消息
                if($this->getMessageType() == "group" && $data["group_id"] != ADMIN_GROUP) return;
                $this->function_call = "block";
                $message = "[".self::host_alias[$this->getUserId()]."] ".$data["message"];
                CQAPI::send_private_msg($data2["user_robot_id"], $data2["user_id"], $message);
            } elseif ($this->getUserId() == $data2["user_id"]) { //如果是用户发的消息
                if($data2["host_user_id"] == "") return;
                if($this->getMessageType() != "private") return;
                $this->function_call = "block";
                $message = "[".$this->getUser()->getNickname()."(".$this->getUserId().")] ".$data["message"];
                if($data2["host_message_type"] == "group") CQAPI::send_group_msg($data2["host_robot_id"], ADMIN_GROUP, $message);
                else CQAPI::send_private_msg($data2["host_robot_id"], $data2["host_user_id"], $message);
            }
        }
    }

    public static function initValues() {
        ZMBuf::set("manual_session", DataProvider::getJsonData("ManualService_session.json"));
    }

    public static function saveValues() {
        DataProvider::setJsonData("ManualService_session.json", ZMBuf::get("manual_session"));
    }

    public function execute($it) {
        switch ($it[0]) {
            case "人工服务":
                if ($this->getMessageType() == "group") {
                    $this->reply("请在私聊中使用！");
                    return true;
                }
                if ($this->getMessageType() == "wechat") {
                    $this->reply("微信用户暂不支持，敬请期待！");
                    return true;
                }
                if (!isset($it[1]) || $it[1] != "确认") {
                    $this->reply("「人工服务」是一个测试功能。意在对炸毛部分功能有疑问时可通知炸毛主进行沟通。\n激活人工服务请回复：人工服务 确认");
                    return true;
                }
                if ($this->isInSession($this->getUserId(), $this->getRobotId())) return false;
                $this->reply("已请求人工服务。");
                $ls = [
                    "host_user_id" => "",
                    "host_robot_id" => "",
                    "host_message_type" => "",
                    "user_id" => $this->getUserId(),
                    "user_robot_id" => $this->getRobotId()
                ];
                $ts = time();
                CQAPI::debug("用户 " . $this->getUser()->getNickname() . "(" . $this->getUserId() . ") 在机器人" . $this->getRobotId() . " 上请求了人工服务。", "", $this->getRobotId());
                ZMBuf::appendKey("manual_session", $ts, $ls);
                CQAPI::debug("建立会话 ".strval($ts), "", $this->getRobotId());
                return true;
            case "建立会话":
                if (!$this->main->isAdmin()) return false;
                if (count($it) < 2) return $this->reply("用法：建立会话 会话id");
                $id = $it[1];
                if ($this->isInSession($this->getUserId(), $this->getRobotId())) return $this->reply("对不起，你已经建立了会话，请先终止会话");
                if (!ZMBuf::array_key_exists("manual_session", $id)) return $this->reply("对不起，会话 " . $id . " 不存在！");
                $ls = ZMBuf::get("manual_session");
                $ls[$id]["host_user_id"] = $this->getUserId();
                $ls[$id]["host_robot_id"] = $this->getRobotId();
                $ls[$id]["host_message_type"] = $this->getMessageType();
                ZMBuf::set("manual_session", $ls);
                CQAPI::debug("已建立" . self::host_alias[$this->getUserId()] . "与" . $ls[$id]["user_id"] . "的会话！", "", $this->getRobotId());
                CQAPI::send_private_msg($ls[$id]["user_robot_id"], $ls[$id]["user_id"], "炸毛人工[" . self::host_alias[$this->getUserId()] . "]正在服务");
                $this->function_call = "block";
                return true;
            case "退出会话":
            case "结束会话":
                if (!$this->main->isAdmin()) return false;
                $ls = ZMBuf::get("manual_session");
                foreach ($ls as $k => $v) {
                    if ($v["host_user_id"] == $this->getUserId() && $v["host_robot_id"] == $this->getRobotId() && $v["host_message_type"] == $this->getMessageType()) {
                        CQAPI::send_private_msg($v["user_robot_id"], $v["user_id"], "已结束会话！如有任何疑问或不满意请再次联系人工服务！");
                        $this->reply("已结束与" . $v["user_id"] . "的会话！");
                        unset($ls[$k]);
                        ZMBuf::set("manual_session", $ls);
                        return true;
                    }
                }

                return $this->reply("对不起，你没有正在进行的会话！");
        }
        return false;
    }

    public function isInSession($user_id, $robot_id) {
        foreach (ZMBuf::get("manual_session") as $k => $v) {
            if (($v['user_id'] == $user_id && $v['user_robot_id'] == $robot_id) || ($v['host_user_id'] == $user_id && $v['host_robot_id'] == $robot_id))
                return true;
        }
        return false;
    }

    public function getSessionData($user_id, $robot_id) {
        foreach (ZMBuf::get("manual_session") as $k => $v) {
            if ($v["host_user_id"] == $user_id && $v["host_robot_id"] == $robot_id) return $v;
            if ($v["user_id"] == $user_id && $v["user_robot_id"] == $robot_id) return $v;
        }
        return null;
    }
}
