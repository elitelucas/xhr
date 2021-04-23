<?php
/*
 * 参数过滤
 * */

/*
 * 获取参数
 * @parame $parame_name 参数名
 * @parame $inputOpt 是否为必填参数     1：是  0：否
 * @parame $default 非必填参数缺省值
 * @parame $parameFunc 参数校验类型/方法    通过匿名函数扩展校验方法
 * @parame $errMsg 错误提示内容 ['为空时的错误提示', '格式不正确时的错误提示']
 * @parame $returnType 错误信息返回函数  因为目前返回格式不统一,所以通过匿名函数来支持各种格式的返回
 * */
function getParame($parame_name, $inputOpt = 1, $default = '', $parameFunc = 'str', $errMsg = [], $returnFunc = '') {
    !$returnFunc && $returnFunc = "getReturn";

    if(!function_exists($returnFunc))
        getReturn(400, 'returnFunc undefined!k');

    $parame_get = $_GET;
    $parame_post = $_POST;
    $parame_form = file_get_contents("php://input");
    $parame_val = false;
    if(!$parame_val && isset($parame_get[$parame_name]) && false !== $parame_get[$parame_name]) $parame_val = $parame_get[$parame_name];
    if(!$parame_val && isset($parame_post[$parame_name]) && false !== $parame_post[$parame_name]) $parame_val = $parame_post[$parame_name];
    if(!$parame_val && isset($parame_form[$parame_name]) && false !== $parame_form[$parame_name]) $parame_val = $parame_form[$parame_name];

    //非必填参数为空 返回默认值
    if(!$inputOpt && (false === $parame_val || '' === $parame_val))
        return $default;

    if(!is_array($errMsg) && $errMsg) $errMsg[0] = $errMsg;

    //必填参数为空
    lg('parame_log',var_export(array(
        '$parame_get'=>$parame_get,
        '$parame_post'=>$parame_post,
        '$parame_form'=>$parame_form,
        '$parame_val'=>$parame_val,
        '!$parame_val'=>!$parame_val,
        'isset($parame_val)'=>isset($parame_val),
    ),1));
	if(false === $parame_val || '' === $parame_val) {
        !$errMsg && $errMsg[0] = '缺少参数'.$parame_name;
        $returnFunc(1709, $errMsg[0]);
    }

    //参数不为空，校验参数类型
    if(!isset($errMsg[1]) || !$errMsg[1]) $errMsg[1] = '参数 '.$parame_name.' 格式不正确! ';
    switch ($parameFunc) {
        case 'string':
        case 'str':
            if(!is_string($parame_val)) $returnFunc(1708, $errMsg[1]);
        break;
        case 'int':
            if(!preg_match("/^\d*$/",$parame_val)) $returnFunc(1708, $errMsg[1]);
//            $parame_val_filter = (int)$parame_val;
//            if($parame_val_filter != $parame_val || !is_integer($parame_val_filter)) $returnFunc(1708, $errMsg[1]);
            break;
        case 'phone':
            break;
        case 'email':
            break;
        case 'float':
            break;
        default:
            if(function_exists($parameFunc)) {
                !$parameFunc($parame_val) && $returnFunc(1708, $errMsg[1]);
            }else {
                $returnFunc(1708, '校验类型错误! ');
            }
            break;
    }

    return $parame_val;
}


//百人牛牛参数过滤
function bairennn_input_filter($val) {
    if (!preg_match('/^(?:方块|梅花|红心|黑桃)(?:[2-9]|10|[AJQK])$/i', $val)) {
        return false;
    }
    return true;
}

//小数位最多2位
function decimalMax2($val) {
    if (preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $val, $data)) {
        return true;
    }else
        return false;
}

//手机号码
function checkPhone($val) {
    if(preg_match("^((0\d{2,3})-)(\d{7,8})(-(\d{3,}))?$",$val))
        return true;
    return false;
}

//邮箱地址
function checkEmail($val){

}

function getReturn($code, $errMsg = '', $returnType = 0) {
    if($returnType == 0) {
        jsonReturn(array(
            'status' => $code,
            'ret_msg' => $errMsg
        ));
    }
}


function checkLongNum($val) {
    if (!preg_match('/^\d+$/', $val)) {
        return false;
    }
    return true;
}


function odds_return($code, $errMsg = '') {
    echo json_encode(array("rt" => -100, 'ret_msg' => $errMsg));
    exit;
}
?>