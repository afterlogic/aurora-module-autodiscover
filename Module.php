<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Autodiscover;

use Aurora\System\Facades\Route;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @property Settings $oModuleSettings
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
    /**
     * @return Module
     */
    public static function getInstance()
    {
        return parent::getInstance();
    }

    /**
     * @return Module
     */
    public static function Decorator()
    {
        return parent::Decorator();
    }

    /**
     * @return Settings
     */
    public function getModuleSettings()
    {
        return $this->oModuleSettings;
    }

    /**
     * Initializes Mail Module.
     *
     * @ignore
     */
    public function init()
    {
        Route::add(
            $this,
            [
                'autodiscover' => 'EntryAutodiscover'
            ]
        );
    }

    public function GetAutodiscover($Email)
    {
        return '';
    }

    public function EntryAutodiscover()
    {
        // List of allowed AcceptableResponseSchema values. All others will be treated as absent.
        static $aAllowedSchemas = [
            'http://schemas.microsoft.com/exchange/autodiscover/outlook/responseschema/2006a',
            'http://schemas.microsoft.com/exchange/autodiscover/mobilesync/responseschema/2006',
            'http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006',
        ];

        $sInput = \file_get_contents('php://input');
        \Aurora\System\Api::Log('#autodiscover:');
        \Aurora\System\Api::LogObject($sInput);

        $aMatches = array();
        $aEmailAddress = array();
        \preg_match("/\<AcceptableResponseSchema\>(.*?)\<\/AcceptableResponseSchema\>/i", $sInput, $aMatches);
        \preg_match("/\<EMailAddress\>(.*?)\<\/EMailAddress\>/i", $sInput, $aEmailAddress);

        // Schema validation: if AcceptableResponseSchema is present, it must be one of the allowed values. Otherwise, treat as absent.
        $sSchema = '';
        if (!empty($aMatches[1])) {
            $sCandidate = \trim($aMatches[1]);
            if (\in_array($sCandidate, $aAllowedSchemas, true)) {
                $sSchema = $sCandidate;
            } else {
                \Aurora\System\Api::Log('Autodiscover: rejected unknown AcceptableResponseSchema value: ' . $sCandidate);
            }
        }

        $sEmail = !empty($aEmailAddress[1]) ? \trim($aEmailAddress[1]) : '';

        $oDoc = new \DOMDocument('1.0', 'utf-8');
        $oDoc->formatOutput = false;

        $oRoot = $oDoc->createElement('Autodiscover');
        $oRoot->setAttribute('xmlns', 'http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006');
        $oDoc->appendChild($oRoot);

        $oResponse = $oDoc->createElement('Response');
        // xmlns added as attribute to Response element, not Autodiscover, because some clients (e.g. Outlook 2016) ignore the outer xmlns and fail to parse the response if it's not present on Response.
        if ($sSchema !== '') {
            $oResponse->setAttribute('xmlns', $sSchema);
        }
        $oRoot->appendChild($oResponse);

        $bOk = false;
        if ($sSchema !== '' && $sEmail !== '') {
            $sAutodiscover = self::Decorator()->GetAutodiscover($sEmail);
            if (!empty($sAutodiscover)) {
                // GetAutodiscover() returns trusted server-generated XML (not user input),
                // so it is appended as an XML fragment instead of escaped text.
                $oFragment = $oDoc->createDocumentFragment();
                // appendXML() validates that the fragment is well-formed XML.
                if (@$oFragment->appendXML($sAutodiscover)) {
                    $oResponse->appendChild($oFragment);
                    $bOk = true;
                } else {
                    \Aurora\System\Api::Log('Autodiscover: GetAutodiscover() returned malformed XML fragment');
                }
            }
        }

        if (!$bOk) {
            $usec = $sec = 0;
            list($usec, $sec) = \explode(' ', \microtime());

            $oError = $oDoc->createElement('Error');
            $oError->setAttribute('Time', \gmdate('H:i:s', $sec) . \substr($usec, 0, \strlen($usec) - 2));
            $oError->setAttribute('Id', '2477272013');

            $oErrorCode = $oDoc->createElement('ErrorCode', '600');
            $oMessage = $oDoc->createElement('Message', 'Invalid Request');
            $oDebugData = $oDoc->createElement('DebugData');

            $oError->appendChild($oErrorCode);
            $oError->appendChild($oMessage);
            $oError->appendChild($oDebugData);

            $oResponse->appendChild($oError);
        }

        \header('Content-Type: text/xml; charset=utf-8');
        $sResult = $oDoc->saveXML();
        echo $sResult;

        \Aurora\System\Api::Log('');
        \Aurora\System\Api::Log($sResult);
    }
}