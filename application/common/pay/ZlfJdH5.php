<?php
/**
 * 掌灵付微信H5
 * @author mapeijian
 */
namespace app\common\pay;
use think\Request;
use app\common\Pay;
use Util\Zlf\itpPayH5;
class ZlfJdH5 extends Pay
{

    protected $gateway = 'http://trans.palmf.cn/sdk/api/v1.0/cli/order_h5/0';
    protected $code='';
    protected $error='';

    public function getCode()
    {
        return $this->code;
    }

    public function getError()
    {
        return $this->error;
    }

    public function order($outTradeNo,$subject,$totalAmount)
    {

        /* *
 * 请求参数，参数须按字符升序排列
 */
        $parameter=array(
            "amount"         =>$totalAmount*100,//[必填]订单总金额，单位(分)
            "appid"          =>$this->account->params->appid,//[必填]//交易发起所属app
            "body"           =>$subject,//[必填]商品描述
            "clientIp"       =>Request::instance()->ip(),//[必填]客户端IP
            "mchntOrderNo"   =>$outTradeNo,//[必填]商户订单号，必须唯一性
            "notifyUrl"      =>Request::instance()->domain().'/pay/notify/ZlfJdH5',//[必填]订单支付结果异步通知地址，用于接收订单支付结果通知，必须以http或https开头
            "returnUrl"      =>Request::instance()->domain().'/orderquery?orderid='.$outTradeNo,//[必填]订单支付结果同步跳转地址，用于同步跳转到商户页面，必须以http或https开头
            "subject"        =>$subject,//[必填]商品名称
            "openId"         =>"",
            "version"        =>"h5_NoEncrypt",//接口版本号，值为pc_NoEncrypt时,则明天平台返回商户参数时，不进行RSA加密
            "payChannelId"   =>"0000000015",//支付渠道id
            "description"    =>"",
            "extra"          =>"",
            "expireMs"       =>"",
            "cpChannel"      =>"",
            "currency"       =>"",
        );

        $itppay_config["appid"] = $this->account->params->appid;//交易发起所属app
        $itppay_config["key"] = $this->account->params->key;//合作密钥
        /* *
         * 建立请求
         */
        $itpPay = new itpPayH5($itppay_config);
        $orderInfo=$itpPay->getOrderInfo($parameter);
        $orderInfo = str_replace("+","%2B",$orderInfo);



        $html=$itpPay->RequestForm($orderInfo, "点我进行支付...");

        $this->code    =0;
        $obj           =new \stdClass();
        $obj->pay_url  = $html;
        $obj->content_type = 3;
        return $obj;
    }

    /**
     * 页面回调
     */
    public function page_callback($params,$order)
    {
        header("Location:" . url('/orderquery',['orderid'=>$order->trade_no]));
    }

    /**
     * 服务器回调
     */
    public function notify_callback($params,$order)
    {
        $data = file_get_contents("php://input");

        $parameter = json_decode($data, true);
        $signature = $parameter["signature"];
        unset($parameter["signature"]);
        $itppay_config["appid"] = $this->account->params->appid;
        $itppay_config["key"] = $this->account->params->key;
        $itpPay = new itpPayH5($itppay_config);
        $signature_local = $itpPay->setSignature($parameter);
        if($signature && $signature == $signature_local){
            // 金额异常检测
            if($order->total_price!=$params['amount']/100){
                record_file_log('ZlfJdH5_notify_error','金额异常！'."\r\n".$order->trade_no."\r\n订单金额：{$order->total_price}，已支付：{$params['total_amount']}");
                die('金额异常！');
            }
            if($parameter["paySt"] == 2) {
                $order->transaction_id =$params['orderNo'];
                $this->completeOrder($order);
                echo json_encode(['success'=>'true']);
                return true;
            } else {
                exit('fail');
            }
        } else {
            exit('fail');
        }
    }
}
?>