<?php

use WHMCS\Database\Capsule;

function paykassa_convert_amount($amount, $currency_in, $currency_out)
{
    $currency_tag = mb_strtoupper(sprintf("%s_%s", $currency_in, $currency_out));
    $pairs = [
        $currency_tag,
    ];

    $res = \Paykassa\PaykassaCurrency::getCurrencyPairs($pairs);


    if ($res["error"]) {
        die($res["message"]);
    }
    $map_pairs = [];
    array_map(function ($pairs) use (&$map_pairs) {
        foreach ($pairs as $pair => $value) {
            $map_pairs[$pair] = $value;
        }
    }, $res["data"]);

    return bcmul($amount, $map_pairs[$currency_tag], 8);
}


function paykassa_format_is_crypto($currency)
{
    $currency_list = ["USD", "RUB", "GBP", "EUR",];
    return !in_array(
        mb_strtoupper($currency), $currency_list);
}


function paykassa_format_currency($money, $currency)
{
    $money = (string)$money;
    $currency = strtolower($currency);

    if (!paykassa_format_is_crypto($currency)) {
        return bcmul($money, '1', 2);
    }

    if ($currency === "xmr") {
        return bcmul($money, '1', 12);
    }

    if ($currency === "ton") {
        return bcmul($money, '1', 9);
    }

    if (in_array($currency, ["xrp", "trx", "usdt", "busd", "usdc",])) {
        return bcmul($money, '1', 6);
    }

    if ($currency === "xlm") {
        return bcmul($money, '1', 7);
    }

    return bcmul($money, '1', 8);
}


function paykassa_get_invoice_currency_code($invoiceId)
{
    $invoiceData = Capsule::table('tblinvoices')
        ->join('tblclients', 'tblinvoices.userid', '=', 'tblclients.id')
        ->join('tblcurrencies', 'tblclients.currency', '=', 'tblcurrencies.id')
        ->where('tblinvoices.id', $invoiceId)
        ->first(['tblcurrencies.code']);

    if (!$invoiceData) {
        throw new Exception("Invoice or currency not found.");
    }

    return $invoiceData->code;
}

function paykassa_get_module_name()
{
    return "paykassa";
}


function paykassa_log($data)
{
    logTransaction(
        paykassa_get_module_name(),
        $data,
        'Error'
    );
}

function paykassa_set_error($message)
{
    ob_start(); ?>
    <div class="alert alert-danger" style="margin-bottom:20px;">
    <?php echo htmlspecialchars(
    $message,
    ENT_QUOTES,
    'UTF-8'
); ?>
    </div><?php

    return ob_get_clean();
}


