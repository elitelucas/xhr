<?php
$allowTestMenu = true;
$use_mysqli = function_exists("mysqli_connect");
header("Content-Type: text/plain; charset=x-user-defined");
error_reporting(0);
set_time_limit(0);
function phpversion_int()
{
    list($maVer, $miVer, $edVer) = preg_split("(/|\.|-)", phpversion());
    return $maVer * 10000 + $miVer * 100 + $edVer;
}
function GetLongBinary($num)
{
    return pack("N", $num);
}
function GetShortBinary($num)
{
    return pack("n", $num);
}
function GetDummy($count)
{
    $str = "";
    for ($i = 0; $i < $count; $i++)
        $str .= "\x00";
    return $str;
}
function GetBlock($val)
{
    $len = strlen($val);
    if ($len < 254)
        return chr($len) . $val;
    else
        return "\xFE" . GetLongBinary($len) . $val;
}
function EchoHeader($errno)
{
    $str = GetLongBinary(1111);
    $str .= GetShortBinary(202);
    $str .= GetLongBinary($errno);
    $str .= GetDummy(6);
    echo $str;
}
function EchoConnInfo($conn)
{
    $str = GetBlock($conn->getAttribute(PDO::ATTR_CONNECTION_STATUS));
    $str .= GetBlock($conn->getAttribute(PDO::ATTR_CLIENT_VERSION));
    $str .= GetBlock($conn->getAttribute(PDO::ATTR_SERVER_VERSION));
    echo $str;
}
function EchoResultSetHeader($errno, $affectrows, $insertid, $numfields, $numrows)
{
    $str = GetLongBinary($errno);
    $str .= GetLongBinary($affectrows);
    $str .= GetLongBinary($insertid);
    $str .= GetLongBinary($numfields);
    $str .= GetLongBinary($numrows);
    $str .= GetDummy(12);
    echo $str;
}
function EchoFieldsHeader($res, $numfields)
{
    $str = "";
    for ($i = 0; $i < $numfields; $i++) {
        $finfo = $res->getColumnMeta($i);
        $str .= GetBlock($finfo['name']);
        $str .= GetBlock($finfo['table']);
        $type = strtolower($finfo['native_type']);
        $length = $finfo['len'];
        switch ($type) {
            case "int":
                if ($length > 11) $type = 8;
                else $type = 3;
                break;
            case "real":
                if ($length == 12) $type = 4;
                elseif ($length == 22) $type = 5;
                else $type = 0;
                break;
            case "null":
                $type = 6;
                break;
            case "timestamp":
                $type = 7;
                break;
            case "date":
                $type = 10;
                break;
            case "time":
                $type = 11;
                break;
            case "datetime":
                $type = 12;
                break;
            case "year":
                $type = 13;
                break;
            case "blob":
                if ($length > 16777215) $type = 251;
                elseif ($length > 65535) $type = 250;
                elseif ($length > 255) $type = 252;
                else $type = 249;
                break;
            default:
                $type = 253;
        }
        $str .= GetLongBinary($type);
        $flags = $finfo['flags'];
        $intflag = 0;
        if (in_array("not_null", $flags)) $intflag += 1;
        if (in_array("primary_key", $flags)) $intflag += 2;
        if (in_array("unique_key", $flags)) $intflag += 4;
        if (in_array("multiple_key", $flags)) $intflag += 8;
        if (in_array("blob", $flags)) $intflag += 16;
        if (in_array("unsigned", $flags)) $intflag += 32;
        if (in_array("zerofill", $flags)) $intflag += 64;
        if (in_array("binary", $flags)) $intflag += 128;
        if (in_array("enum", $flags)) $intflag += 256;
        if (in_array("auto_increment", $flags)) $intflag += 512;
        if (in_array("timestamp", $flags)) $intflag += 1024;
        if (in_array("set", $flags)) $intflag += 2048;
        $str .= GetLongBinary($intflag);
        $str .= GetLongBinary($length);
    }
    echo $str;
}
function EchoData($res)
{
    while ($row = $res->fetch(PDO::FETCH_NUM)) {
        $str = "";

        foreach ($row as $item) {
            if (is_null($item))
                $str .= "\xFF";
            else
                $str .= GetBlock($item);
        }
        echo $str;
    }
}
function doSystemTest()
{
    function output($description, $succ, $resStr)
    {
        echo "<tr><td class=\"TestDesc\">$description</td><td ";
        echo ($succ) ? "class=\"TestSucc\">$resStr[0]</td></tr>" : "class=\"TestFail\">$resStr[1]</td></tr>";
    }
    output("PHP version >= 4.0.5", phpversion_int() >= 40005, ["Yes", "No"]);
    output("PDO available", class_exists("PDO"), ["Yes", "No"]);
    if (phpversion_int() >= 40302 && substr($_SERVER["SERVER_SOFTWARE"], 0, 6) == "Apache" && function_exists("apache_get_modules")) {
        if (in_array("mod_security2", apache_get_modules()))
            output("Mod Security 2 installed", false, ["No", "Yes"]);
    }
}
if (phpversion_int() < 40005) {
    EchoHeader(201);
    echo GetBlock("unsupported php version");
    exit();
}
if (phpversion_int() < 40010) {
    global $HTTP_POST_VARS;
    $_POST = &$HTTP_POST_VARS;
}
$testMenu = false;
if (!isset($_POST["actn"]) || !isset($_POST["host"]) || !isset($_POST["port"]) || !isset($_POST["login"])) {
    $testMenu = $allowTestMenu;
    if (!$testMenu) {
        EchoHeader(202);
        echo GetBlock("invalid parameters");
        exit();
    }
}
if (!$testMenu) {
    if (isset($_POST["encodeBase64"]) && $_POST["encodeBase64"] == '1') {
        for ($i = 0; $i < count($_POST["q"]); $i++)
            $_POST["q"][$i] = base64_decode($_POST["q"][$i]);
    }
    if (!class_exists("PDO")) {
        EchoHeader(203);
        echo GetBlock("MySQL not supported on the server");
        exit();
    }
    if (!in_array('mysql', pdo_drivers())) {
        EchoHeader(203);
        echo GetBlock("pdo_mysql not install on the server");
        exit();
    }
    $errno = 0;
    $hs = $_POST["host"];
    try {
        $conn = new PDO("mysql:host=$hs;port={$_POST['port']}", $_POST["login"], $_POST["password"]);
    } catch (PDOException $e) {
        $errno = $e->getCode();
        $error = $e->getMessage();
    }
    if ($errno > 0) {
        EchoHeader($errno);
        echo GetBlock($error);
        exit;
    }
    if (($errno <= 0) && ($_POST["db"] != "")) {
        $conn->exec('use ' . $_POST["db"]);
        $errno = $conn->errorCode();
        if ($errno > 0) {
            echo GetBlock($conn->errorInfo());
        }
    }
    EchoHeader($errno);
    if ($_POST["actn"] == "C") {
        EchoConnInfo($conn);
    } elseif ($_POST["actn"] == "Q") {
        for ($i = 0; $i < count($_POST["q"]); $i++) {
            $query = $_POST["q"][$i];
            if ($query == "") continue;
            if (phpversion_int() < 50400) {
                if (get_magic_quotes_gpc())
                    $query = stripslashes($query);
            }
            $res = $conn->prepare($query);
            if ($res->execute()) {
                $numfields = $res->columnCount();
                $numrows = $res->rowCount();
                $affectedrows = $numrows;
                $insertid = $conn->lastInsertId();
                $errno = 0;
                $error = '';
            } else {
                $errorInfo = $res->errorInfo();
                $errno = $errorInfo[1];
                $error = $errorInfo[2];
                $numfields = $numrows = $affectedrows = $insertid = 0;
            }
            EchoResultSetHeader($errno, $affectedrows, $insertid, $numfields, $numrows);
            if ($errno > 0) {
                echo GetBlock($error);
            } else {
                if ($numfields > 0) {
                    EchoFieldsHeader($res, $numfields);
                    EchoData($res);
                } else {
                    echo GetBlock("");
                }
            }
            if ($i < (count($_POST["q"]) - 1))
                echo "\x01";
            else
                echo "\x00";
        }
    }
    exit();
}
?>