<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

if (!extension_loaded('bcmath')) {
    die("BCMath module is not enabled.");
}

$gatewayModuleName = "paykassa";

require_once __DIR__ . '/paykassa/index.php';

function paykassa_MetaData()
{
    return array(
        'DisplayName' => 'Paykassa.pro Merchant Gateway Module',
        'APIVersion' => '1.0.7',
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage' => false,
    );
}

function paykassa_config()
{
    return array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Paykassa.pro',
        ),
        'merchant_id' => array(
            'FriendlyName' => 'Merchant ID',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => '',
        ),
        'merchant_password' => array(
            'FriendlyName' => 'Merchant Password',
            'Type' => 'text',
            'Size' => '255',
            'Default' => '',
            'Description' => '',
        ),
    );
}

/**
 * https://developers.whmcs.com/payment-gateways/third-party-gateway/
 * https://github.com/WHMCS/sample-gateway-module
 */
function paykassa_link($params)
{

    if (basename($_SERVER['SCRIPT_NAME']) !== "viewinvoice.php") {
        $query = http_build_query([
            "id" => $params['invoiceid'],
        ]);
        header('Location: /viewinvoice.php?' . $query);
        exit();
    }
    global $gatewayModuleName;
    $paykassa = new \Paykassa\PaykassaSCI(
        $params['merchant_id'],
        $params['merchant_password']
    );


    ob_start();
    if ("GET" === $_SERVER['REQUEST_METHOD'] || empty($_POST["pscur"])) {
        $list = \Paykassa\PaykassaSCI::getPaymentSystems("crypto");
        ?>
        <form action="" method="POST">
            <label>Choose payment system and currency</label>
            <select name="pscur" id="pscur" autocomplete="off" required>
                <option value="">---</option>
                <?php foreach ($list as $item) { ?>
                    <?php foreach ($item["currency_list"] as $currency) { ?>
                        <option value="<?php echo htmlspecialchars(
                            sprintf("%s_%s", mb_strtolower($item["system"]), mb_strtolower($currency)),
                            ENT_QUOTES, "UTF-8"); ?>">
                            <?php echo htmlspecialchars(sprintf("%s %s", $item["display_name"], $currency),
                                ENT_QUOTES, "UTF-8"); ?>
                        </option>
                    <?php } ?>
                <?php } ?>
            </select>

            <button><?php echo htmlspecialchars($params['langpaynow'], ENT_QUOTES, "UTF-8"); ?></button>
        </form>

        <script>
            document.getElementById('pscur').addEventListener('change', function () {
                this.form.submit();
            });
        </script>

        <?php
    } else {

        @list($system, $currency) = preg_split('~_(?=[^_]*$)~', $_POST["pscur"]);

        $res = $paykassa->createOrder(
            paykassa_convert_amount($params["amount"], $params["currency"], $currency),
            $system,
            $currency,
            $params['invoiceid'],
            $params["description"]
        );

        if ($res["error"]) {
            paykassa_log($res);
            return paykassa_set_error($res["message"]);
        }

        header('Location: ' . $res["data"]["url"]);
        exit();

        ?>
        <?php
    }

    return ob_get_clean();
}