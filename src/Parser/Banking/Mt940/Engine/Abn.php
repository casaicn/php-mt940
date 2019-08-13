<?php

namespace Kingsquare\Parser\Banking\Mt940\Engine;

use Kingsquare\Parser\Banking\Mt940\Engine;

/**
 * @author Kingsquare (source@kingsquare.nl)
 * @license http://opensource.org/licenses/MIT MIT
 */
class Abn extends Engine
{
    /**
     * returns the name of the bank.
     *
     * @return string
     */
    protected function parseStatementBank()
    {
        return 'ABN';
    }

    /**
     * Overloaded: ABN Amro shows the GIRO
     * includes fix for 'for GIRO 1234567 TEST 201009063689 CLIEOP 21-9' and translates that into 1234567.
     *
     * @inheritdoc
     */
    protected function parseTransactionAccount()
    {
        $results = parent::parseTransactionAccount();

        if (empty($results)) {
            $giroMatch = $ibanMatch = [];
            if (preg_match('/^:86:GIRO(.{9})/im', $this->getCurrentTransactionData(), $giroMatch)
                && !empty($giroMatch[1])
            ) {
                $results = $giroMatch[1];
            }

            if (preg_match('!^:86:/.*/IBAN/(.*?)/!m', $this->getCurrentTransactionData(), $ibanMatch)
                && !empty($ibanMatch[1])
            ) {
                $results = $ibanMatch[1];
            }
        }

        return $this->sanitizeAccount($results);
    }

    /**
     * Overloaded: ABN Amro shows the GIRO and fixes newlines etc.
     *
     * @inheritdoc
     */
    protected function parseTransactionAccountName()
    {
        $results = parent::parseTransactionAccountName();
        if ($results !== '') {
            return $results;
        }

        $results = [];
        $data = $this->getCurrentTransactionData();

        // SEPA MT940 Structured
        if (preg_match('#/NAME/(.+?)\n?/(REMI|IBAN|BIC|ADDR|ISDT|CSID|MARF)/#ms', $data, $results)) {
            $accountName = trim($results[1]);
            if (!empty($accountName)) {
                return $this->sanitizeAccountName($accountName);
            }
        }

        if (preg_match('/:86:(GIRO|BGC\.)\s+[\d]+ (.+)/', $data, $results)
            && !empty($results[2])
        ) {
            return $this->sanitizeAccountName($results[2]);
        }

        if (preg_match('/:86:.+\n(.*)\n/', $data, $results)
            && !empty($results[1])
        ) {
            return $this->sanitizeAccountName($results[1]);
        }

        return '';
    }

    /**
     * Overloaded: ABN Amro shows description and fixes newlines etc.
     *
     * @inheritdoc
     */
    protected function parseTransactionDescription()
    {
        $results = [];
        $data = $this->getCurrentTransactionData();

        if (preg_match('#/REMI/(.+?)\n?/(EREF|IBAN|BIC|ADDR|ISDT|CSID|MARF)/#ms', $data, $results)) {
            $description = trim($results[1]);
            if (!empty($description)) {
                return $this->sanitizeDescription($description);
            }
        }

        if (preg_match('/:86:.+\n(.*)\n/', $data, $results)
            && !empty($results[1])
        ) {
            return $this->sanitizeDescription($results[1]);
        }

        return '';
    }

    /**
     * Overloaded: ABNAMRO uses the :61: date-part of the field for two values:
     * Valuetimestamp (YYMMDD) and Entry date (book date) (MMDD).
     *
     * @return int
     */
    protected function parseTransactionEntryTimestamp()
    {
        $results = [];
        if (preg_match('/^:61:(\d{2})((\d{2})\d{2})((\d{2})\d{2})[C|D]/', $this->getCurrentTransactionData(), $results)
            && !empty($results[1])
        ) {

            list(, $valueDateY, $valueDateMD, $valueDateM, $entryDateMD, $entryDateM) = $results;
            $entryDate = $valueDateY . $entryDateMD;
            if ($valueDateMD !== $entryDateMD && $valueDateM > $entryDateM) {
                $entryDate = ($valueDateY + 1) . $entryDateMD;
            }

            return $this->sanitizeTimestamp($entryDate);
        }

        return 0;
    }

    /**
     * Overloaded: Is applicable if first line has ABNA.
     *
     * @inheritdoc
     */
    public static function isApplicable($string)
    {
        $firstline = strtok($string, "\r\n\t");

        return strpos($firstline, 'ABNA') !== false;
    }
}
