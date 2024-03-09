<?php
/**
 * Plugin Name: Auto Lunas
 * Plugin URI: -
 * Description: Cara membuat denda langsung lunas
 * Version: 1.0.0
 * Author: Drajat Hasan
 * Author URI: https://t.me/drajathasan
 */
use SLiMS\Plugins;
use SLiMS\DB;

$plugins = Plugins::getInstance();

$plugins->registerMenu('circulation', __('Loan Rules'), __DIR__ . '/loan_rules.php');
$plugins->register('circulation_after_successful_transaction', function($detail) {
    $state = DB::getInstance()->prepare(<<<SQL
    update
        `fines`
        set
            `credit` = `debet`
        where
            `member_id`  = ? and
            `description` like ?
    SQL);

    foreach ($_SESSION['receipt_record']['return']??[] as $return) {
        $state->execute([$detail['memberID'], '%' . $return['itemCode']]);
    }
});