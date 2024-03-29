<?php
use SLiMS\Config;
use SLiMS\Table\Schema;
use SLiMS\Table\Blueprint;

class MaxFine extends \SLiMS\Migration\Migration
{
    function up() {
        if (!Schema::hasColumn('mst_loan_rules', 'max_fine')) {
            Schema::table('mst_loan_rules', function(Blueprint $table) {
                $table->number('max_fine', 11)->default(0)->add();
            });
        }

        if (config('advance-fines') === null) {
            Config::createOrUpdate('advance-fines', [
                'auto-paid' => true,
                'allowed-users' => []
            ]);
        }
    }

    function down() {

    }
}