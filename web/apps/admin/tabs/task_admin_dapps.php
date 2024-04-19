<?php
global $_config;
$dapps_public_key = $_config['dapps_public_key'];
$dapps_id = Account::getAddress($dapps_public_key);
$dapps_folder = Dapps::getDappsDir() . "/$dapps_id";
$folder_exists = file_exists($dapps_folder);
?>
<div class="card-body">
    <div class="flex-row d-flex justify-content-between flex-wrap">
        <div>Address:</div>
        <div><?php echo explorer_address_link($dapps_id) ?></div>
    </div>
    <div class="flex-row d-flex justify-content-between flex-wrap">
        <div>Folder:</div>
        <div class="text-break"><?php echo $dapps_folder ?></div>
    </div>
    <div class="flex-row d-flex justify-content-between flex-wrap">
        <div>Folder exists:</div>
        <div><?php echo $folder_exists ? "Yes" : "No" ?></div>
    </div>
    <div class="flex-row d-flex justify-content-between flex-wrap">
        <div>Dapps URL:</div>
        <div>
            <a href="/dapps/<?php echo $dapps_id ?>">/dapps/<?php echo $dapps_id ?></a>
        </div>
    </div>
</div>
