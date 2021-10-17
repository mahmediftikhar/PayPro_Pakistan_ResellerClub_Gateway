<?php
	session_start();
	require("functions.php");	//file which has required functions
?>	 	
		
<html>
<head><title>Payment Page </title>
<script language="JavaScript">
        function successClicked()
        {
            document.tptpaypro.submit();
        }
        function failClicked()
        {
            document.tptpaypro.status.value = "N";
            document.tptpaypro.submit();
        }
        function pendingClicked()
        {
            document.tptpaypro.status.value = "P";
            document.tptpaypro.submit();
        }
</script>
</head>
<body bgcolor="white">

<?php
		
		$key = "x6JkETUg6gxZ53K212dnBHfq1mTFjIaW";
		
		//This filter removes data that is potentially harmful for your application. It is used to strip tags and remove or encode unwanted characters.
		$_GET = filter_var_array($_GET, FILTER_SANITIZE_STRING);
		
		//Below are the  parameters which will be passed from foundation as http GET request
		$paymentTypeId = $_GET["paymenttypeid"];  //112169
		$transId = $_GET["transid"];			   //This refers to a unique transaction ID which we generate for each transaction
		$userId = $_GET["userid"];               //userid of the user who is trying to make the payment
		$userType = $_GET["usertype"];  		   //This refers to the type of user perofrming this transaction. The possible values are "Customer" or "Reseller"
		$transactionType = $_GET["transactiontype"];  //Type of transaction (ResellerAddFund/CustomerAddFund/ResellerPayment/CustomerPayment)

		$invoiceIds = $_GET["invoiceids"];		   //comma separated Invoice Ids, This will have a value only if the transactiontype is "ResellerPayment" or "CustomerPayment"
		$debitNoteIds = $_GET["debitnoteids"];	   //comma separated DebitNotes Ids, This will have a value only if the transactiontype is "ResellerPayment" or "CustomerPayment"

		$description = $_GET["description"];
		
		$sellingCurrencyAmount = $_GET["sellingcurrencyamount"]; //This refers to the amount of transaction in your Selling Currency - PKR
        $accountingCurrencyAmount = $_GET["accountingcurrencyamount"]; //This refers to the amount of transaction in your Accounting Currency - USD

		$redirectUrl = $_GET["redirecturl"];  //This is the URL on our server, to which you need to send the user once you have finished charging him

						
		$checksum = $_GET["checksum"];	 //checksum for validation

		 echo "File tptpaypro.php<br>";
         echo "Checksum Verification..............";

		if(verifyChecksum($paymentTypeId, $transId, $userId, $userType, $transactionType, $invoiceIds, $debitNoteIds, $description, $sellingCurrencyAmount, $accountingCurrencyAmount, $key, $checksum))
		{
			//PAYPRO API CODE GOES HERE
	
		
		

		/** 
		* since all these data has to be passed back to foundation after making the payment you need to save these data
		*		
		* keep the data to the session which will be available in tptthankyou.php as we have done here.
		*
		**/

			
			$_SESSION['redirecturl']=$redirectUrl;
			$_SESSION['transid']=$transId;
			$_SESSION['sellingcurrencyamount']=$sellingCurrencyAmount;
			$_SESSION['accountingcurencyamount']=$accountingCurrencyAmount;


			
            echo "Verified<br>";
            echo "List of Variables Received as follows<br>";
            echo "Paymenttypeid : ".$paymentTypeId."<br>";
            echo "transid : ".$transId."<br>";
            echo "userid : ".$userId."<br>";
            echo "usertype : ".$userType."<br>";
            echo "transactiontype : ".$transactionType."<br>";
            echo "invoiceids : ".$invoiceIds."<br>";
            echo "debitnoteids : ".$debitNoteIds."<br>";
            echo "description : ".$description."<br>";
            echo "sellingcurrencyamount : ".$sellingCurrencyAmount."<br>";
            echo "accountingcurrencyamount : ".$accountingCurrencyAmount."<br>";
            echo "redirecturl : ".$redirectUrl."<br>";
            echo "checksum : ".$checksum."<br><br>";
?>

<form name="tptpaypro" action="tptthankyou.php">
    <input type="hidden" name="status" value="Y">
    <input type="button" name="btnSuccess" onClick="successClicked();" value="Continue Test of a Successful Transaction"><br>
    <input type="button" name="btnPending" onClick="pendingClicked();" value="Continue Test of a Pending Transaction"><br>
    <input type="button" name="btnFailed" onClick="failClicked();" value="Continue Test of a Failed Transaction"><br>
</form>

<?php

		}
		else
		{
			/**This message will be dispayed in any of the following case
			*
			* 1. You are not using a valid 32 bit secure key from your Reseller Control panel
			* 2. The data passed from foundation has been tampered.
			*
			* In both these cases the customer has to be shown error message and shound not
			* be allowed to proceed  and do the payment.
			*
			**/

			echo "Checksum mismatch !";			

		}
?>
</body>
</html>
