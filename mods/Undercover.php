<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2018/6/28
 * Time: 1:41 AM
 */

use DataProvider as DP;

class Undercover extends ModBase
{
    public function __construct(CrazyBot $main, $data) {
        parent::__construct($main, $data);
        $this->function_call = true;
        if ($this->execute(explode(" ", $data["message"])) === true) return;
        if (is_array($this->getUser()->getBuffer()) && isset($this->getUser()->getBuffer()["type"]) && $this->getUser()->getBuffer()["type"] == "undercover") {
            if (trim($data["message"]) == "确认房间") {
                $buf = $this->getUser()->getBuffer();
                if ($buf["data"]["undercover"] == "" || $buf["data"]["civilian"] == "") {
                    $this->reply("你还没有设置卧底词和平民词哦～");
                    $this->reply("请输入卧底词和平民词，以空格分开。\n例如：可口可乐 百事可乐\n或进行随机出词，回复：随机");
                    return;
                }
                $this->printRoomResult($buf["data"]);
                $this->getUser()->setBuffer('');
                return;
            }
            $it = explode(" ", $data["message"]);
            $first = "";
            $second = "";
            while (!empty($it)) {
                $s = array_shift($it);
                if ($s != "" && $first == "") $first = $s;
                elseif ($s != "" && $second == "") $second = $s;
            }
            if ($first == "随机") {
                $this->getRandomWords($first, $second);
            }
            if ($second == "")
                return;
            $this->reply("你选择的词是：\n卧底词：" . $first . "\n平民词：" . $second . "\n确认创建房间请回复：确认房间\n继续换词请继续输入新词，例如：xxx yyy\n或：随机");
            $buf = $this->getUser()->getBuffer();
            $buf["data"]["undercover"] = $first;
            $buf["data"]["civilian"] = $second;
            $this->getUser()->setBuffer($buf);
        }
        if ($data["message_type"] == "private" || $data["message_type"] == "wechat") {
            $num = intval($data["message"]);
            if (is_numeric($data["message"]) && $num >= 1000 && $num <= 9999) {
                if ($this->isRoomExists($num)) {
                    $db = DP::connect();
                    $r = $db->query("SELECT * FROM undercover_user WHERE user_id = '" . $this->getUserId() . "'");
                    if ($r->num_rows == 0) {
                        $db->query("INSERT INTO undercover_user (user_id, room_id, player_num) VALUES ('" . $this->getUserId() . "',0,0)");
                    } else {
                        $ss = $r->fetch_assoc();
                        if ($ss["room_id"] == $num) {
                            $this->setPlayerStatus($num, $ss["player_num"]);
                            return;
                        }
                    }
                    $r = $db->query("SELECT * from undercover_user WHERE room_id = " . $num);
                    $r_max = 1;
                    if ($r->num_rows == 0) {
                        $this->setPlayerStatus($num, 1);
                    } else {
                        $all = $r->fetch_all();
                        foreach ($all as $k => $v) {
                            if ($v[2] > $r_max) $r_max = $v[2];
                        }
                        if ($r_max >= $this->getRoom($num)["room_size"]) return;
                        else $this->setPlayerStatus($num, $r_max + 1);
                    }
                }
            }
        }
    }

    public function execute($it) {
        switch ($it[0]) {
            case "谁是卧底":
                if (count($it) < 2) {
                    $this->sendHelp();
                    return true;
                }
                switch ($it[1]) {
                    case "创建房间":
                        if (count($it) < 3) {
                            $this->sendHelp();
                            return true;
                        }
                        $count = intval($it[2]);
                        if ($count < 4 || $count > 13) {
                            $this->reply("对不起，范围输入错误！请输入4～13的数字！\n谁是卧底 创建房间 人数：创建一个谁是卧底的房间");
                            return true;
                        }
                        if (($room = $this->createRoom($count))) {
                            $this->reply("创建房间成功！请输入卧底词和平民词，以空格分开。\n例如：可口可乐 百事可乐\n或进行随机出词，回复：随机");
                            $this->getUser()->setBuffer(["type" => "undercover", "data" => $room]);
                            return true;
                        } else {
                            $this->reply("创建失败！");
                        }
                        return true;
                    default:
                        $this->sendHelp();
                        return true;
                }
        }
        return false;
    }

    private function sendHelp() {
        $msg = "「创建房间帮助」";
        $msg .= "\n谁是卧底 创建房间 人数：创建一个谁是卧底的房间，人数范围为4～13";
        $msg .= "\n例如：谁是卧底 创建房间 8";
        $this->reply($msg);
    }

    /**
     * 检查房间是否存在
     * @param $room_id
     * @return bool
     */
    public function isRoomExists($room_id) {
        $db = DP::connect();
        $r = $db->query("select * from undercover_room where room_id = " . $room_id);
        return $r->num_rows != 0;
    }

    /**
     * 获取卧底数量
     * @param $cnt
     * @return int
     */
    public function getUndercoverNumByCount($cnt) {
        if ($cnt >= 4 && $cnt <= 7) return 1;
        elseif ($cnt >= 8 && $cnt <= 11) return 2;
        elseif ($cnt >= 12) return 3;
        return 1;
    }

    /**
     * 创建谁是卧底房间，生成信息
     * @param $count
     * @return mixed
     */
    public function createRoom($count) {
        $create = 0;
        while (true) {
            $room_id = mt_rand(1000, 9999);
            if (!$this->isRoomExists($room_id)) {
                $create = $room_id;
                break;
            }
        }
        $cover_count = $this->getUndercoverNumByCount($count);
        $room = [
            "room_id" => intval($create),
            "create_time" => time(),
            "god" => $this->getUserId(),
            "undercover" => "",
            "civilian" => "",
            "undercover_num" => $cover_count,
            "undercover_1" => 0,
            "undercover_2" => 0,
            "undercover_3" => 0,
            "room_size" => $count
        ];
        $ls = [];
        for ($i = 0; $i < $count; $i++) {
            $ls[] = $i;
        }
        $s = array_rand($ls, $cover_count);
        for ($i = 1; $i <= $cover_count; $i++) {
            $room["undercover_" . $i] = $s[$i - 1] + 1;
        }
        $db = DP::connect();
        $db->query("INSERT INTO undercover_room (room_id, create_time, god, undercover, civilian, undercover_num, undercover_1, undercover_2, undercover_3, room_size) VALUES (" . $room["room_id"] . ", " . $room["create_time"] . ", '" . $db->real_escape_string($room["god"]) . "', '', '', " . $cover_count . ", " . $room["undercover_1"] . "," . $room["undercover_2"] . "," . $room["undercover_3"] . ", " . $count . ")");
        return $room;
    }

    /**
     * 打印房间信息给上帝
     * @param $room_data
     */
    public function printRoomResult($room_data) {
        $msg = "*** 房间创建成功 ***";
        $msg .= "\n房间号：" . $room_data["room_id"];
        $msg .= "\n卧底词：" . $room_data["undercover"];
        $msg .= "\n平民词：" . $room_data["civilian"];
        $msg .= "\n玩家数：" . $room_data["room_size"] . "人（卧底" . $room_data["undercover_num"] . "人）";
        $msg .= "\n卧底编号：";
        $s = [];
        for ($i = 1; $i <= $room_data["undercover_num"]; $i++) {
            $s[] = $room_data["undercover_" . $i];
        }
        $msg .= implode(", ", $s);
        $msg .= "\n玩家只需私聊炸毛回复房间号即可加入游戏！";
        $db = DP::connect();
        $db->query("UPDATE undercover_room SET undercover = '" . $db->real_escape_string($room_data["undercover"]) . "', civilian = '" . $db->real_escape_string($room_data["civilian"]) . "' WHERE room_id = " . $room_data["room_id"]);
        $this->reply($msg);
    }

    /**
     * 获取随机词
     * @param $first
     * @param $second
     */
    public function getRandomWords(&$first, &$second) {
        $file = DP::getJsonData("Undercover_words.json");
        $size = count($file);
        $r = mt_rand(0, $size - 1);
        $first = $file[$r]["wodi"];
        $second = $file[$r]["pingmin"];
    }

    /**
     * 更新玩家状态
     * @param $room_id
     * @param $id
     */
    public function setPlayerStatus($room_id, $id) {
        $db = DP::connect();
        $db->query("UPDATE undercover_user SET room_id = " . $db->real_escape_string($room_id) . ", player_num = " . $id . " WHERE user_id = '" . $db->real_escape_string($this->getUserId()) . "'");
        $msg = "*** 成功加入游戏 ***";
        $msg .= "\n房号：" . $room_id;
        $msg .= "\n编号：【" . $id . "号】";
        $room = $this->getRoom($room_id);
        if ($id == $room["undercover_1"] || $id == $room["undercover_2"] || $id == $room["undercover_3"])
            $msg .= "\n词语：" . $room["undercover"];
        else
            $msg .= "\n词语：" . $room["civilian"];
        $pingmin = $room["room_size"] - $room["undercover_num"];
        $msg .= "\n配置：" . $room["undercover_num"] . "个卧底， " . $pingmin . "个平民";
        $this->reply($msg);
    }

    /**
     * 获取房间信息
     * @param $room_id
     * @return array|null
     */
    public function getRoom($room_id) {
        $db = DP::connect();
        $r = $db->query("SELECT * FROM undercover_room WHERE room_id = " . $room_id);
        if ($r->num_rows == 0) return null;
        $s = $r->fetch_assoc();
        echo json_encode($s, 128 | 256);
        return $s;
    }
}