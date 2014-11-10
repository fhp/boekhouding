<?php

require_once(dirname(__FILE__) . "/../common.php");

function doBilling()
{
	useComponent("billing");
	$GLOBALS["menuComponent"] = "billing";
}

function doBillingAdmin($customerID)
{
	doBilling();
	useCustomer($customerID);
	useCustomer(0);
}

function doInvoice($invoiceID)
{
	doBilling();
	useCustomer(stdGetTry("billingInvoice", array("invoiceID"=>$invoiceID), "customerID", false));
}

function crumb($name, $filename)
{
	return array("name"=>$name, "url"=>"{$GLOBALS["root"]}billing/$filename");
}

function crumbs($name, $filename)
{
	return array(crumb($name, $filename));
}

function customersBillingBreadcrumbs()
{
	return crumbs("Billing", "");
}

function adminCustomerBreadcrumbs($customerID)
{
	$name = stdGet("adminCustomer", array("customerID"=>$customerID), "name");
	return array(
		array("name"=>"Customers", "url"=>"{$GLOBALS["root"]}customers/"),
		array("name"=>$name, "url"=>"{$GLOBALS["root"]}customers/customer.php?id=$customerID"),
		crumb("Billing", "customer.php?id=$customerID")
	);
}

function adminSubscriptionBreadcrumbs($subscriptionID)
{
	$subscription = stdGet("billingSubscription", array("subscriptionID"=>$subscriptionID), array("customerID", "description"));
	return array_merge(adminCustomerBreadcrumbs($subscription["customerID"]), crumbs($subscription["description"], "subscription.php?id=$subscriptionID"));
}

function adminPaymentBreadcrumbs($paymentID)
{
	$payment = stdGet("billingPayment", array("paymentID"=>$paymentID), array("customerID", "transactionID"));
	$date = stdGet("accountingTransaction", array("transactionID"=>$payment["transactionID"]), "date");
	$name = "Payment on " . date("d-m-Y", $date);
	return array_merge(adminCustomerBreadcrumbs($payment["customerID"]), crumbs($name, "payment.php?id=$payment"));
}

function customerSummary($customerID)
{
	$customer = stdGet("adminCustomer", array("customerID"=>$customerID), array("accountID", "name", "invoiceStatus"));
	return summaryTable("Customer {$customer["name"]}", array(
		"Balance"=>array("url"=>"{$GLOBALS["rootHtml"]}accounting/account.php?id={$customer["accountID"]}", "html"=>accountingFormatAccountPrice($customer["accountID"], true)),
		"Invoice status"=>ucfirst(strtolower($customer["invoiceStatus"])),
		"Domain registration limit"=>array("html"=>formatPrice(domainsCustomerUnpaidDomainsPrice($customerID)) . " / " . formatPrice(domainsCustomerUnpaidDomainsLimit($customerID))),
		
	));
}

function invoiceStatusForm($customerID, $error = "", $values = null)
{
	if($values === null) {
		$values = stdGet("adminCustomer", array("customerID"=>$customerID), array("invoiceStatus"));
	}
	return operationForm("changestatus.php?id=$customerID", $error, "Change invoice status", "Save",
		array(
			array("title"=>"Status", "type"=>"dropdown", "name"=>"invoiceStatus", "options"=>array(
				array("label"=>"Unset", "value"=>"UNSET"),
				array("label"=>"Disabled", "value"=>"DISABLED"),
				array("label"=>"Preview", "value"=>"PREVIEW"),
				array("label"=>"Enabled", "value"=>"ENABLED")
			)),
		),
		$values);
}

function subscriptionList($customerID)
{
	$rows = array();
	foreach(stdList("billingSubscription", array("customerID"=>$customerID), array("subscriptionID", "domainTldID", "description", "price", "discountPercentage", "discountAmount", "frequencyBase", "frequencyMultiplier", "invoiceDelay", "nextPeriodStart", "endDate")) as $subscription) {
		if($subscription["discountPercentage"] === null && $subscription["discountAmount"] === null) {
			$priceDetail = "None";
		} else {
			$priceDetail = formatPrice(billingBasePrice($subscription));
			if($subscription["discountPercentage"] !== null) {
				$priceDetail .= " - " . $subscription["discountPercentage"] . "%";
			}
			if($subscription["discountAmount"] !== null) {
				$priceDetail .= " - " . formatPrice($subscription["discountAmount"]);
			}
		}
		
		$nextPeriod = date("d-m-Y", $subscription["nextPeriodStart"]);
		
		if($subscription["endDate"] === null) {
			$endDate  = "-";
		} else {
			$endDate = date("d-m-Y", $subscription["endDate"]);
		}
		
		$rows[] = array(
			array("url"=>"{$GLOBALS["rootHtml"]}billing/subscription.php?id={$subscription["subscriptionID"]}", "text"=>$subscription["description"]),
			array("html"=>formatSubscriptionPrice($subscription)),
			array("html"=>$priceDetail),
			$nextPeriod,
			$endDate
		);
	}
	return listTable(array("Description", "Price", "Discounts", "Renew date", "End date"), $rows, "Subscriptions", true, "sortable list");
}

function customerSubscriptionList()
{
	$rows = array();
	foreach(stdList("billingSubscription", array("customerID"=>customerID()), array("subscriptionID", "domainTldID", "description", "price", "discountPercentage", "discountAmount", "frequencyBase", "frequencyMultiplier", "invoiceDelay", "nextPeriodStart", "endDate")) as $subscription) {
		$domainID = stdGetTry("dnsDomain", array("subscriptionID"=>$subscription["subscriptionID"]), "domainID");
		if($domainID === null) {
			$url = null;
		} else {
			$url = "{$GLOBALS["rootHtml"]}domains/domain.php?id={$domainID}";
		}
		$rows[] = array(
			array("url"=>$url, "text"=>$subscription["description"]),
			array("html"=>formatSubscriptionPrice($subscription))
		);
	}
	return listTable(array("Description", "Price"), $rows, "Subscriptions", false, "sortable list");
}

function subscriptionDetail($subscriptionID)
{
	$subscription = stdGet("billingSubscription", array("subscriptionID"=>$subscriptionID), array("revenueAccountID", "domainTldID", "description", "price", "discountPercentage", "discountAmount", "frequencyBase", "frequencyMultiplier", "invoiceDelay", "nextPeriodStart", "endDate"));
	
	if($subscription["discountPercentage"] !== null) {
		$discountPercentage = $subscription["discountPercentage"] . "% (" . formatPrice(billingBasePrice($subscription) * $subscription["discountPercentage"] / 100) . ")";
	} else {
		$discountPercentage = "-";
	}
	
	if($subscription["discountAmount"] !== null) {
		$discountAmount = formatPrice($subscription["discountAmount"]);
	} else {
		$discountAmount = "-";
	}
	
	if($subscription["invoiceDelay"] == 0) {
		$delay = "None";
	} else if($subscription["invoiceDelay"] > 0) {
		$delay = ceil($subscription["invoiceDelay"] / 86400) . " days later";
	} else {
		$delay = ceil(-1 * $subscription["invoiceDelay"] / 86400) . " days in advance";
	}
	
	if($subscription["domainTldID"] !== null) {
		$domainID = stdGetTry("dnsDomain", array("subscriptionID"=>$subscriptionID), "domainID");
		if($domainID === null) {
			$domainTldName = "." . stdGet("infrastructureDomainTld", array("domainTldID"=>$subscription["domainTldID"]), "name");
			$domainName = "unknown $domainTldName domain";
		} else {
			$domainName = domainsFormatDomainName($domainID);
		}
	} else {
		$domainName = "-";
	}
	
	$revenueAccountName = stdGet("accountingAccount", array("accountID"=>$subscription["revenueAccountID"]), "name");
	
	return summaryTable("Subscription", array(
		"Description"=>$subscription["description"],
		"Price"=>array("html"=>formatSubscriptionPrice($subscription)),
		"Base price"=>array("html"=>formatPrice(billingBasePrice($subscription))),
		"Discount percentage"=>array("html"=>$discountPercentage),
		"Discount amount"=>array("html"=>$discountAmount),
		"Frequency"=>frequency($subscription),
		"Invoice delay"=>$delay,
		"Renew date"=>date("d-m-Y", $subscription["nextPeriodStart"]),
		"End date"=>($subscription["endDate"] === null ? "-" : date("d-m-Y", $subscription["endDate"])),
		"Revenue account"=>array("url"=>"{$GLOBALS["rootHtml"]}accounting/account.php?id={$subscription["revenueAccountID"]}", "text"=>$revenueAccountName),
		"Related domain"=>$domainName
	));
}

function invoiceList($customerID)
{
	$accountID = stdGet("adminCustomer", array("customerID"=>$customerID), "accountID");
	$balance = -billingBalance($customerID);
	
	$rows = array();
	foreach(billingInvoiceStatusList($customerID) as $invoice) {
		$rows[] = array(
			date("d-m-Y", $invoice["date"]),
			array("url"=>"{$GLOBALS["rootHtml"]}billing/invoicepdf.php?id={$invoice["invoiceID"]}", "text"=>$invoice["invoiceNumber"]),
			array("url"=>"{$GLOBALS["rootHtml"]}accounting/transaction.php?id={$invoice["transactionID"]}", "html"=>formatPrice($invoice["amount"])),
			array("html"=>($invoice["remainingAmount"] == 0 ? "Paid" : formatPrice($invoice["remainingAmount"]))),
			$invoice["remainingAmount"] == 0 ? array("html"=>"") : array("url"=>"reminder.php?id={$invoice["invoiceID"]}", "text"=>"Send reminder"),
			array("url"=>"resend.php?id={$invoice["invoiceID"]}", "text"=>"Resend")
		);
	}
	return listTable(array("Date", "Invoice number", "Amount", "Remaining amount", "Reminder", "Resend"), $rows, "Invoices", "No invoices have been sent so far.", "sortable list");
}

function customerInvoiceList($customerID)
{
	$accountID = stdGet("adminCustomer", array("customerID"=>$customerID), "accountID");
	$balance = -billingBalance($customerID);
	
	$rows = array();
	foreach(billingInvoiceStatusList($customerID) as $invoice) {
		$rows[] = array(
			array("url"=>"{$GLOBALS["rootHtml"]}billing/invoicepdf.php?id={$invoice["invoiceID"]}", "text"=>$invoice["invoiceNumber"]),
			date("d-m-Y", $invoice["date"]),
			array("html"=>formatPrice($invoice["amount"])),
			array("html"=>($invoice["remainingAmount"] == 0 ? "Paid" : formatPrice($invoice["remainingAmount"]))),
		);
	}
	return listTable(array("Invoice number", "Date", "Amount", "Remaining amount"), $rows, "Invoices", "No invoices have been sent so far.", "sortable list");
}

function paymentList($customerID)
{
	$accountID = stdGet("adminCustomer", array("customerID"=>$customerID), "accountID");
	$customerIDSql = dbAddSlashes($customerID);
	
	$rows = array();
	foreach(query("SELECT paymentID, transactionID, description, date FROM billingPayment INNER JOIN accountingTransaction USING(transactionID) WHERE customerID='$customerIDSql' ORDER BY date DESC")->fetchList() as $payment) {
		$amount = -stdGet("accountingTransactionLine", array("transactionID"=>$payment["transactionID"], "accountID"=>$accountID), "amount");
		
		$rows[] = array(
			array("url"=>"payment.php?id={$payment["paymentID"]}", "text"=>date("d-m-Y", $payment["date"])),
			array("url"=>"{$GLOBALS["rootHtml"]}accounting/transaction.php?id={$payment["transactionID"]}", "html"=>formatPrice($amount)),
			$payment["description"]
		);
	}
	return listTable(array("Date", "Amount", "Description"), $rows, "Payments", "No payments have been made so far.", "sortable list");
}

function paymentSummary($paymentID)
{
	$payment = stdGet("billingPayment", array("paymentID"=>$paymentID), array("customerID", "transactionID"));
	$customer = stdGet("adminCustomer", array("customerID"=>$payment["customerID"]), array("accountID", "name"));
	$transaction = stdGet("accountingTransaction", array("transactionID"=>$payment["transactionID"]), array("date", "description"));
	$currencyID = stdGet("accountingAccount", array("accountID"=>$customer["accountID"]), "currencyID");
	$currencySymbol = stdGet("accountingCurrency", array("currencyID"=>$currencyID), "symbol");
	
	$dateHtml = date("d-m-Y", $transaction["date"]);
	$amountHtml = accountingCalculateTransactionAmount($payment["transactionID"], $customer["accountID"], true);
	
	$fields = array(
		"Customer"=>array("url"=>"customer.php?id={$payment["customerID"]}", "text"=>$customer["name"]),
		"Amount"=>array("url"=>"{$GLOBALS["rootHtml"]}accounting/transaction.php?id={$payment["transactionID"]}", "html"=>$amountHtml),
		"Date"=>array("text"=>$dateHtml),
		"Description"=>array("text"=>$transaction["description"]),
	);
	
	return summaryTable("Payment on " . $dateHtml, $fields);
}

function addPaymentForm($customerID, $error = "", $values = null)
{
	if($values === null) {
		$values = array("date"=>date("d-m-Y"), "bankAccountID"=>$GLOBALS["bankDefaultAccountID"], "description"=>"Payment from " . stdGet("adminCustomer", array("customerID"=>$customerID), "name"));
	}
	if(isset($values["date"])) {
		$values["date"] = date("d-m-Y", parseDate($values["date"]));
	}
	if(isset($values["amount"])) {
		$values["amount"] = formatPriceRaw(parsePrice($values["amount"]));
	}
	return operationForm("addpayment.php?id=$customerID", $error, "Add payment", "Save",
		array(
			array("title"=>"Amount", "type"=>"text", "name"=>"amount"),
			array("title"=>"Description", "type"=>"text", "name"=>"description"),
			array("title"=>"Date", "type"=>"text", "name"=>"date"),
			array("title"=>"Bank account", "type"=>"dropdown", "name"=>"bankAccountID", "options"=>accountingAccountOptions($GLOBALS["bankDirectoryAccountID"], true)),
		),
		$values);
}

function editPaymentForm($paymentID, $error = "", $values = null)
{
	$payment = stdGet("billingPayment", array("paymentID"=>$paymentID), array("customerID", "transactionID"));
	$customer = stdGet("adminCustomer", array("customerID"=>$payment["customerID"]), array("accountID", "name"));
	$currencyID = stdGet("accountingAccount", array("accountID"=>$customer["accountID"]), "currencyID");
	
	if($values === null) {
		$transaction = stdGet("accountingTransaction", array("transactionID"=>$payment["transactionID"]), array("date", "description"));
		$lines = stdList("accountingTransactionLine", array("transactionID"=>$payment["transactionID"]), array("accountID", "amount"));
		foreach($lines as $line) {
			if($line["accountID"] == $customer["accountID"]) {
				$amount = -1 * $line["amount"];
			} else {
				$bankAccountID = $line["accountID"];
			}
		}
		$values = array(
			"amount"=>formatPriceRaw($amount),
			"bankAccountID"=>$bankAccountID,
			"date"=>date("d-m-Y", $transaction["date"]),
			"description"=>$transaction["description"],
		);
	}
	
	return operationForm("editpayment.php?id=$paymentID", $error, "Edit payment", "Save", array(
			array("title"=>"Amount", "type"=>"text", "name"=>"amount"),
			array("title"=>"Description", "type"=>"text", "name"=>"description"),
			array("title"=>"Date", "type"=>"text", "name"=>"date"),
			array("title"=>"Bank account", "type"=>"dropdown", "name"=>"bankAccountID", "options"=>accountingAccountOptions($GLOBALS["bankDirectoryAccountID"], true)),
		), $values);
}

function deletePaymentForm($paymentID, $error = "", $values = null)
{
	return operationForm("deletepayment.php?id=$paymentID", $error, "Delete payment", "Delete", array(), $values);
}

function addSubscriptionForm($customerID, $error = "", $values = null)
{
	if($values === null) {
		$values = array("discountPercentage"=>0, "discountAmount"=>"0,00", "frequencyMultiplier"=>1, "frequencyBase"=>"MONTH", "nextPeriodStart"=>date("d-m-Y"), "invoiceDelay"=>0);
	}
	if(isset($values["nextPeriodStart"])) {
		$values["nextPeriodStart"] = date("d-m-Y", parseDate($values["nextPeriodStart"]));
	}
	return operationForm("addsubscription.php?id=$customerID", $error, "Add subscription", "Save",
		array(
			array("title"=>"Description", "type"=>"text", "name"=>"description"),
			array("title"=>"Price", "type"=>"text", "name"=>"price"),
			array("title"=>"Discount percentage", "type"=>"text", "name"=>"discountPercentage"),
			array("title"=>"Discount amount", "type"=>"text", "name"=>"discountAmount"),
			array("title"=>"Frequency", "type"=>"colspan", "columns"=>array(
				array("type"=>"html", "html"=>"per"),
				array("type"=>"text", "name"=>"frequencyMultiplier", "fill"=>true),
				array("type"=>"dropdown", "name"=>"frequencyBase", "options"=>array(
					array("label"=>"year", "value"=>"YEAR"),
					array("label"=>"month", "value"=>"MONTH"),
					array("label"=>"day", "value"=>"DAY")
				))
			)),
			array("title"=>"Start date", "type"=>"text", "name"=>"nextPeriodStart"),
			array("title"=>"Invoice delay", "type"=>"colspan", "columns"=>array(
				array("type"=>"text", "name"=>"invoiceDelay", "fill"=>true),
				array("type"=>"html", "html"=>"days")
			)),
			array("title"=>"Revenue account", "type"=>"dropdown", "name"=>"revenueAccountID", "options"=>accountingAccountOptions($GLOBALS["revenueDirectoryAccountID"], true)),
		),
		$values);
}

function editSubscriptionForm($subscriptionID, $error = "", $values = null)
{
	if($values === null) {
		$values = stdGet("billingSubscription", array("subscriptionID"=>$subscriptionID), array("revenueAccountID", "domainTldID", "description", "price", "discountPercentage", "discountAmount", "frequencyBase", "frequencyMultiplier", "invoiceDelay", "nextPeriodStart", "endDate"));
		$values["priceType"] = $values["price"] === null ? "domain" : "custom";
		$values["price"] = formatPriceRaw($values["price"]);
		$values["discountAmount"] = formatPriceRaw($values["discountAmount"]);
		$values["invoiceDelay"] = round($values["invoiceDelay"] / (24 * 3600));
		if($values["discountPercentage"] === null) {
			$values["discountPercentage"] = 0;
		}
	}
	$domainTldID = stdGet("billingSubscription", array("subscriptionID"=>$subscriptionID), "domainTldID");
	return operationForm("editsubscription.php?id=$subscriptionID", $error, "Edit subscription", "Save",
		array(
			array("title"=>"Description", "type"=>"text", "name"=>"description"),
			$domainTldID !== null ?
				array("title"=>"Price", "type"=>"subformchooser", "name"=>"priceType", "subforms"=>array(
					array("value"=>"domain", "label"=>"Use tld price (" . formatPrice(billingDomainPrice($domainTldID)) . ")", "subform"=>array()),
					array("value"=>"custom", "label"=>"Custom", "subform"=>array(
						array("type"=>"text", "name"=>"price")
					))
				))
			:
				array("title"=>"Price", "type"=>"text", "name"=>"price")
			,
			array("title"=>"Discount percentage", "type"=>"text", "name"=>"discountPercentage"),
			array("title"=>"Discount amount", "type"=>"text", "name"=>"discountAmount"),
			array("title"=>"Frequency", "type"=>"colspan", "columns"=>array(
				array("type"=>"html", "html"=>"per"),
				array("type"=>"text", "name"=>"frequencyMultiplier", "fill"=>true),
				array("type"=>"dropdown", "name"=>"frequencyBase", "options"=>array(
					array("label"=>"year", "value"=>"YEAR"),
					array("label"=>"month", "value"=>"MONTH"),
					array("label"=>"day", "value"=>"DAY")
				))
			)),
			array("title"=>"Invoice delay", "type"=>"colspan", "columns"=>array(
				array("type"=>"text", "name"=>"invoiceDelay", "fill"=>true),
				array("type"=>"html", "html"=>"days")
			)),
			array("title"=>"Revenue account", "type"=>"dropdown", "name"=>"revenueAccountID", "options"=>accountingAccountOptions($GLOBALS["revenueDirectoryAccountID"])),
		),
		$values);
}

function endSubscriptionForm($subscriptionID, $error = "", $values = null)
{
	if($values === null) {
		$values = array("endDate"=>date("d-m-Y"));
	}
	return operationForm("endsubscription.php?id=$subscriptionID", $error, "End subscription", "Save", array(array("title"=>"End date", "type"=>"text", "name"=>"endDate")), $values);
}

function addInvoiceLineForm($customerID, $error = "", $values = null)
{
	return operationForm("addinvoiceline.php?id=$customerID", $error, "Add invoice line", "Save", 
		array(
			array("title"=>"Description", "type"=>"text", "name"=>"description"),
			array("title"=>"Price", "type"=>"text", "name"=>"price"),
			array("title"=>"Discount", "type"=>"text", "name"=>"discount"),
			array("title"=>"Revenue account", "type"=>"dropdown", "name"=>"revenueAccountID", "options"=>accountingAccountOptions($GLOBALS["revenueDirectoryAccountID"], true)),
		),
		$values);
}

function sendInvoiceForm($customerID, $error = "", $values = null)
{
	if($values === null) {
		$values = array("sendmail"=>true);
	}
	$lines = array();
	$lines[] = array("type"=>"colspan", "columns"=>array(
		array("type"=>"html", "html"=>"", "celltype"=>"th"),
		array("type"=>"html", "html"=>"Description", "celltype"=>"th", "fill"=>true),
		array("type"=>"html", "html"=>"Price", "celltype"=>"th"),
		array("type"=>"html", "html"=>"Discount", "celltype"=>"th"),
		array("type"=>"html", "html"=>"Start date", "celltype"=>"th"),
		array("type"=>"html", "html"=>"End date", "celltype"=>"th")
	));
	foreach(stdList("billingSubscriptionLine", array("customerID"=>$customerID), array("subscriptionLineID", "revenueAccountID", "description", "price", "discount", "periodStart", "periodEnd")) as $subscriptionLine) {
		if($error === null && !isset($values["subscriptionline-{$subscriptionLine["subscriptionLineID"]}"])) {
			continue;
		}
		$lines[] = array("type"=>"colspan", "columns"=>array(
			array("type"=>"checkbox", "name"=>"subscriptionline-{$subscriptionLine["subscriptionLineID"]}", "label"=>""),
			array("type"=>"html", "fill"=>true, "html"=>$subscriptionLine["description"]),
			array("type"=>"html", "cellclass"=>"nowrap", "html"=>formatPrice($subscriptionLine["price"])),
			array("type"=>"html", "cellclass"=>"nowrap", "html"=>formatPrice($subscriptionLine["discount"])),
			array("type"=>"html", "cellclass"=>"nowrap", "html"=>$subscriptionLine["periodStart"] == null ? "-" : date("d-m-Y", $subscriptionLine["periodStart"])),
			array("type"=>"html", "cellclass"=>"nowrap", "html"=>$subscriptionLine["periodEnd"] == null ? "-" : date("d-m-Y", $subscriptionLine["periodEnd"]))
		));
	}
	$lines[] = array("type"=>"typechooser", "options"=>array(
		array("title"=>"Delete", "submitcaption"=>"Delete", "name"=>"delete", "summary"=>"Delete selected subscription lines", "subform"=>array()),
		array("title"=>"Create invoice", "submitcaption"=>"Create Invoice", "name"=>"create", "summary"=>"Create and send an invoice with the selected subscription lines", "subform"=>array(
			array("title"=>"Send email", "type"=>"checkbox", "name"=>"sendmail", "label"=>"Send an email to the customer")
		)),
	));

	return operationForm("sendinvoice.php?id=$customerID", $error, "Subscription lines", "Create Invoice", $lines, $values);
}

function frequency($subscription)
{
	if($subscription["frequencyBase"] == "DAY") {
		return "per " . ($subscription["frequencyMultiplier"] == 1 ? "day" : $subscription["frequencyMultiplier"] . " days");
	} else if($subscription["frequencyBase"] == "MONTH") {
		return "per " . ($subscription["frequencyMultiplier"] == 1 ? "month" : $subscription["frequencyMultiplier"] . " months");
	} else if($subscription["frequencyBase"] == "YEAR") {
		return "per " . ($subscription["frequencyMultiplier"] == 1 ? "year" : $subscription["frequencyMultiplier"] . " years");
	} else {
		return "unknown";
	}
}

function formatSubscriptionPrice($subscription)
{
	$percentageDiscount = billingBasePrice($subscription) * $subscription["discountPercentage"] / 100;
	$price = (int)(billingBasePrice($subscription) - $percentageDiscount - $subscription["discountAmount"]);
	return formatPrice($price) . " " . frequency($subscription);
}

?>