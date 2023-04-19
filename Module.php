<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Autodiscover;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
    /**
     * @return Module
     */
    public static function Decorator()
    {
        return parent::Decorator();
    }

    /**
     *
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
        $this->AddEntries(
            array(
                'autodiscover' => 'EntryAutodiscover'
            )
        );
    }

    public function GetAutodiscover($Email)
    {
        return '';
    }

    public function EntryAutodiscover()
    {
        $sInput = \file_get_contents('php://input');

        \Aurora\System\Api::Log('#autodiscover:');
        \Aurora\System\Api::LogObject($sInput);

        $aMatches = array();
        $aEmailAddress = array();
        \preg_match("/\<AcceptableResponseSchema\>(.*?)\<\/AcceptableResponseSchema\>/i", $sInput, $aMatches);
        \preg_match("/\<EMailAddress\>(.*?)\<\/EMailAddress\>/", $sInput, $aEmailAddress);
        if (!empty($aMatches[1]) && !empty($aEmailAddress[1])) {
            $sAutodiscover = self::Decorator()->GetAutodiscover($aEmailAddress[1]);

            $sResult = \implode("\n", array(
'<Autodiscover xmlns="http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006">',
'	<Response xmlns="'.$aMatches[1].'">',
$sAutodiscover,
'	</Response>',
'</Autodiscover>'));
        }

        if (empty($sResult)) {
            $usec = $sec = 0;
            list($usec, $sec) = \explode(' ', \microtime());
            $sResult = \implode("\n", array('<Autodiscover xmlns="http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006">',
(
    empty($aMatches[1]) ?
'	<Response>' :
'	<Response xmlns="'.$aMatches[1].'">'
),
'		<Error Time="'.\gmdate('H:i:s', $sec).\substr($usec, 0, \strlen($usec) - 2).'" Id="2477272013">',
'			<ErrorCode>600</ErrorCode>',
'			<Message>Invalid Request</Message>',
'			<DebugData />',
'		</Error>',
'	</Response>',
'</Autodiscover>'));
        }

        \header('Content-Type: text/xml');
        $sResult = '<'.'?xml version="1.0" encoding="utf-8"?'.'>'."\n".$sResult;

        echo $sResult;

        \Aurora\System\Api::Log('');
        \Aurora\System\Api::Log($sResult);
    }
}
