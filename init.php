<?
use Bitrix\Main\Application;
use Bitrix\Main\EventManager;
use Bitrix\Main\IO\File;

define('NEW_SITE_DIR', '/mobile');

class OrderAndBasketHandler
{
    public static function savePropLogs(\Bitrix\Main\Event $event)
    {
        global $arZone, $USER;
        /**@var \Bitrix\Sale\Shipment $ship */
        /**@var \Bitrix\Sale\Order $order */
        // Remove the admin check
        $allowSiteId = ["s1", "s2", "s3", "ma"];

        $order = $event->getParameter("ENTITY");

        if ($order->getPaymentSystemId()[0] == 1 && in_array($order->getSiteId(), $allowSiteId)) {
            $basket = $order->getBasket();
            $basketItems = $basket->getBasketItems();
            $start_price = $basket->getPrice();
            $end_price = floor($basket->getPrice());
            $r = $start_price - $end_price;
            $max_price = 0;
            $max = [
                'TOTAL_PRICE' => 0,
                'PRICE' => 0,
                'ID' => 0,
            ];
            foreach ($basketItems as $item) {
                if ($max['TOTAL_PRICE'] < $item->getPrice()) {
                    $max['TOTAL_PRICE'] = $item->getPrice();
                    $max['PRICE'] = $item->getPrice() - $r;
                    $max['ID'] = $item->getProductId();
                }
            }
            foreach ($basketItems as $item) {
                if ($item->getProductId() == $max['ID']) {
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/bitrix/php_interface/logs/order1.txt', print_r($max['PRICE'], true));
                    $item->setField('PRICE', $max['PRICE']);
                    $item->setField('CUSTOM_PRICE', 'Y');
                    $item->save();

                    $orderPrice = $order->getBasket()->getPrice();
                    $deliveryPrice = 0;
                    if ($orderPrice <= 500)
                        $deliveryPrice = 150;
                    elseif ($orderPrice <= 1000)
                        $deliveryPrice = CSiteUtils::$deliveryPrice;
                }
            }
        }
        if (defined('ADMIN_SECTION') || $USER->GetID() == 12572) {
            $propertyCollection = $order->getPropertyCollection()->getArray()['properties'];
            $arProps = [];
            foreach ($propertyCollection as $props)
                $arProps[$props['CODE']] = $props['VALUE'][0];

            if ($arProps['ZONE_ID']) {
                $arZone = ZonesTable::getById(str_replace('zone_', '', $arProps['ZONE_ID']))->fetch();
            }
        }
        $deliveryPrice = 0;
        if ($_REQUEST['DELIVERY_ID'] == CSiteUtils::$deliveryExpress && $arZone['EXPRESS'] == "Y") {
            $deliveryPrice = $arZone['PRICE_EXPRESS'];
        }
        if ($order->getShipmentCollection()[0] && $order->getShipmentCollection()[0]->getDeliveryId() == CSiteUtils::$deliveryExpress && $arZone['EXPRESS'] == "Y") {
            $deliveryPrice = $arZone['PRICE_EXPRESS'];
        }

        if ($deliveryPrice > 0) {
            $order->setFieldNoDemand('PRICE_DELIVERY', $deliveryPrice);
            $order->setFieldNoDemand('PRICE', $deliveryPrice + $order->getBasket()->getPrice());
        }
    }
}

\Bitrix\Main\EventManager::getInstance()->addEventHandler(
    'sale',
    'OnSaleOrderBeforeSaved',
    ['OrderAndBasketHandler', 'savePropLogs']
);



