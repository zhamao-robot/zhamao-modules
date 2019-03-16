<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2019-03-14
 * Time: 17:26
 */

use DataProvider as DP;

class Eating extends ModBase
{
    public function __construct(CrazyBot $main, $data) { parent::__construct($main, $data); }

    public static function initValues() {
        ZMBuf::set("foods", DP::getJsonData("Eating_foods.json"));
        ZMBuf::set("user_taste", DP::getJsonData("Eating_taste.json"));
    }

    public static function saveValues() {
        DP::setJsonData("Eating_foods.json", ZMBuf::get("foods"));
        DP::setJsonData("Eating_taste.json", ZMBuf::get("user_taste"));
    }

    public function execute($it) {
        switch ($it[0]) {
            case "添加吃什么":
                if ($this->checkWechat()) return true;
                if (!isset($it[1])) {
                    $this->reply("小主人要往自己的菜单添加什么吃的呀？\n回复你想吃的即可，例如：小杨生煎");
                    $wait = $this->waitUserMessage($this->data);
                    if ($wait === null) return $this->reply("emm，你好像一直没回炸毛哦，如果还想添加吃什么的话，再次回复炸毛\"" . $it[0] . "\"就好了！");
                    $context = $this->getContext($wait);
                    if ($context == 3) return $this->reply("那好的吧，先不加了。");
                    if ($context != 4) return $this->reply("额，那炸毛先不添加了，你说的好像不是个吃的。。");
                } else $wait = $it[1];
                $wait = trim($wait);
                $a = ZMBuf::get("foods");
                $a["user"][$this->getUserId()][] = $wait;
                ZMBuf::set("foods", $a);
                return $this->reply("好的，炸毛已经将" . $wait . "添加到你的食谱！");
            case "中午吃什么":
            case "晚上吃什么":
            case "吃什么":
            case "吃点啥":
            case "吃点啥呢":
            case "吃啥":
                if ($this->checkWechat()) return true;
                if ($this->getUser()->getDhuer() !== null && $this->getUser()->getDhuer()->getClasstable() != [] && $this->getUser()->getDhuer()->getCampus() == "sj") {
                    $course = Course::getNextClass($this->getUserId(), time(), 1);
                    if ($course["id"] != "") {//判断是否有下节课
                        if (TimeManager::getTodayZero() == TimeManager::getTodayZero($course["timestamp"]) && in_array($course["course_data"]["time"][1], [5, 10])) {//判断是否是同一天
                            $this->reply("好像接下来还有课，你想去哪吃呢？\n一食堂、二食堂、其他？");
                            $wait = $this->waitUserMessage($this->data);
                            if ($wait === null) return $this->reply("额，你还在吗？？请重新回复：" . $it[0]);
                            if (mb_strpos($wait, "一食") !== false) $filter = ["include" => ["一食堂"]];
                            if (mb_strpos($wait, "二食") !== false) $filter = ["include" => ["二食堂"]];
                            if (mb_strpos($wait, "其他") !== false) $filter = ["exclude" => ["一食堂", "二食堂"]];
                        }
                    }
                }
                if ($this->getUser()->getDhuer() !== null && $this->getUser()->getDhuer()->getCampus() == "yal")
                    $filter = ["include" => ["user"]];
                $target = $this->getTargetList(false, $filter ?? []);
                $choice_time = 0;
                if ($target == []) return $this->reply("阿咧，你的食谱是空的，炸毛还怎么给你选啊，快去添加一些你想吃的吧！\n回复: 添加吃什么");
                shuffle($target);
                $this->reply($this->getRandomReply("think"));
                co::sleep(1);
                $food_item = $target[1] ?? $target[0];
                $this->reply($this->getRandomReply("decide", $food_item));
                $wait = $this->waitUserMessage($this->data);
                if ($wait === null) return true;
                $context = $this->getContext($wait);
                ++$choice_time;
                while ($context == 0 || $context == 1) {
                    if ($choice_time > 6) return $this->reply("选了这么多次，炸毛也不知道了，那小主人自己做决定吧！");
                    if ($context == 1) {
                        $user = ZMBuf::get("user_taste");
                        $user[$this->getUserId()][] = $food_item[1];
                        ZMBuf::set("user_taste", $user);
                        $this->reply("已从你的食谱中删除[" . $food_item[1] . "]。");
                        $target = $this->getTargetList();
                    }
                    $this->reply($this->getRandomReply("unlike-think"));
                    co::sleep(1);

                    shuffle($target);
                    $food_item = $target[1] ?? $target[0];
                    $this->reply($this->getRandomReply("decide", $food_item));
                    $wait = $this->waitUserMessage($this->data);
                    if ($wait === null) return true;
                    $context = $this->getContext($wait);
                    ++$choice_time;
                }
                if ($context == 2) return $this->reply($this->getRandomReply("ok"));
                if ($context == 3) return $this->reply($this->getRandomReply("unlike"));
                if ($context == 4) return $this->reply("唔，炸毛有点懵，那小主人自己做决定吃啥吧！");
                return true;
        }
        return false;
    }

    private function getRandomReply($mode, $params = []) {
        switch ($mode) {
            case "think":
                $reply = [
                    "嗯，等会儿，让炸毛先想想去哪儿吃",
                    "让炸毛想想啊，吃啥呢？",
                    "今天吃什么呢？"
                ];
                $r = array_rand($reply);
                return $reply[$r];
            case "decide":
                $reply = [
                    ($params[0] != "user" ? "去" . $params[0] : "") . "吃" . $params[1] . "吧，怎么样？",
                    "要不今天吃" . ($params[0] != "user" ? $params[0] . "的" : "") . $params[1] . "吧？",
                    "你想去吃" . ($params[0] != "user" ? $params[0] . "的" : "") . $params[1] . "吗？"
                ];
                $r = array_rand($reply);
                $re = $reply[$r] . "\n*如果你不喜欢吃这个，可以回\"不喜欢\"，炸毛以后将排除。\n*如果想换一个请回\"换一个\"";
                return $re;
            case "unlike-think":
                $reply = [
                    "嗯。。不喜欢啊，那让我再想想。",
                    "额，那我再想想",
                    "噗，稍等，炸毛再帮你选一个",
                    "那，炸毛也不知道了"
                ];
                $r = array_rand($reply);
                return $reply[$r];
            case "ok":
                $reply = [
                    "好的，小主人",
                    "好的呀！",
                    "那好",
                    "OK的！",
                    "好，小主人喜欢就好啦！"
                ];
                return $reply[array_rand($reply)];
            case "unlike":
                $reply = [
                    "嗯。。小主人不喜欢的话，炸毛也不知道了，那小主人你自己决定吧～",
                    "啊，不喜欢吗？那，炸毛也不知道了。",
                    "那小主人自己做决定吧！",
                    "小主人看来不想吃这个吗？那就自己做决定吧！"
                ];
                return $reply[array_rand($reply)] . "\n如果炸毛的默认食谱里没有你想吃的，可以使用\"添加吃什么\"来加入你想吃的，以后就可以随机了";
        }
        return null;
    }

    /**
     * @param bool $shuffle
     * @param array $filter
     * @return array
     */
    private function getTargetList($shuffle = false, $filter = []) {
        $foods = ZMBuf::get("foods");
        $user = ZMBuf::get("user_taste");
        $target = [];
        foreach ($foods as $type => $v) {
            if ($filter != [] && isset($filter["include"])) {
                if (!in_array($type, $filter["include"])) continue;
            } elseif ($filter != [] && isset($filter["exclude"])) {
                if (in_array($type, $filter["exclude"])) continue;
            }
            if ($type == "user") {
                $v = $v[$this->getUserId()] ?? [];
            }
            foreach ($v as $ks => $vs) {
                if (in_array($vs, $user[$this->getUserId()] ?? [])) continue;
                $target[] = [$type, $vs];
            }

        }
        if ($shuffle === true) shuffle($target);
        return $target;
    }

    /**
     * 0: 换一个(继续筛选)
     * 1: 不喜欢(排除并继续筛选)
     * 2: 肯定(结束筛选并)
     * 3: 不肯定(结束筛选)
     * 4: 不知道你说了什么(结束筛选)
     * @param $wait
     * @return int
     */
    private function getContext($wait) {
        $wait = trim($wait);
        if ($wait == "不喜欢") return 1;
        if ($wait == "换一个" || $wait == "再来一个") return 0;
        if (mb_strpos($wait, "不") === 0 ||
            mb_strpos($wait, "算了") !== false
        ) return 3;
        if (mb_strpos($wait, "可以") !== false ||
            mb_strpos($wait, "好") !== false ||
            mb_strpos($wait, "行") !== false ||
            mb_strpos($wait, "没问题") !== false ||
            mb_strpos($wait, "嗯") !== false ||
            mb_strpos($wait, "就这个") !== false
        ) return 2;
        return 4;
    }
}
