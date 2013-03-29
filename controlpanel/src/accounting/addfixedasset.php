<?php

require_once("common.php");

function main()
{
	doAccounting();
	
	$check = function($condition, $error) {
		if(!$condition) die(page(makeHeader("Add fixed asset", accountingBreadcrumbs(), crumbs("Add fixed asset", "addfixedasset.php")) . addFixedAssetForm($error, $_POST)));
	};
	
	$check(($name = post("name")) !== null, "");
	$description = post("description");
	$check(($purchaseDate = parseDate(post("purchaseDate"))) !== null, "Invalid purchase date.");
	$check(ctype_digit(post("depreciationFrequencyMultiplier")), "Invalid depreciationFrequencyMultiplier");
	$check(post("depreciationFrequencyBase") == "DAY" || post("depreciationFrequencyBase") == "MONTH" || post("depreciationFrequencyBase") == "YEAR", "Invalid depreciationFrequencyBase");
	$check(ctype_digit($depreciationTerms = post("depreciationTerms")), "Invalid depreciation terms.");
	$check($depreciationTerms > 0, "Invalid depreciation terms.");
	$check(ctype_digit($residualValuePercentage = post("residualValue")), "Invalid residual value.");
	
	
	$check(post("confirm") !== null, null);
	
	startTransaction();
	$valueAccountID = accountingAddAccount($GLOBALS["fixedAssetValueDirectoryAccountID"], $GLOBALS["defaultCurrencyID"], $name, $description, false);
	$depreciationAccountID = accountingAddAccount($GLOBALS["fixedAssetDepreciationDirectoryAccountID"], $GLOBALS["defaultCurrencyID"], $name, depreciationAccountDescription($name), false);
	$expenseAccountID = accountingAddAccount($GLOBALS["fixedAssetExpenseDirectoryAccountID"], $GLOBALS["defaultCurrencyID"], $name, expenseAccountDescription($name), false);
	$fixedAssetID = stdNew("accountingFixedAsset", array(
		"accountID"=>$valueAccountID,
		"depreciationAccountID"=>$depreciationAccountID,
		"expenseAccountID"=>$expenseAccountID,
		"name"=>$name,
		"description"=>$description,
		"purchaseDate"=>$purchaseDate,
		"depreciationFrequencyBase"=>post("depreciationFrequencyBase"),
		"depreciationFrequencyMultiplier"=>post("depreciationFrequencyMultiplier"),
		"nextDepreciationDate"=>billingCalculateNextDate($purchaseDate, post("depreciationFrequencyBase"), post("depreciationFrequencyMultiplier")),
		"totalDepreciations"=>$depreciationTerms,
		"performedDepreciations"=>0,
		"residualValuePercentage"=>$residualValuePercentage,
		"automaticDepreciation"=>1,
		));
	commitTransaction();
	
	redirect("accounting/fixedasset.php?id=$fixedAssetID");
}

main();

?>