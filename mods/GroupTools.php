<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2018/10/23
 * Time: 11:36 PM
 */

class GroupTools extends ModBase
{
    public function __construct(CrazyBot $main, $data) { parent::__construct($main, $data); }

    public function execute($it) {
        if ($this->data["message_type"] != "group") return true;
        $ls = [
            "group_id" => $this->data["group_id"],
            "user_id" => $this->getUserId(),
            "no_cache" => true
        ];
        switch ($it[0]) {
            case "说话":
            case "闭嘴":
                $set_status = $it[0] == "说话" ? true : false;
                CQAPI::get_group_member_info($this->getConnection(), $ls, function ($response) use ($set_status) {
                    if ($response["data"]["role"] == "member" && !in_array($response["data"]["user_id"], ZMBuf::get("su"))) return;
                    GroupManager::setGroupStatus($response["data"]["group_id"], $response["self_id"], $set_status, ($set_status ? "已开启本群服务！" : "已停止本群服务！再次开启服务请回复：说话"));
                });
                return true;
            case "关闭全体消息记录":
            case "开启全体消息记录":
                $set_status = $it[0] == "开启全体消息记录" ? true : false;
                $conn = $this->getConnection();
                CQAPI::get_group_member_info($this->getConnection(), $ls, function ($response) use ($set_status, $conn) {
                    if ($response["data"]["role"] == "member" && !in_array($response["data"]["user_id"], ZMBuf::get("su"))) return;
                    $attribute_list = DataProvider::getJsonData("data/group_list_attribute.json");
                    $attribute_list[$response["data"]["group_id"]]["at_msg_record"] = $set_status;
                    CQAPI::send_group_msg($conn, $response["data"]["group_id"],"成功" . ($set_status ? "开启" : "关闭") . "群at全体消息记录功能！");
                    DataProvider::setJsonData("data/group_list_attribute.json", $attribute_list);
                });
                return true;
            case "查看全体消息":
            case "查看全体信息":
            case "显示全体消息":
            case "全体消息":
                if (!isset($it[1])) $page = 1;
                else $page = intval($it[1]);

                $db = DataProvider::connect();//阻塞IO
                $r = $db->query("SELECT * FROM group_at_message WHERE group_id = '" . $db->real_escape_string($this->data["group_id"]) . "'");
                $ls = [];
                while (($v = $r->fetch_assoc())) {
                    $ls[] = $v;
                }
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
                $group_info = ZMBuf::get("group_list")[$this->data["group_id"]] ?? null;
                if ($group_info === null) {
                    Console::trace("群组列表中找不到群：" . $this->data["group_id"]);
                    return false;
                }
                if (($group_info["at_msg_record"] ?? true) === true) {
                    if (mb_strpos($this->data["message"], "[CQ:at,qq=all]") !== false) {
                        $user_id = $this->getUserId();
                        $group_id = $this->data["group_id"];
                        $msg = str_replace("[CQ:at,qq=all]", "", $this->data["message"]);
                        $msg = trim($msg);
                        if ($msg != "") {
                            $db = DataProvider::connect();
                            $db->query("INSERT INTO group_at_message (group_id, send_time, user_id, message) VALUES ('" . $db->real_escape_string($group_id) . "', '" . time() . "', '" . $db->real_escape_string($user_id) . "', '" . $db->real_escape_string(base64_encode($msg)) . "')");
                        }
                    }
                }
        }
        return false;
    }
}