<?php

echo "<link rel='stylesheet' href='https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css'> 
      <link rel='stylesheet' href='https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css'>";

if ($_POST) {
    $gponSerialNumber = $_POST['gpon_sn'];
    $gponPassword = $_POST['gpon_password'];
    $gponMacAddress = str_replace(array('-', ':'), '', $_POST['gpon_mac']);
    $sfpA2Info = $_POST['sfp_a2_info'];
}

if (!$_POST ||
    ($_POST['check']) ||
    ($_POST['submit'] && !$sfpA2Info)
) {
    if ($sfpA2Info) {
        $sfpA2InfoArray = parseToArray($sfpA2Info);
        delNonEncoded();
        arrayDecode();
        $gponSerialNumber = huaweiShowSerialNumber();
        $gponPassword = huaweiShowPassword();
        $gponMacAddress = huaweiShowMacAddress();
    }

    echo "
    <div class='container-fluid'>
        <h2>Huawei MA5671A Data Generator</h2>
        <form action='gen_huawei.php' method='post'>
          <div class='form-group'>
            <label>GPON S/N</label> <input type='text' class='form-control' name='gpon_sn' placeholder='16-chars HEX 4857544312345678 or 12-chars HWTC12345678' value='$gponSerialNumber' " . ($gponSerialNumber ? 'readonly' : '') . ">
          </div>
          <div class='form-group'>
            <label>GPON Password</label> <input type='text' class='form-control' name='gpon_password' placeholder='20-chars HEX 30303030303030303030 or 10-chars ASCII 0000000000' value='$gponPassword' " . ($gponPassword ? 'readonly' : '') . ">
          </div>
          <div class='form-group'>
            <label>GPON MAC Address</label> <input type='text' class='form-control' name='gpon_mac' placeholder='24-chars HEX 12:34:56:78:9A:BC' value='$gponMacAddress' " . ($gponMacAddress ? 'readonly' : '') . ">
          </div>
          <div class='form-group'>
            <label>SFP A2 Info Variable (shell command: 'fw_printenv sfp_a2_info')</label> 
            <textarea class='form-control' name='sfp_a2_info' placeholder='base64 env variable' rows='15'>$sfpA2Info</textarea>
          </div>
          <div class='form-group'>
            <input type='submit' class='btn btn-primary' name='submit' value='Modify and generate new data'>
            <input type='submit' class='btn btn-primary' name='check' value='Display current data'>
            <a href='gen_huawei.php'><input type='button' class='btn btn-primary' name='reset' value='Reset'></a>
          </div>
        </form>
    </div>";
} else if ($_POST['submit'] && $sfpA2Info) {
    echo "
    <h2>Original:</h2>
    <textarea rows='15' cols='100'>$sfpA2Info</textarea>
    <br>";

    $sfpA2InfoArray = parseToArray($sfpA2Info);
    if (!$sfpA2InfoArray || ($sfpA2InfoArray[0] !== 'begin-base64 644 sfp_a2_info ')) {
        echo "<p>sfp_a2_info variable in wrong format!</p>";
        exit();
    }
    delNonEncoded();
    arrayDecode();

    if ($gponSerialNumber) {
        if (strlen($gponSerialNumber) === 12) {
            $gponSerialNumber = substr_replace(
                $gponSerialNumber,
                ascii2hex(substr($gponSerialNumber, 0, 4)),
                0,
                4
            );
        }
        if (strlen($gponSerialNumber) === 16) {
            huaweiChangeSerialNumber($gponSerialNumber);
        }
    }
    if ($gponPassword) {
        if (strlen($gponPassword) === 10) {
            $gponPassword = ascii2hex($gponPassword);
        }
        if (strlen($gponPassword) === 20 && ctype_xdigit($gponPassword)) {
            huaweiChangePassword($gponPassword);
        }
    }
    if ($gponMacAddress && strlen($gponMacAddress) === 12) {
        huaweiChangeMacAddress($gponMacAddress);
    }

    arrayEncode();
    addNonEncoded();
    $sfpA2InfoNew = parseToString($sfpA2InfoArray);

    if ($sfpA2Info === $sfpA2InfoNew) {
        echo "<p>Modified is the same, nothing done!</p>";
    } else {
        echo "
        <h2>Modified:</h2>
        <form action='gen_huawei.php' method='post'>
            <div class='form-group'>
                <textarea rows='15' cols='100' name='sfp_a2_info'>$sfpA2InfoNew</textarea>
            </div>
            <div class='form-group'>
                <input type='submit' class='btn btn-primary' name='check' value='Test generated data'>
            </div>
        </form>
        <p>Paste the modified string into a temporary file (e.g. /tmp/sfp) on your module and run 'fw_setenv sfp_a2_info `cat /tmp/sfp`'</p>";
    }
}


function huaweiShowSerialNumber(): string
{
    global $sfpA2InfoArray;
    $gponSerialNumber = substr($sfpA2InfoArray[5], 16, 16);
    $gponSerialNumberAscii = substr_replace(
        $gponSerialNumber,
        hex2ascii(substr($gponSerialNumber, 0, 8)),
        0,
        8
    );
    if ($gponSerialNumber && $gponSerialNumberAscii) {
        return 'HEX: ' . $gponSerialNumber . ' / Vendor ASCII: ' . $gponSerialNumberAscii;
    }
    return '';
}

function huaweiShowPassword(): string
{
    global $sfpA2InfoArray;
    $gponPassword = substr($sfpA2InfoArray[4], 22, 20);
    $gponPasswordAscii = hex2ascii($gponPassword);
    if ($gponPassword && $gponPasswordAscii) {
        return 'HEX: ' . $gponPassword . ' / ASCII: ' . hex2ascii($gponPassword);
    }
    return '';
}

function huaweiShowMacAddress(): string
{
    global $sfpA2InfoArray;
    $gponMacAddress = substr($sfpA2InfoArray[8], 48, 12);
    return implode(':', str_split($gponMacAddress, 2));
}

function huaweiChangeSerialNumber($gponSerialNumber)
{
    global $sfpA2InfoArray;
    $replacement = substr_replace($sfpA2InfoArray[5], $gponSerialNumber, 16, strlen($gponSerialNumber));
    array_splice($sfpA2InfoArray, 5, 1, $replacement);
}

function huaweiChangePassword($gponPassword)
{
    global $sfpA2InfoArray;
    $replacement = substr_replace($sfpA2InfoArray[4], $gponPassword, 22, strlen($gponPassword));
    array_splice($sfpA2InfoArray, 4, 1, $replacement);
}

function huaweiChangeMacAddress($gponMacAddress)
{
    global $sfpA2InfoArray;
    $replacement = substr_replace($sfpA2InfoArray[8], $gponMacAddress, 48, strlen($gponMacAddress));
    array_splice($sfpA2InfoArray, 8, 1, $replacement);
}

function parseToArray(string $string): ?array
{
    $array = preg_split('/[@]+/', $string, -1, PREG_SPLIT_NO_EMPTY);
    if (count($array) > 10) {
        return $array;
    }
    return null;
}

function parseToString(array $array): string
{
    return implode('@', $array) . '@';
}

function delNonEncoded(): void
{
    global $sfpA2InfoArray;
    $GLOBALS['prefix'] = array_shift($sfpA2InfoArray);
    $GLOBALS['suffix'] = array_pop($sfpA2InfoArray);
}

function addNonEncoded(): void
{
    global $sfpA2InfoArray;
    array_unshift($sfpA2InfoArray, $GLOBALS['prefix']);
    $sfpA2InfoArray[] = $GLOBALS['suffix'];
}

function arrayDecode(): void
{
    global $sfpA2InfoArray;
    array_walk($sfpA2InfoArray, static function (&$val) {
        $val = bin2hex(base64_decode($val));
    });
}

function arrayEncode(): void
{
    global $sfpA2InfoArray;
    array_walk($sfpA2InfoArray, static function (&$val) {
        $val = base64_encode(hex2bin($val));
    });
}

function ascii2hex(string $ascii): string
{
    $hex = '';
    for ($i = 0, $iMax = strlen($ascii); $i < $iMax; $i++) {
        $byte = strtoupper(dechex(ord($ascii[$i])));
        $byte = str_repeat('0', 2 - strlen($byte)) . $byte;
        $hex .= $byte;
    }
    return $hex;
}

function hex2ascii($hex): string
{
    $str = '';
    for ($i = 0, $iMax = strlen($hex); $i < $iMax; $i += 2) {
        $str .= chr(hexdec(substr($hex, $i, 2)));
    }
    return $str;
}
