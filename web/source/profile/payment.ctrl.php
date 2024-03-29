<?php
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */
defined('IN_IA') or exit('Access Denied');

load()->model('payment');
load()->model('account');
load()->func('communication');

$dos = array('save_setting', 'display', 'test_alipay', 'get_setting', 'switch');
$do = in_array($do, $dos) ? $do : 'display';
permission_check_account_user('profile_pay_setting');
$_W['page']['title'] = '支付参数 - 公众号选项';

// 获取支付配置
if ($do == 'get_setting') {
    $pay_setting = payment_setting();
    iajax(0, $pay_setting, '');
}

// 支付宝支付测试
if ($do == 'test_alipay') {
    $alipay = $_GPC['param'];
    $pay_data = array(
        'uniacid' => $_W['uniacid'],
        'acid' => $_W['acid'],
        'uniontid' => date('Ymd', time()) . time(),
        'module' => 'system',
        'fee' => '0.01',
        'status' => 0,
        'card_fee' => 0.01
    );
    $params = array();
    $params['tid'] = md5(uniqid());
    $params['user'] = '测试用户';
    $params['fee'] = '0.01';
    $params['title'] = '测试支付接口';
    $params['uniontid'] = $pay_data['uniontid'];

    $result = alipay_build($params, $alipay);
    iajax(0, $result['url'], '');
}

// 保存支付配置，对于支付宝，微信等，需要先配置，然后进入switch界面设置是否开启充值和付款操作
if ($do == 'save_setting') {
    $type = $_GPC['type'];
    $param = $_GPC['param'];
    $setting = uni_setting_load('payment', $_W['uniacid']);
    $pay_setting = $setting['payment'];

    // 余额、货到付款、混合支付
    if ($type == 'credit' || $type == 'delivery' || $type == 'mix') {
        $param['recharge_switch'] = false; //永远不支持充值操作
        $param['pay_switch'] = $param['pay_switch'] == 'true' ? true : false; // 用户设置是否支持
    }

    // 一码支付
    if ($type == 'jueqiymf') {
        $param['pay_switch'] = $param['pay_switch'] == 'true' ? true : false; // 用户设置是否支持支付操作
        $param['recharge_switch'] = $param['recharge_switch'] == 'true' ? true : false;// 用户设置是否支持充值操作
    }

    // 支付宝、百度钱包、汇款支付
    if ($type == 'alipay' || $type == 'baifubao' || $type == 'line') {
        $param['pay_switch'] = $param['pay_switch'] == 'true' ? true : false;
        $param['recharge_switch'] = $param['recharge_switch'] == 'true' ? true : false;
    }

    // 服务商支付
    if ($type == 'wechat_facilitator') {
        $param['switch'] = $param['switch'] == 'true' ? true : false;
    }

    // 微信支付
    if ($type == 'wechat') {
        $param['account'] = $_W['acid'];
        if ($param['switch'] == 1) {
            $param['signkey'] = $param['version'] == 2 ? trim($param['apikey']) : trim($param['signkey']);
        }
        $param['pay_switch'] = $param['pay_switch'] == 'true' ? true : false;
        $param['recharge_switch'] = $param['recharge_switch'] == 'true' ? true : false;
    }

    // 银联支付
    if ($type == 'unionpay') {
        $unionpay = $_GPC['unionpay'];
        $switch_status = ($unionpay['pay_switch'] || $unionpay['recharge_switch']) ? true : false;
        if ($switch_status && empty($_FILES['unionpay']['tmp_name']['signcertpath']) && !file_exists(IA_ROOT . '/attachment/unionpay/PM_' . $_W['uniacid'] . '_acp.pfx')) {
            itoast('请上联银商户私钥证书.', referer(), 'error');
        }
        $param = array(
            'pay_switch' => $unionpay['pay_switch'] == 'false' ? false : true,
            'recharge_switch' => $unionpay['recharge_switch'] == 'false' ? false : true,
            'merid' => $unionpay['merid'],
            'signcertpwd' => $unionpay['signcertpwd']
        );
        if ($switch_status && (empty($param['merid']) || empty($param['signcertpwd']))) {
            itoast('请输入完整的银联支付接口信息.', referer(), 'error');
        }
        if ($switch_status && empty($_FILES['unionpay']['tmp_name']['signcertpath']) && !file_exists(IA_ROOT . '/attachment/unionpay/PM_' . $_W['uniacid'] . '_acp.pfx')) {
            itoast('请上传银联商户私钥证书.', referer(), 'error');
        }
        if ($switch_status && !empty($_FILES['unionpay']['tmp_name']['signcertpath'])) {
            load()->func('file');
            mkdirs(IA_ROOT . '/attachment/unionpay/');
            file_put_contents(IA_ROOT . '/attachment/unionpay/PM_' . $_W['uniacid'] . '_acp.pfx', file_get_contents($_FILES['unionpay']['tmp_name']['signcertpath']));
            $public_rsa = '-----BEGIN CERTIFICATE-----
MIIEIDCCAwigAwIBAgIFEDRVM3AwDQYJKoZIhvcNAQEFBQAwITELMAkGA1UEBhMC
Q04xEjAQBgNVBAoTCUNGQ0EgT0NBMTAeFw0xNTEwMjcwOTA2MjlaFw0yMDEwMjIw
OTU4MjJaMIGWMQswCQYDVQQGEwJjbjESMBAGA1UEChMJQ0ZDQSBPQ0ExMRYwFAYD
VQQLEw1Mb2NhbCBSQSBPQ0ExMRQwEgYDVQQLEwtFbnRlcnByaXNlczFFMEMGA1UE
Aww8MDQxQDgzMTAwMDAwMDAwODMwNDBA5Lit5Zu96ZO26IGU6IKh5Lu95pyJ6ZmQ
5YWs5Y+4QDAwMDE2NDkzMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA
tXclo3H4pB+Wi4wSd0DGwnyZWni7+22Tkk6lbXQErMNHPk84c8DnjT8CW8jIfv3z
d5NBpvG3O3jQ/YHFlad39DdgUvqDd0WY8/C4Lf2xyo0+gQRZckMKEAId8Fl6/rPN
HsbPRGNIZgE6AByvCRbriiFNFtuXzP4ogG7vilqBckGWfAYaJ5zJpaGlMBOW1Ti3
MVjKg5x8t1/oFBkpFVsBnAeSGPJYrBn0irfnXDhOz7hcIWPbNDoq2bJ9VwbkKhJq
Vz7j7116pziUcLSFJasnWMnp8CrISj52cXzS/Y1kuaIMPP/1B0pcjVqMNJjowooD
OxID3TZGfk5V7S++4FowVwIDAQABo4HoMIHlMB8GA1UdIwQYMBaAFNHb6YiC5d0a
j0yqAIy+fPKrG/bZMEgGA1UdIARBMD8wPQYIYIEchu8qAQEwMTAvBggrBgEFBQcC
ARYjaHR0cDovL3d3dy5jZmNhLmNvbS5jbi91cy91cy0xNC5odG0wNwYDVR0fBDAw
LjAsoCqgKIYmaHR0cDovL2NybC5jZmNhLmNvbS5jbi9SU0EvY3JsMjI3Mi5jcmww
CwYDVR0PBAQDAgPoMB0GA1UdDgQWBBTEIzenf3VR6CZRS61ARrWMto0GODATBgNV
HSUEDDAKBggrBgEFBQcDAjANBgkqhkiG9w0BAQUFAAOCAQEAHMgTi+4Y9g0yvsUA
p7MkdnPtWLS6XwL3IQuXoPInmBSbg2NP8jNhlq8tGL/WJXjycme/8BKu+Hht6lgN
Zhv9STnA59UFo9vxwSQy88bbyui5fKXVliZEiTUhjKM6SOod2Pnp5oWMVjLxujkk
WKjSakPvV6N6H66xhJSCk+Ref59HuFZY4/LqyZysiMua4qyYfEfdKk5h27+z1MWy
nadnxA5QexHHck9Y4ZyisbUubW7wTaaWFd+cZ3P/zmIUskE/dAG0/HEvmOR6CGlM
55BFCVmJEufHtike3shu7lZGVm2adKNFFTqLoEFkfBO6Y/N6ViraBilcXjmWBJNE
MFF/yA==
-----END CERTIFICATE-----';
            file_put_contents(IA_ROOT . '/attachment/unionpay/UpopRsaCert.cer', trim($public_rsa));
        }
    }

    $pay_setting[$type] = $param;
    $payment = iserializer($pay_setting);
    uni_setting_save('payment', $payment);

    if ($type == 'wechat_facilitator') {
        cache_clean(cache_system_key('proxy_wechatpay_account:'));
    }

    if ($type == 'unionpay') {
        header('LOCATION: ' . url('profile/payment'));
        exit();
    }
    iajax(0, '');
}

// 进入支持设置界面
if ($do == 'display' || $do == 'switch') {
    $proxy_wechatpay_account = account_wechatpay_proxy();
    $pay_setting = payment_setting();
    $accounts = array();
    $accounts[$_W['acid']] = array_elements(array('name', 'acid', 'key', 'secret', 'level'), $_W['account']);
}
if ($do == 'switch') {
    $payment_types = payment_types();
    if (empty($payment_types[$_GPC['type']])) {
        itoast('参数错误', url('profile/payment'), 'error');
    }
}

template('profile/payment');