<?php

namespace Salavert;

class OpenLocationCode
{
    private $OpenLocationCode = [];
    
    // A separator used to break the code into two parts to aid memorability.
    private $SEPARATOR_ = '+';
    
    // The number of characters to place before the separator.
    private $SEPARATOR_POSITION_ = 8;
    
    // The character used to pad codes.
    private $PADDING_CHARACTER_ = '0';
    
    // The character set used to encode the values.
    private $CODE_ALPHABET_ = '23456789CFGHJMPQRVWX';
    
    // The base to use to convert numbers to/from.
    private $ENCODING_BASE_;
    
    // The maximum value for latitude in degrees.
    private $LATITUDE_MAX_ = 90;
    
    // The maximum value for longitude in degrees.
    private $LONGITUDE_MAX_ = 180;
    
    // Maxiumum code length using lat/lng pair encoding. The area of such a
    // code is approximately 13x13 meters (at the equator), and should be suitable
    // for identifying buildings. This excludes prefix and separator characters.
    private $PAIR_CODE_LENGTH_ = 10;
    
    // The resolution values in degrees for each position in the lat/lng pair
    // encoding. These give the place value of each position, and therefore the
    // dimensions of the resulting area.
    private $PAIR_RESOLUTIONS_ = [20.0, 1.0, .05, .0025, .000125];
    
    // Number of columns in the grid refinement method.
    private $GRID_COLUMNS_ = 4;
    
    // Number of rows in the grid refinement method.
    private $GRID_ROWS_ = 5;
    
    // Size of the initial grid in degrees.
    private $GRID_SIZE_DEGREES_ = 0.000125;
    
    // Minimum length of a code that can be shortened.
    private $MIN_TRIMMABLE_CODE_LEN_ = 6;
    
    public function __construct()
    {
        $this->ENCODING_BASE_ = strlen($this->CODE_ALPHABET_);
    }

    /**
     * Returns the OLC alphabet.
     */
    public function getAlphabet()
    {
        return $this->CODE_ALPHABET_;
    }

    /**
     * Determines if a code is valid.
     * To be valid, all characters must be from the Open Location Code character
     * set with at most one separator. The separator can be in any even-numbered
     * position up to the eighth digit.
     */
    public function isValid($code)
    {
        $firstOccurrence = strpos($code, $this->SEPARATOR_);
        // The separator is required.
        if ($firstOccurrence === false) {
            echo __LINE__.PHP_EOL;
            return false;
        }
        $lastOccurrence = strrpos($code, $this->SEPARATOR_);
        if ($firstOccurrence != $lastOccurrence) {
            echo __LINE__.PHP_EOL;
            return false;
        }
        // Is it in an illegal position?
        if (($firstOccurrence > $this->SEPARATOR_POSITION_) || ($firstOccurrence % 2 == 1)) {
            echo __LINE__.PHP_EOL;
            return false;
        }
        // We can have an even number of padding characters before the separator,
        // but then it must be the final character.
        $paddingCharacters = strpos($code, $this->PADDING_CHARACTER_);
        if ($paddingCharacters !== false) {
            if ($paddingCharacters == 0) {
                echo __LINE__.PHP_EOL;
                return false;
            }
            // There can only be one group and it must have even length.
            preg_match_all('#' . $this->PADDING_CHARACTER_ . '+#U', $code, $padMatch);
            $padMatchCount = count($padMatch);
            if (($padMatchCount > 1) || (count($padMatch[0]) % 2 == 1) || ($padMatchCount > $this->SEPARATOR_POSITION_ - 2)) {
                return false;
            }
            // If the code is long enough to end with a separator, make sure it does.
            if (substr($code, -1) != $this->SEPARATOR_) {
                echo __LINE__.PHP_EOL;
                return false;
            }
        }
        // If there are characters after the separator, make sure there isn't just one of them (not legal).
        if ((strlen($code) - $firstOccurrence - 1) == 1) {
            echo __LINE__.PHP_EOL;
            return false;
        }

        // Strip the separator and any padding characters.
        $code = preg_replace('#\\' . $this->SEPARATOR_ . '+#', '', $code);
        $code = preg_replace('#'.$this->PADDING_CHARACTER_ . '+#', '', $code);

        // Check the code contains only valid characters.
        $characters = str_split($code);
        foreach ($characters as $character) {
            $character = strtoupper($character);
            if (($character != $this->SEPARATOR_) && (strpos($this->CODE_ALPHABET_, $character) === false)) {
                return false;
            }
        }
        return true;
    }

}


















