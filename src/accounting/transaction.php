<?php

require_once("common.php");

function main()
{
	$transactionID = get("id");
	$accountID = get("accountID");
	
	doAccountingTransaction($transactionID);
	
	$date = stdGet("accountingTransaction", array("transactionID"=>$transactionID), "date");
	$content = makeHeader(sprintf(_("Transaction on %s"), date("d-m-Y", $date)), transactionBreadcrumbs($transactionID, $accountID));
	
	$content .= transactionSummary($transactionID);
	
	$content .= editTransactionForm($transactionID, $accountID);
	
	$type = accountingTransactionType($transactionID);
	if($type["type"] == "NONE") {
		$content .= deleteTransactionForm($transactionID, $accountID);
	}
	
	echo page($content);
}

main();

?>