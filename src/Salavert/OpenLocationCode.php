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
     *
     * @param string $code
     * @return bool
     */
    public function isValid($code)
    {
        $firstOccurrence = strpos($code, $this->SEPARATOR_);
        // The separator is required.
        if ($firstOccurrence === false) {
            return false;
        }
        $lastOccurrence = strrpos($code, $this->SEPARATOR_);
        if ($firstOccurrence != $lastOccurrence) {
            return false;
        }
        // Is it in an illegal position?
        if (($firstOccurrence > $this->SEPARATOR_POSITION_) || ($firstOccurrence % 2 == 1)) {
            return false;
        }
        // We can have an even number of padding characters before the separator,
        // but then it must be the final character.
        $paddingCharacters = strpos($code, $this->PADDING_CHARACTER_);
        if ($paddingCharacters !== false) {
            if ($paddingCharacters == 0) {
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
                return false;
            }
        }
        // If there are characters after the separator, make sure there isn't just one of them (not legal).
        if ((strlen($code) - $firstOccurrence - 1) == 1) {
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

    /**
     * Determines if a code is a valid short code.
     * A short Open Location Code is a sequence created by removing four or more
     * digits from an Open Location Code. It must include a separator
     * character.
     *
     * @param string $code
     * @return bool
     */
    public function isShort($code)
    {
        if (!$this->isValid($code)) {
            return false;
        }
        // If there are less characters than expected before the SEPARATOR.
        $firstOccurrence = strpos($code, $this->SEPARATOR_);
        if (($firstOccurrence >= 0) && ($firstOccurrence < $this->SEPARATOR_POSITION_)) {
            return true;
        }
        return false;
    }

    /**
     * Determines if a code is a valid full Open Location Code.
     * Not all possible combinations of Open Location Code characters decode to
     * valid latitude and longitude values. This checks that a code is valid
     * and also that the latitude and longitude values are legal. If the prefix
     * character is present, it must be the first character. If the separator
     * character is present, it must be after four characters.
     */
    public function isFull($code)
    {
        // If it's short, it's not full.
        if (!$this->isValid($code) || $this->isShort($code)) {
            return false;
        }

        // Work out what the first latitude character indicates for latitude.
        $firstLatValue = strpos($this->CODE_ALPHABET_, strtoupper($code[0])) * $this->ENCODING_BASE_;
        if ($firstLatValue >= $this->LATITUDE_MAX_ * 2) {
            // The code would decode to a latitude of >= 90 degrees.
            return false;
        }
        if (strlen($code) > 1) {
            // Work out what the first longitude character indicates for longitude.
            $firstLngValue = strpos($this->CODE_ALPHABET_, strtoupper($code[1])) * $this->ENCODING_BASE_;
            if ($firstLngValue >= $this->LONGITUDE_MAX_ * 2) {
                // The code would decode to a longitude of >= 180 degrees.
                return false;
            }
        }
        return true;
    }

    /**
     * Encode a location into an Open Location Code.
     * Produces a code of the specified length, or the default length if no length is provided.
     * The length determines the accuracy of the code. The default length is 10 characters, returning a code of
     * approximately 13.5x13.5 meters. Longer codes represent smaller areas, but lengths > 14 are sub-centimetre and so
     * 11 or 12 are probably the limit of useful codes.
     *
     * @param float $latitude A latitude in signed decimal degrees. Will be clipped to the range -90 to 90.
     * @param float $longitude A longitude in signed decimal degrees. Will be normalised to the range -180 to 180.
     * @param float|null $codeLength The number of significant digits in the output code, not including any separator
     * characters.
     * @return float
     * @throws \Exception
     */
    public function encode($latitude, $longitude, $codeLength = null)
    {
        if ($codeLength === null){
            $codeLength = $this->PAIR_CODE_LENGTH_;
        }
        if (($codeLength < 2) || ($codeLength < $this->SEPARATOR_POSITION_) && ($codeLength % 2 == 1)){
            throw new \Exception("IllegalArgumentException: Invalid Open Location Code length");
        }
        // Ensure that latitude and longitude are valid.
        $latitude = $this->clipLatitude($latitude);
        $longitude = $this->normalizeLongitude($longitude);
        // Latitude 90 needs to be adjusted to be just less, so the returned code
        // can also be decoded.
        if ($latitude == 90) {
            $latitude = $latitude - $this->computeLatitudePrecision($codeLength);
        }
        $code = $this->encodePairs($latitude, $longitude, min($codeLength, $this->PAIR_CODE_LENGTH_));
        // If the requested length indicates we want grid refined codes.
        if ($codeLength > $this->PAIR_CODE_LENGTH_) {
            $code .= $this->encodeGrid($latitude, $longitude, $codeLength - $this->PAIR_CODE_LENGTH_);
        }
        return $code;
    }

    /**
     * Clip a latitude into the range -90 to 90.
     *
     * @param float $latitude A latitude in signed decimal degrees.
     * @return float
     */
    private function clipLatitude($latitude)
    {
        return min(90, max(-90, $latitude));
    }

    /**
     * Normalize a longitude into the range -180 to 180, not including 180.
     *
     * @param float $longitude A longitude in signed decimal degrees.
     * @return float
     */
    private function normalizeLongitude($longitude)
    {
        while ($longitude < -180) {
            $longitude = $longitude + 360;
        }
        while ($longitude >= 180) {
            $longitude = $longitude - 360;
        }
        return $longitude;
    }

    /**
     * Compute the latitude precision value for a given code length. Lengths <=
     * 10 have the same precision for latitude and longitude, but lengths > 10
     * have different precisions due to the grid method having fewer columns than
     * rows.
     *
     * @param int $codeLength
     * @return int
     */
    private function computeLatitudePrecision($codeLength)
    {
        if ($codeLength <= 10) {
            return pow(20, floor($codeLength / -2 + 2));
        }
        return pow(20, -3) / pow($this->GRID_ROWS_, $codeLength - 10);
    }

    /**
     * Encode a location using the grid refinement method into an OLC string.
     * The grid refinement method divides the area into a grid of 4x5, and uses a
     * single character to refine the area. This allows default accuracy OLC codes
     * to be refined with just a single character.
     *
     * @param float $latitude A latitude in signed decimal degrees.
     * @param float $longitude A longitude in signed decimal degrees.
     * @param float $codeLength The number of characters required.
     * @return mixed
     */
    private function encodeGrid($latitude, $longitude, $codeLength)
    {
        $code = '';
        $latPlaceValue = $this->GRID_SIZE_DEGREES_;
        $lngPlaceValue = $this->GRID_SIZE_DEGREES_;
        // Adjust latitude and longitude so they fall into positive ranges and get the offset for the required places.
        $adjustedLatitude = ($latitude + $this->LATITUDE_MAX_) % $latPlaceValue;
        $adjustedLongitude = ($longitude + $this->LONGITUDE_MAX_) % $lngPlaceValue;
        for ($i = 0; $i < $codeLength; $i++) {
            // Work out the row and column.
            $row = (int) floor($adjustedLatitude / ($latPlaceValue / $this->GRID_ROWS_));
            $col = (int) floor($adjustedLongitude / ($lngPlaceValue / $this->GRID_COLUMNS_));
            $latPlaceValue /= $this->GRID_ROWS_;
            $lngPlaceValue /= $this->GRID_COLUMNS_;
            $adjustedLatitude -= $row * $latPlaceValue;
            $adjustedLongitude -= $col * $lngPlaceValue;
            $code .= $this->CODE_ALPHABET_[$row * $this->GRID_COLUMNS_ + $col];
        }
        return $code;
    }

    /**
     * Encode a location into a sequence of OLC lat/lng pairs.
     * This uses pairs of characters (longitude and latitude in that order) to
     * represent each step in a 20x20 grid. Each code, therefore, has 1/400th
     * the area of the previous code.
     *
     * @param float $latitude A latitude in signed decimal degrees.
     * @param float $longitude A longitude in signed decimal degrees.
     * @param float $codeLength The number of significant digits in the output code, not. including any separator
     * characters
     * @return string
     */
    private function encodePairs($latitude, $longitude, $codeLength)
    {
        $code = '';
        // Adjust latitude and longitude so they fall into positive ranges.
        $adjustedLatitude = $latitude + $this->LATITUDE_MAX_;
        $adjustedLongitude = $longitude + $this->LONGITUDE_MAX_;
        // Count digits - can't use string length because it may include a separator character.
        $digitCount = 0;
        while ($digitCount < $codeLength) {
            // Provides the value of digits in this place in decimal degrees.
            $placeValue = $this->PAIR_RESOLUTIONS_[(int) floor($digitCount / 2)];
            // Do the latitude - gets the digit for this place and subtracts that for the next digit.
            $digitValue = (int) floor($adjustedLatitude / $placeValue);
            $adjustedLatitude -= $digitValue * $placeValue;
            $code .= $this->CODE_ALPHABET_[$digitValue];
            $digitCount += 1;
            if ($digitCount == $codeLength) {
                break;
            }
            // And do the longitude - gets the digit for this place and subtracts that for the next digit.
            $digitValue = (int) floor($adjustedLongitude / $placeValue);
            $adjustedLongitude -= $digitValue * $placeValue;
            $code .= $this->CODE_ALPHABET_[$digitValue];
            $digitCount += 1;
            // Should we add a separator here?
            if (($digitCount == $this->SEPARATOR_POSITION_) && ($digitCount < $codeLength)) {
                $code .= $this->SEPARATOR_;
            }
        }
        if (strlen($code) < $this->SEPARATOR_POSITION_) {
            $code .= join($this->PADDING_CHARACTER_, array($this->SEPARATOR_POSITION_ - strlen($code) + 1));
        }
        if (strlen($code) == $this->SEPARATOR_POSITION_) {
            $code .= $this->SEPARATOR_;
        }
        echo PHP_EOL.'$code '.$code;
        return $code;
    }
}



















