<?php
$table=new ILabPublishHistoryTable();
$table->prepare_items();
?>
<style>
    .wrap table th {
        background-color: #d7d7d7;
        padding: 10px;
    }
    .wrap table td {
        padding: 10px;
    }

    .column-version {
        width: 140px !important;
    }
    .column-date {
        width: 140px !important;
    }
</style>
<div class="wrap">
    <h2>History</h2>
    <?php $table->display(); ?>
</div>
