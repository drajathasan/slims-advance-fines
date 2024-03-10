<?php
/**
 * Plugin Name: SLiMS Advance Fines
 * Plugin URI: -
 * Description: Cara membuat denda langsung lunas
 * Version: 1.0.0
 * Author: Drajat Hasan
 * Author URI: https://t.me/drajathasan
 */
use SLiMS\Plugins;
use SLiMS\DB;

$plugins = Plugins::getInstance();

if (!function_exists('pluginUrl'))
{
    /**
     * Generate URL with plugin_container.php?id=<id>&mod=<mod> + custom query
     *
     * @param array $data
     * @param boolean $reset
     * @return string
     */
    function pluginUrl(array $data = [], bool $reset = false): string
    {
        // back to base uri
        if ($reset) return $_SERVER['PHP_SELF'] . '?mod=' . $_GET['mod'] . '&id=' . $_GET['id'];
        
        return $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET,$data));
    }
}


// Overriding existing submenu
$plugins->registerMenu('system', __('System Configuration'), __DIR__ . '/system-config.php');
$plugins->registerMenu('circulation', __('Loan Rules'), __DIR__ . '/loan_rules.php');

// Intercepting access to fines_list.php
$plugins->register('admin_session_after_start', function() {
    global $sysconf,$dbs;

    $trace = array_pop(debug_backtrace());
    $match = basename($trace['file']) === 'fines_list.php';
    $notInArray = ($_SESSION['uid']??[]) > 1 && !in_array($_SESSION['uid']??0, config('advance-fines.allowed-users', []));

    if ($match && $notInArray) {
        $content = '<div class="errorBox">
            <strong>Anda tidak diijinkan oleh Admin untuk mengelola data denda</strong>.
        </div>';
        // js include
        $js = '<script type="text/javascript" src="'.JWB.'calendar.js"></script>';
        // include the page template
        require SB.'/admin/'.$sysconf['admin_template']['dir'].'/notemplate_page_tpl.php';
        exit;
    }
});

// Bulk update
$plugins->register('circulation_after_successful_transaction', function($detail) {
    if (config('advance-fines.auto-paid', false)) {
        $loan_rules = DB::getInstance()->prepare(<<<SQL
        select 
            max_fine 
            from 
                mst_loan_rules 
                where member_type_id = (
                    select 
                        member_type_id
                        from 
                            member
                        where
                            member_id = ?
                )
        SQL);
        $loan_rules->execute([$detail['memberID']]);

        $max_fines = 0;
        if ($loan_rules && $loan_rules->rowCount() == 1) {
            $fineData = $loan_rules->fetchObject();
            $max_fines = (int)$fineData->max_fine;
        }

        $state = DB::getInstance()->prepare(<<<SQL
        update
            `fines`
            set
                `credit` = IF (({$max_fines} > 0 and `debet` > {$max_fines}), {$max_fines}, `debet`),
                `debet` = IF (({$max_fines} > 0 and `debet` > {$max_fines}), {$max_fines}, `debet`)
            where
                `member_id`  = ? and
                `description` like ?
        SQL);

        foreach ($_SESSION['receipt_record']['return']??[] as $return) {
            $state->execute([$detail['memberID'], '%' . $return['itemCode']]);
        }
    }
});