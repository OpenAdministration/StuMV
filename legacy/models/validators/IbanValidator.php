<?php

namespace app\models\validators;

class IbanValidator extends \yii\validators\Validator
{

    public const COUNTRY_LENGTH = [
        'al' => 28, 'ad' => 24, 'at' => 20, 'az' => 28, 'bh' => 22, 'be' => 16, 'ba' => 20, 'br' => 29, 'bg' => 22,
        'cr' => 21, 'hr' => 21, 'cy' => 28, 'cz' => 24, 'dk' => 18, 'do' => 28, 'ee' => 20, 'fo' => 18, 'fi' => 18,
        'fr' => 27, 'ge' => 22, 'de' => 22, 'gi' => 23, 'gr' => 27, 'gl' => 18, 'gt' => 28, 'hu' => 28, 'is' => 26,
        'ie' => 22, 'il' => 23, 'it' => 27, 'jo' => 30, 'kz' => 20, 'kw' => 30, 'lv' => 21, 'lb' => 28, 'li' => 21,
        'lt' => 20, 'lu' => 20, 'mk' => 19, 'mt' => 31, 'mr' => 27, 'mu' => 30, 'mc' => 27, 'md' => 24, 'me' => 22,
        'nl' => 18, 'no' => 15, 'pk' => 24, 'ps' => 29, 'pl' => 28, 'pt' => 25, 'qa' => 29, 'ro' => 24, 'sm' => 27,
        'sa' => 24, 'rs' => 22, 'sk' => 24, 'si' => 19, 'es' => 24, 'se' => 24, 'ch' => 21, 'tn' => 24, 'tr' => 26,
        'ae' => 23, 'gb' => 22, 'vg' => 24
    ];


    /**
     * @param string $value iban to validate
     * @return array|void|null
     * @see https://stackoverflow.com/a/20983340
     */
    protected function validateValue($value)
    {
        $iban = mb_strtolower($value);
        $countryCode = mb_substr($iban, 0, 2);
        if(!isset(self::COUNTRY_LENGTH[$countryCode])){
            return ["Länderkennung '$countryCode' ist unbekannt", []];
        }
        if (strlen($iban) === self::COUNTRY_LENGTH[$countryCode]) {

            $ibanSwitch = mb_substr($iban, 4) . mb_substr($iban, 0, 4);
            $ibanSwitchArray = str_split($ibanSwitch);
            $transIban = "";

            foreach ($ibanSwitchArray as $char) {
                if (ctype_digit($char)){
                    $val = $char;
                } else if (ctype_alpha($char)) {
                    $val = (string) (mb_ord($char) - 87); // shift ascii offset to a = 10
                } else {
                    return ["Unbekanntes Zeichen '$char'", []];
                }
                $transIban .= $val;
            }

            if (bcmod($transIban, '97') === '1') {
                return ['IBAN ungültig', []];
            }
        }
        return ['IBAN hat die falsche Länge', []];
    }

    // TODO: implement clientValidateAttribute for JS client validations

}