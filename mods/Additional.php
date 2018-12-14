<?php

use DataProvider as DP;

/**
 * Class Additional
 * 其他零碎的功能模块
 */
class Additional extends ModBase
{
    public function __construct(CrazyBot $main, $data) { parent::__construct($main, $data); }

    public function execute($it) {
        if ($this->main->isFunctionCalled()) return false;
        switch ($it[0]) {
            case "二维码":
                if (!isset($it[1])) {
                    $this->reply("用法：\n二维码 xxx\n例如生成一个打开百度的二维码：\n二维码 https://www.baidu.com");
                    return true;
                }
                if ($it[1] == "") {
                    $this->reply("未输入任何文本哦～无法转换！");
                    return true;
                }
                array_shift($it);
                $it = urlencode(implode(" ", $it));
                if (strlen($it) >= 150) {
                    $this->reply("内容过长");
                    return true;
                }
                $this->reply(CQ::image("http://qr.liantu.com/api.php?gc=cc00000&text=" . $it));
                return true;
            case "timestamp":
            case "时间戳":
                if(count($it) < 2){
                    $this->reply("用法：时间戳 timestamp");
                    return true;
                }
                $timestamp = $it[1];
                if(($date = date("Y-m-d H:i:s", $timestamp)) !== false){
                    $this->reply($date);
                }
                return true;
            case "归属地":
                if (count($it) < 2) return true;
                $num = substr($it[1], 0, 7);
                $ls = file_get_contents(DP::getDataFolder() . "Additional_phone.txt");
                $ls = explode("\n", $ls);
                foreach ($ls as $k => $v) {
                    $p = explode(",", $v);
                    if ($num == $p[0]) {
                        $this->reply($p[1]);
                        return true;
                    }
                }
                $this->reply("no matches.");
                return true;
        }
        return false;
    }
}