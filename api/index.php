<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

?>

<?php
$APPLICATION->IncludeComponent(
    "only:car.booking",
    "",
    [
        "EMPLOYEE_ID" => $_GET["employee_id"],
        "START_DATE" => $_GET["start_date"],
        "END_DATE" => $_GET["end_date"],
    ],
    false
);
?>

<? require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>