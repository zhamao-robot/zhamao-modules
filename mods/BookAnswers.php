<?php

use DataProvider as DP;

/**
 * Class BookAnswers
 * 答案之书模块
 */
class BookAnswers extends ModBase
{
    public function __construct(CrazyBot $main, $data) { parent::__construct($main, $data); }

    public function execute($it) {
        if ($this->main->isFunctionCalled()) return false;
        switch ($it[0]) {
            case "我的答案":
                $ls = DP::getJsonData("BookAnswers_mysoul.json");
                $answer = $ls[array_rand($ls)];
                $this->reply("你的答案是：" . $answer);
                return true;
            case "添加答案":
                if (!$this->main->isAdmin($this->getUserId())) {
                    return false;
                }
                if (!isset($it[1])) {
                    $this->reply("未输入关键词！");
                    return true;
                }
                $answer = $it[1];
                $dt = DP::getJsonData("mysoul.json");
                $dt[] = $answer;
                DP::setJsonData("mysoul.json", $dt);
                $this->reply("成功添加答案！");
                return true;
        }
        return false;
    }
}
