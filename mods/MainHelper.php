<?php

/**
 * Class MainHelper
 * 帮助模块
 */
class MainHelper extends ModBase
{
    const mod_level = 30;

    public function __construct(CrazyBot $main, $data) { parent::__construct($main, $data); }

    public function execute($it) {
        switch (array_shift($it)) {
            case "?":
            case "帮助":
            case "菜单":
            case "功能":
            if (empty($it)) {
                $this->reply(CQ::image(HTTP_ADDRESS . "?pict_type=zm&file=main_help_" . mt_rand(1, 9) . ".jpg"));
                return true;
            }
            switch ($it[0]) {
                default:
                    $this->reply(CQ::image(HTTP_ADDRESS . "?pict_type=zm&file=main_help_" . mt_rand(1, 9) . ".jpg"));
                    return true;
            }
            case "语音识别":
                $msg = "「语音识别帮助」";
                $msg .= "\n炸毛的语音识别功能可以直接把你的语音转换为文本处理，如果含有激活炸毛功能的关键词，则不输出文本而是调用相应功能";
                $msg .= "\n回复：开启转换";
                $msg .= "\n即可开启语音转换功能，你发的每一条语音都将经过炸毛的转换";
                $msg .= "\n回复：关闭转换，即可关闭转换模式";
                $msg .= "\n" . CQ::image(HTTP_ADDRESS . "?pict_type=zm&file=voice_helper.jpg");
                $this->reply($msg);
                return true;
            case "刷题":
            case "题库":
                $msg = "「刷题系统帮助」\n";
                $msg .= "现提供的刷题题库有：\n";
                $msg .= "「近代史题库」，「思修题库」，「毛概二题库」，「马克思题库」\n";
                $msg .= "如需开始做题，请私聊炸毛回复题库名称，例如：近代史题库\n";
                $msg .= "也可以做自己的错题，回复：xx题库 错题\n或随机做题，回复： xx题库 随机\n如果在使用题库过程中有任何疑问或错题，欢迎反馈或提出意见！\n回复：题库反馈 xxx";
                $this->reply($msg);
                $this->reply("炸毛将在15周左右完成题库的更新，更新完会通知大家！");
                return true;
            case "娱乐功能":
                $msg = CQ::image(HTTP_ADDRESS . "?pict_type=zm&file=entertain_help.jpg");
                $this->reply($msg);
                return true;
            case "翻译功能":
                $this->reply(CQ::image(HTTP_ADDRESS . "?pict_type=zm&file=translate_help.jpg"));
                return true;
            case "课表功能":
            case "查课功能":
            case "我的课表":
            case "课表":
            case "怎么导入课表":
            case "查看课表":
                $this->reply(CQ::image(HTTP_ADDRESS . "?pict_type=zm&file=course_help.jpg"));
                return true;
            case "翻译语言":
                $this->reply(CQ::image(HTTP_ADDRESS . "?pict_type=zm&file=translate_lang_help.jpg"));
                return true;
            case "成语接龙游戏":
                $msg = "「成语接龙帮助」\n";
                $msg .= "成语接龙是包含在智能聊天功能中的游戏，具体规则如下：\n私聊直接回复炸毛：成语接龙，即可";
                $this->reply($msg);
                return true;
            case "智能聊天":
                $msg = "「智能聊天帮助」\n智能聊天采用先进的图灵中文词库，可以根据你的内容智能回复内容。你可以和炸毛聊天，也可以问炸毛天气，也可以和炸毛玩成语接龙，甚至问炸毛新闻，用炸毛计算算式等～\n使用方法：\n群聊对话时，直接艾特炸毛+你想说的话即可\n私聊对话时，说的话前面带个*号即可对话\n或者回复：炸毛，xxx\n";
                $msg .= CQ::image(HTTP_ADDRESS . "?pict_type=zm&file=turing_helper.jpg");
                $this->reply($msg);
                $this->reply("此功能现已默认开启，和炸毛直接对话即可，功能关键词会识别为功能，其他语句会默认当作对话哦～");
                return true;
            case "私人词库":
                if ($this->main->data["message_type"] == "wechat") {
                    $this->reply("微信暂不支持此功能，敬请期待！");
                    return true;
                }
                $this->reply(CQ::image(HTTP_ADDRESS . "?pict_type=zm&file=lexicon_help.jpg"));
                return true;
            case "订阅功能":
                if ($this->main->data["message_type"] == "wechat") {
                    $this->reply("微信暂不支持此功能，敬请期待！");
                    return true;
                }
                $this->reply(CQ::image(HTTP_ADDRESS . "?pict_type=zm&file=subsc_help.jpg"));
                return true;
            case "支持炸毛":
                $this->reply(CQ::image(HTTP_ADDRESS . "?pict_type=zm&file=wechat.jpg") . CQ::image(HTTP_ADDRESS . "?pict_type=zm&file=alipay.jpg"));
                $this->reply("如果炸毛给你带来了方便和快乐，不妨支持一下炸毛~\n炸毛不会推出任何第三方广告以及付费服务，欢迎赞助支持炸毛机器人的持续开发！");
                return true;
            case "其他功能":
                $msg = CQ::image(HTTP_ADDRESS . "?pict_type=zm&file=other_help.jpg");
                $this->reply($msg);
                return true;
            case "答案之书":
                $msg = "「Book of Answers」";
                $msg .= "\n当你有一个疑问或烦恼需要解答时";
                $msg .= "\n闭上双眼，默念10秒，心中想着问题";
                $msg .= "\n然后将书随意翻开一页，得到你的答案";
                $msg .= "\n炸毛版只需默念10秒，然后回复炸毛：";
                $msg .= "\n我的答案";
                $msg .= "\n即可获取自己命中注定的答案";
                $msg .= "\nps：纯属娱乐，切勿过度当真哦～";
                $this->reply($msg);
                return true;
            case "音乐查询":
                if ($this->main->data["message_type"] == "wechat") {
                    $this->reply("微信暂不支持此功能，敬请期待！");
                    return true;
                }
                $this->reply(CQ::image(HTTP_ADDRESS . "?pict_type=zm&file=music_help.jpg"));
                return true;
            case "关于炸毛":
                $this->reply(CQ::image(HTTP_ADDRESS . "?pict_type=zm&file=about_zhamao.jpg"));
                return true;
            case "实验性功能":
                $this->reply("暂未开放，敬请期待！");
                return true;
            case "计划任务":
                $msg = CQ::image(HTTP_ADDRESS . "?pict_type=zm&file=jihua_help.jpg");
                $this->reply($msg);
                return true;
            default:
                if ($this->getMessageType() != "wechat" && (trim($this->data["message"]) == "[CQ:at,qq=" . $this->data["self_id"] . "]")) {
                    $this->reply("听着呢，有事吗？\n炸毛使用帮助查看请回复：帮助");
                    return true;
                }
        }
        return false;
    }
}
