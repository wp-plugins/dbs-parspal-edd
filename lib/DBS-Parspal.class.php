<?php

class DBSParspal {

	private $WSURL = "http://merchant.parspal.com/WebService.asmx?wsdl";
	private $MID;
	private $Pass;

	public function Set($MID, $Pass) {
		$this->MID = $MID;
		$this->Pass = $Pass;
	}

	public function Go($inarray = array()) {

		extract( $inarray );

		$connect = new SoapClient($this->WSURL);

		$result  = $connect->RequestPayment(array(
			"MerchantID"  => $this->MID, 
			"Password"    => $this->Pass, 
			"Price"       => $price,
			"ReturnPath"  => $return, 
			"ResNumber"   => $resnum, 
			"Description" => urlencode($desc), 
			"Paymenter"   => $payer,
			"Email"       => $mail,
			"Mobile"      => $mob
		));

		if( $result->RequestPaymentResult->ResultStatus == 'Succeed' ){
			ob_start();
			header( 'Location: ' . $result->RequestPaymentResult->PaymentPath );
			ob_end_clean();
		} else {
			die( $result->RequestPaymentResult->ResultStatus );
		}

	}

	public function Check($price, $refnum) {

		$connect = new SoapClient($this->WSURL);

		$result  = $connect->VerifyPayment(array(
			"MerchantID" => $this->MID, 
			"Password"   => $this->Pass, 
			"Price"      => $price,
			"RefNum"     => $refnum
		));

		return $result->verifyPaymentResult->ResultStatus;

	}


}

?>