<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: SMSProvider.php 6772 2012-08-15 16:53:17Z matt $
 *
 * @category Piwik_Plugins
 * @package Piwik_MobileMessaging
 */

/**
 * The Piwik_MobileMessaging_SMSProvider abstract class is used as a base class for SMS provider implementations.
 *
 * @package Piwik_MobileMessaging
 * @subpackage Piwik_MobileMessaging_SMSProvider
 */
abstract class Piwik_MobileMessaging_SMSProvider
{
	const MAX_GSM_CHARS_IN_ONE_UNIQUE_SMS = 160;
	const MAX_GSM_CHARS_IN_ONE_CONCATENATED_SMS = 153;
	const MAX_UCS2_CHARS_IN_ONE_UNIQUE_SMS = 70;
	const MAX_UCS2_CHARS_IN_ONE_CONCATENATED_SMS = 67;

	static public $availableSMSProviders = array(
		'Clockwork' => 'You can use <a target="_blank" href="?module=Proxy&action=redirect&url=http://www.clockworksms.com/platforms/piwik/"><img src="plugins/MobileMessaging/images/Clockwork.png"/></a> to send SMS Reports from Piwik.<br/> 
			<ul>
			<li> First, <a target="_blank" href="?module=Proxy&action=redirect&url=http://www.clockworksms.com/platforms/piwik/">get an API Key from Clockwork</a> (Signup is free!) 
			</li><li> Enter your Clockwork API Key on this page. </li>
			</ul>
			<br/><i>About Clockwork: </i><ul>
			<li>Clockwork gives you fast, reliable high quality worldwide SMS delivery, over 450 networks in every corner of the globe. 
			</li><li>Cost per SMS message is around ~0.08USD (0.06EUR).
			</li><li>Most countries and networks are supported but we suggest you check the latest position on their coverage map <a target="_blank" href="?module=Proxy&action=redirect&url=http://www.clockworksms.com/sms-coverage/">here</a>.
			</li>
			</ul>
			',
	);

	/**
	 * Return the SMSProvider associated to the provider name $providerName
	 *
	 * @throws exception If the provider is unknown
	 * @param string $providerName
	 * @return Piwik_MobileMessaging_SMSProvider
	 */
	static public function factory($providerName)
	{
		$name = ucfirst(strtolower($providerName));
		$className = 'Piwik_MobileMessaging_SMSProvider_' . $name;

		try {
			Piwik_Loader::loadClass($className);
			return new $className;
		} catch(Exception $e) {
			throw new Exception(
				Piwik_TranslateException(
					'MobileMessaging_Exception_UnknownProvider',
					array($name, implode(', ', array_keys(self::$availableSMSProviders)))
				)
			);
		}
	}

	/**
	 * Assert whether a given String contains UCS2 characters
	 *
	 * @param string $string
	 * @return bool true if $string contains UCS2 characters
	 */
	static public function containsUCS2Characters($string)
	{
		$GSMCharsetAsString = implode(array_keys(Piwik_MobileMessaging_GSMCharset::$GSMCharset));

		foreach(self::mb_str_split($string) as $char)
		{
			if(mb_strpos($GSMCharsetAsString, $char) === false) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Truncate $string and append $appendedString at the end if $string can not fit the
	 * the $maximumNumberOfConcatenatedSMS.
	 *
	 * @param string $string String to truncate
	 * @param int $maximumNumberOfConcatenatedSMS
	 * @param string $appendedString
	 * @return string original $string or truncated $string appended with $appendedString
	 */
	static public function truncate($string, $maximumNumberOfConcatenatedSMS, $appendedString = 'MobileMessaging_SMS_Content_Too_Long')
	{
		$appendedString = Piwik_Translate($appendedString);

		$smsContentContainsUCS2Chars = self::containsUCS2Characters($string);
		$maxCharsAllowed = self::maxCharsAllowed($maximumNumberOfConcatenatedSMS, $smsContentContainsUCS2Chars);
		$sizeOfSMSContent = self::sizeOfSMSContent($string, $smsContentContainsUCS2Chars);

		if($sizeOfSMSContent <= $maxCharsAllowed) return $string;

		$smsContentContainsUCS2Chars = $smsContentContainsUCS2Chars || self::containsUCS2Characters($appendedString);
		$maxCharsAllowed = self::maxCharsAllowed($maximumNumberOfConcatenatedSMS, $smsContentContainsUCS2Chars);
		$sizeOfSMSContent = self::sizeOfSMSContent($string . $appendedString, $smsContentContainsUCS2Chars);

		$sizeToTruncate = $sizeOfSMSContent - $maxCharsAllowed;

		$subStrToTruncate = '';
		$subStrSize = 0;
		$reversedStringChars = array_reverse(self::mb_str_split($string));
		for($i = 0; $subStrSize < $sizeToTruncate; $i++)
		{
			$subStrToTruncate = $reversedStringChars[$i] . $subStrToTruncate;
			$subStrSize = self::sizeOfSMSContent($subStrToTruncate, $smsContentContainsUCS2Chars);
		}

		return preg_replace('/' . preg_quote($subStrToTruncate) . '$/', $appendedString, $string);
	}

	static private function mb_str_split($string)
	{
		return preg_split('//u',$string, -1, PREG_SPLIT_NO_EMPTY);
	}

	static private function sizeOfSMSContent($smsContent, $containsUCS2Chars)
	{
		if($containsUCS2Chars) return mb_strlen($smsContent, 'UTF-8');

		$sizeOfSMSContent = 0;
		foreach(self::mb_str_split($smsContent) as $char)
		{
			$sizeOfSMSContent += Piwik_MobileMessaging_GSMCharset::$GSMCharset[$char];
		}
		return $sizeOfSMSContent;
	}

	static private function maxCharsAllowed($maximumNumberOfConcatenatedSMS, $containsUCS2Chars)
	{
		$maxCharsInOneUniqueSMS = $containsUCS2Chars ? self::MAX_UCS2_CHARS_IN_ONE_UNIQUE_SMS : self::MAX_GSM_CHARS_IN_ONE_UNIQUE_SMS;
		$maxCharsInOneConcatenatedSMS = $containsUCS2Chars ? self::MAX_UCS2_CHARS_IN_ONE_CONCATENATED_SMS : self::MAX_GSM_CHARS_IN_ONE_CONCATENATED_SMS;

		$uniqueSMS = $maximumNumberOfConcatenatedSMS == 1;

		return $uniqueSMS ?
			$maxCharsInOneUniqueSMS :
			$maxCharsInOneConcatenatedSMS * $maximumNumberOfConcatenatedSMS;
	}

	/**
	 * verify the SMS API credential
	 *
	 * @param string $apiKey API Key
	 * @return bool true if SMS API credential are valid, false otherwise
	 */
	abstract public function verifyCredential($apiKey);

	/**
	 * get remaining credits
	 *
	 * @param string $apiKey API Key
	 * @return string remaining credits
	 */
	abstract public function getCreditLeft($apiKey);

	/**
	 * send SMS
	 *
	 * @param string $apiKey
	 * @param string $smsText
	 * @param string $phoneNumber
	 * @return bool true
	 */
	abstract public function sendSMS($apiKey, $smsText, $phoneNumber, $from);
}
