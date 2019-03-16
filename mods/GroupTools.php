<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2018/10/23
 * Time: 11:36 PM
 */

use DataProvider as DP;

class GroupTools extends ModBase
{
    public function __construct(CrazyBot $main, $data) {
        parent::__construct($main, $data);
    }

    public static function initValues() {
        ZMBuf::set("group_attr", DP::getJsonData("GroupTools_attribute.json"));
    }

    public static function saveValues() {
        DP::setJsonData("GroupTools_attribute.json", ZMBuf::get("group_attr"));
    }

    public static function onNotice($req, WSConnection $connection) {
        if ($req["notice_type"] == "group_increase") {
            if (($msg = ZMBuf::get("group_attr")[$req["group_id"]]["join_msg"] ?? "") != "") {
                $info = CQAPI::get_stranger_info($connection, $req["user_id"], true);
                if ($info === null) return;
                $nickname = $info["data"]["nickname"];
                $msg = str_replace("{name}", $nickname, $msg);
                $msg = str_replace("{qq}", $req["user_id"], $msg);
                $msg = str_replace("{enter}", "\n", $msg);
                $msg = str_replace("{at}", CQ::at($req["user_id"]), $msg);
                CQAPI::send_group_msg($connection, $req["group_id"], $msg);
            }
        }
    }

    public function execute($it) {
        if ($this->data["message_type"] != "group") return true;
        switch ($it[0]) {
            case "开启进群提醒":
            case "开启加群提醒":
            case "开启入群提醒":
                $argcs = array_shift($it);
                $other = implode(" ", $it);
                if ($this->data['sender']['role'] == 'member' && !in_array($this->getUserId(), ZMBuf::get('su'))) return false;
                if (trim($other) == "") {
                    $this->reply("请输入你要设置的本群的入群提示语\n==========\n支持变量：\n{name} : 加入的用户昵称\n{qq} : 加入的用户QQ号\n{at} : 艾特新加入的用户\n{enter} : 换行");
                    if (!isset(ZMBuf::get("group_attr")[$this->data["group_id"]]["join_msg"])) $this->reply("例子：\n欢迎新人 {at} 加入本群！请仔细阅读群公告！");
                    $recv = $this->waitUserMessage($this->data);
                    if ($recv === null) return $this->reply("设置入群提示语超时，请重新回复：" . $argcs);
                    if (in_array(mb_substr($recv, 0, 2), ["取消", "不用", "不了", "算了", "停止", "终止", "撤销"])) return $this->reply("停止设置");
                } else {
                    $recv = trim($other);
                }
                $r = ZMBuf::get("group_attr");
                $r[$this->data["group_id"]]["join_msg"] = $recv;
                ZMBuf::set("group_attr", $r);
                return $this->reply("成功设置入群提示！关闭入群提醒请回复：\n关闭入群提醒");
            case "关闭进群提醒":
            case "关闭入群提醒":
            case "关闭加群提醒":
                if ($this->data['sender']['role'] == 'member' && !in_array($this->getUserId(), ZMBuf::get('su'))) return false;
                $r = ZMBuf::get("group_attr");
                $r[$this->data["group_id"]]["join_msg"] = "";
                ZMBuf::set("group_attr", $r);
                return $this->reply("成功关闭入群提示！再次开启入群提醒请回复：\n开启入群提醒");
            case "说话":
            case "闭嘴":
                $set_status = $it[0] == "说话" ? true : false;
                if ($this->data['sender']['role'] == 'member' && !in_array($this->getUserId(), ZMBuf::get('su'))) return false;
                GroupManager::setGroupStatus($this->data['group_id'], $this->getRobotId(), $set_status, ($set_status ? "已开启本群服务！" : "已停止本群服务！再次开启服务请回复：说话"));
                return true;
            case "关闭全体消息记录":
            case "开启全体消息记录":
                $set_status = $it[0] == "开启全体消息记录" ? true : false;
                $conn = $this->getConnection();
                if ($this->data["sender"]["role"] == "member" && !in_array($this->getUserId(), ZMBuf::get("su"))) return false;
                CQAPI::send_group_msg($conn, $this->data['group_id'], "成功" . ($set_status ? "开启" : "关闭") . "群at全体消息记录功能！");
                GroupManager::getGroup($this->data['group_id'])->setFunction('at_msg_record', $set_status);
                return true;
            case "查看全体消息":
            case "查看全体信息":
            case "显示全体消息":
            case "全体消息":
                if (!isset($it[1])) $page = 1;
                else $page = intval($it[1]);
                //CQAPI::debug("co_uid: ".Co::getuid());
                $ls = DataProvider::query("SELECT * FROM group_at_message WHERE group_id = ?", [$this->data["group_id"]]);
                for ($i = 0; $i < count($ls) - 1; $i++) {//外层循环控制排序趟数
                    for ($j = 0; $j < count($ls) - 1 - $i; $j++) {
                        if ($ls[$j]["send_time"] < $ls[$j + 1]["send_time"]) {
                            $tmp = $ls[$j];
                            $ls[$j] = $ls[$j + 1];
                            $ls[$j + 1] = $tmp;
                        }
                    }
                }
                $page_cnt = intval(count($ls) / 3);
                if (count($ls) % 3 != 0) $page_cnt++;
                if ($page < 0 || $page > $page_cnt) {
                    $this->reply("页数输入错误！请输入1～" . $page_cnt . "页范围。\n用法：查看全体消息 1");
                    return true;
                }
                if ($ls == []) $this->reply("本群暂无记录at全体的消息。");
                else {
                    $msg = "「全体消息」";
                    for ($i = $page * 3 - 3; $i < $page * 3; $i++) {
                        if ($i != ($page - 1)) $msg .= "\n===============";
                        if (isset($ls[$i])) {
                            $msg .= "\n发送人：" . $ls[$i]["user_id"];
                            $msg .= "\n时间：" . date("Y-M-d H:i", $ls[$i]["send_time"]);
                            $msg .= "\n消息：" . base64_decode($ls[$i]["message"]);
                        } else break;
                    }
                    $msg .= "\n> 页数 ( " . $page . " / " . $page_cnt . ")";
                    $this->reply($msg);
                }
                return true;
            default:
                if (mb_strpos($this->data["message"], "[CQ:at,qq=all]") !== false) {
                    if (!GroupManager::isGroupExists($this->data['group_id'])) return false;
                    $group_info = GroupManager::getGroup($this->data['group_id'])->getFunction();
                    if (($group_info['at_msg_record'] ?? true) === true) {
                        $user_id = $this->getUserId();
                        $group_id = $this->data["group_id"];
                        $msg = str_replace("[CQ:at,qq=all]", "", $this->data["message"]);
                        $msg = trim($msg);
                        if ($msg != "") {
                            DataProvider::query("INSERT INTO group_at_message (group_id, send_time, user_id, message) VALUES (?,?,?,?)", [$group_id, time(), $user_id, base64_encode($msg)]);
                        }
                    }
                }
        }
        return false;
    }
}
