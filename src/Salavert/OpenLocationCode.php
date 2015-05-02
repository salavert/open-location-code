<?php

namespace Salavert;

class OpenLocationCode
{
    private $OpenLocationCode = [];

    // The maximum value for latitude in degrees.
    const LATITUDE_MAX_ = 90;

    // The maximum value for longitude in degrees.
    const LONGITUDE_MAX_ = 180;

    // Maximum code length using lat/lng pair encoding. The area of such a
    // code is approximately 13x13 meters (at the equator), and should be suitable
    // for identifying buildings. This excludes prefix and separator characters.
    const PAIR_CODE_LENGTH_ = 10;

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
    
    // The resolution values in degrees for each position in the lat/lng pair
    // encoding. These give the place value of each position, and therefore the
    // dimensions of the resulting area.
    private $PAIR_RESOLUTIONS_ = array(20.0, 1.0, .05, .0025, .000125);
    
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
     * To be valid, all characters must be from the Open Location Code character set with at most one separator. The
     * separator can be in any even-numbered position up to the eighth digit.
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
     * A short Open Location Code is a sequence created by removing four or more digits from an Open Location Code.
     * It must include a separator character.
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
     * Not all possible combinations of Open Location Code characters decode to valid latitude and longitude values.
     * This checks that a code is valid and also that the latitude and longitude values are legal. If the prefix
     * character is present, it must be the first character. If the separator character is present, it must be after
     * four characters.
     *
     * @param string $code
     * @return bool
     */
    public function isFull($code)
    {
        // If it's short, it's not full.
        if (!$this->isValid($code) || $this->isShort($code)) {
            return false;
        }

        // Work out what the first latitude character indicates for latitude.
        $firstLatValue = strpos($this->CODE_ALPHABET_, strtoupper($code[0])) * $this->ENCODING_BASE_;
        if ($firstLatValue >= self::LATITUDE_MAX_ * 2) {
            // The code would decode to a latitude of >= 90 degrees.
            return false;
        }
        if (strlen($code) > 1) {
            // Work out what the first longitude character indicates for longitude.
            $firstLngValue = strpos($this->CODE_ALPHABET_, strtoupper($code[1])) * $this->ENCODING_BASE_;
            if ($firstLngValue >= self::LONGITUDE_MAX_ * 2) {
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
            $codeLength = self::PAIR_CODE_LENGTH_;
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
        $code = $this->encodePairs($latitude, $longitude, min($codeLength, self::PAIR_CODE_LENGTH_));
        // If the requested length indicates we want grid refined codes.
        if ($codeLength > self::PAIR_CODE_LENGTH_) {
            $code .= $this->encodeGrid($latitude, $longitude, $codeLength - self::PAIR_CODE_LENGTH_);
        }
        return $code;
    }

    /**
     * Decodes an Open Location Code into the location coordinates.
     * Returns a CodeArea object that includes the coordinates of the bounding box - the lower left, center and upper
     * right.
     *
     * @param string $code The Open Location Code to decode.
     * @return CodeArea that provides the latitude and longitude of two of the corners of the area, the center, and the
     * length of the original code.
     * @throws \Exception
     */
    public function decode($code)
    {
        if (!$this->isFull($code)) {
            throw new \Exception("IllegalArgumentException: Passed Open Location Code is not a valid full code: $code");
        }
        // Strip out separator character (we've already established the code is valid so the maximum is one), padding
        // characters and convert to upper case.
        $code = str_replace($this->SEPARATOR_, '', $code);
        $code = preg_replace('#'.$this->PADDING_CHARACTER_ . '+#', '', $code);
        $code = strtoupper($code);
        // Decode the lat/lng pair component.
        $codeArea = $this->decodePairs(substr($code, 0, self::PAIR_CODE_LENGTH_));
        // If there is a grid refinement component, decode that.
        if (strlen($code) <= self::PAIR_CODE_LENGTH_) {
            return $codeArea;
        }
        $gridArea = $this->decodeGrid(substr($code, self::PAIR_CODE_LENGTH_));
        return new CodeArea(
            $codeArea->latitudeLo + $gridArea->latitudeLo,
            $codeArea->longitudeLo + $gridArea->longitudeLo,
            $codeArea->latitudeLo + $gridArea->latitudeHi,
            $codeArea->longitudeLo + $gridArea->longitudeHi,
            $codeArea->codeLength + $gridArea->codeLength
        );
    }

    /**
    Recover the nearest matching code to a specified location.
    Given a short Open Location Code of between four and seven characters,
    this recovers the nearest matching full code to the specified location.
    The number of characters that will be prepended to the short code, depends
    on the length of the short code and whether it starts with the separator.
    If it starts with the separator, four characters will be prepended. If it
    does not, the characters that will be prepended to the short code, where S
    is the supplied short code and R are the computed characters, are as
    follows:
    SSSS    -> RRRR.RRSSSS
    SSSSS   -> RRRR.RRSSSSS
    SSSSSS  -> RRRR.SSSSSS
    SSSSSSS -> RRRR.SSSSSSS
    Note that short codes with an odd number of characters will have their
    last character decoded using the grid refinement algorithm.
    Args:
    shortCode: A valid short OLC character sequence.
    referenceLatitude: The latitude (in signed decimal degrees) to use to
    find the nearest matching full code.
    referenceLongitude: The longitude (in signed decimal degrees) to use
    to find the nearest matching full code.
    Returns:
    The nearest full Open Location Code to the reference location that matches
    the short code. Note that the returned code may not have the same
    computed characters as the reference location. This is because it returns
    the nearest match, not necessarily the match within the same cell. If the
    passed code was not a valid short code, but was a valid full code, it is
    returned unchanged.
     */
    /**
     * @param string $shortCode
     * @param float $referenceLatitude
     * @param float $referenceLongitude
     * @return float
     * @throws \Exception
     */
    public function recoverNearest($shortCode, $referenceLatitude, $referenceLongitude)
    {
        if (!$this->isShort($shortCode)) {
            if ($this->isFull($shortCode)) {
                return $shortCode;
            } else {
                throw new \Exception("ValueError: Passed short code is not valid: $shortCode");
            }
        }
        // Ensure that latitude and longitude are valid.
        $referenceLatitude = $this->clipLatitude($referenceLatitude);
        $referenceLongitude = $this->normalizeLongitude($referenceLongitude);
        // Clean up the passed code.
        $shortCode = strtoupper($shortCode);
        // Compute the number of digits we need to recover.
        $paddingLength = $this->SEPARATOR_POSITION_ - strpos($shortCode, $this->SEPARATOR_);
        // The resolution (height and width) of the padded area in degrees.
        $resolution = pow(20, 2 - ($paddingLength / 2));
        // Distance from the center to an edge (in degrees).
        $areaToEdge = $resolution / 2.0;

        // Now round down the reference latitude and longitude to the resolution.
        $roundedLatitude = floor($referenceLatitude / $resolution) * $resolution;
        $roundedLongitude = floor($referenceLongitude / $resolution) * $resolution;

        // Use the reference location to pad the supplied short code and decode it.
        $codeArea = $this->decode(substr($this->encode($roundedLatitude, $roundedLongitude), 0, $paddingLength) . $shortCode);

        // How many degrees latitude is the code from the reference? If it is more
        // than half the resolution, we need to move it east or west.
        $degreesDifference = $codeArea->latitudeCenter - $referenceLatitude;
        if ($degreesDifference > $areaToEdge) {
            // If the center of the short code is more than half a cell east,
            // then the best match will be one position west.
            $codeArea->latitudeCenter -= $resolution;
        } else if ($degreesDifference < -$areaToEdge) {
            // If the center of the short code is more than half a cell west,
            // then the best match will be one position east.
            $codeArea->latitudeCenter += $resolution;
        }

        // How many degrees longitude is the code from the reference?
        $degreesDifference = $codeArea->longitudeCenter - $referenceLongitude;
        if ($degreesDifference > $areaToEdge) {
            $codeArea->longitudeCenter -= $resolution;
        } else if ($degreesDifference < -$areaToEdge) {
            $codeArea->longitudeCenter += $resolution;
        }
        return $this->encode($codeArea->latitudeCenter, $codeArea->longitudeCenter, $codeArea->codeLength);
    }

    /**
     * Remove characters from the start of an OLC code.
     * This uses a reference location to determine how many initial characters can be removed from the OLC code. The
     * number of characters that can be removed depends on the distance between the code center and the reference
     * location.
     * The minimum number of characters that will be removed is four. If more than four characters can be removed, the
     * additional characters will be replaced with the padding character. At most eight characters will be removed.
     * The reference location must be within 50% of the maximum range. This ensures that the shortened code will be
     * able to be recovered using slightly different locations.
     *
     * @param string $code A full, valid code to shorten.
     * @param float $latitude A latitude, in signed decimal degrees, to use as the reference point.
     * @param float $longitude A longitude, in signed decimal degrees, to use as the reference point.
     * @return string Either the original code, if the reference location was not close enough, or the .
     * @throws \Exception
     */
    public function shorten($code, $latitude, $longitude)
    {
        if (!$this->isFull($code)) {
            throw new \Exception("ValueError: Passed code is not valid and full: $code");
        }
        if (strpos($code, $this->PADDING_CHARACTER_) !== false) {
            throw new \Exception("ValueError: Cannot shorten padded codes: $code");
        }
        $code = strtoupper($code);
        $codeArea = $this->decode($code);
        if ($codeArea->codeLength < $this->MIN_TRIMMABLE_CODE_LEN_) {
            throw new \Exception("ValueError: Code length must be at least " . $this->MIN_TRIMMABLE_CODE_LEN_);
        }
        // Ensure that latitude and longitude are valid.
        $latitude = $this->clipLatitude($latitude);
        $longitude = $this->normalizeLongitude($longitude);
        // How close are the latitude and longitude to the code center.
        $range = max(
            abs($codeArea->latitudeCenter - $latitude),
            abs($codeArea->longitudeCenter - $longitude)
        );
        for ($i = count($this->PAIR_RESOLUTIONS_) - 2; $i >= 1; $i--) {
            // Check if we're close enough to shorten. The range must be less than 1/2
            // the resolution to shorten at all, and we want to allow some safety, so
            // use 0.3 instead of 0.5 as a multiplier.
            if ($range < ($this->PAIR_RESOLUTIONS_[$i] * 0.3)) {
                // Trim it.
                return substr($code, ($i + 1) * 2);
            }
        }
        return $code;
    }

    /**
     * Decode an OLC code made up of lat/lng pairs.
     * This decodes an OLC code made up of alternating latitude and longitude characters, encoded using base 20.
     *
     * @param string $code A valid OLC code, presumed to be full, but with the separator removed.
     * @return CodeArea
     */
    private function decodePairs($code)
    {
        // Get the latitude and longitude values. These will need correcting from positive ranges.
        $latitude = $this->decodePairsSequence($code, 0);
        $longitude = $this->decodePairsSequence($code, 1);
        // Correct the values and set them into the CodeArea object.
        return new CodeArea(
            $latitude[0] - self::LATITUDE_MAX_,
            $longitude[0] - self::LONGITUDE_MAX_,
            $latitude[1] - self::LATITUDE_MAX_,
            $longitude[1] - self::LONGITUDE_MAX_,
            strlen($code)
        );
    }

    /**
     * Decode either a latitude or longitude sequence.
     * This decodes the latitude or longitude sequence of a lat/lng pair encoding.
     * Starting at the character at position offset, every second character is decoded and the value returned.
     *
     * @param string $code A valid OLC code, presumed to be full, with the separator removed.
     * @param int $offset The character to start from.
     * @return array A pair of the low and high values. The low value comes from decoding the characters. The high
     * value is the low value plus the resolution of the last position. Both values are offset into positive ranges and
     * will need to be corrected before use.
     */
    private function decodePairsSequence($code, $offset)
    {
        $i = 0;
        $value = 0;
        while ($i * 2 + $offset < strlen($code)) {
            $value += strpos($this->CODE_ALPHABET_, $code[$i * 2 + $offset]) * $this->PAIR_RESOLUTIONS_[$i];
            $i += 1;
        }
        return array(
            $value,
            $value + $this->PAIR_RESOLUTIONS_[$i - 1]
        );
    }

    /**
     * Decode the grid refinement portion of an OLC code.
     * This decodes an OLC code using the grid refinement method.
     *
     * @param string $code A valid OLC code sequence that is only the grid refinement portion. This is the portion of a
     * code starting at position 11.
     * @return CodeArea
     */
    private function decodeGrid($code)
    {
        $latitudeLo = 0.0;
        $longitudeLo = 0.0;
        $latPlaceValue = $this->GRID_SIZE_DEGREES_;
        $lngPlaceValue = $this->GRID_SIZE_DEGREES_;
        $i = 0;
        while ($i < strlen($code)) {
            $codeIndex = strpos($this->CODE_ALPHABET_, $code[$i]);
            $row = floor($codeIndex / $this->GRID_COLUMNS_);
            $col = $codeIndex % $this->GRID_COLUMNS_;

            $latPlaceValue /= $this->GRID_ROWS_;
            $lngPlaceValue /= $this->GRID_COLUMNS_;

            $latitudeLo += $row * $latPlaceValue;
            $longitudeLo += $col * $lngPlaceValue;
            $i += 1;
        }
        return new CodeArea(
            $latitudeLo,
            $longitudeLo,
            $latitudeLo + $latPlaceValue,
            $longitudeLo + $lngPlaceValue,
            strlen($code)
        );
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
        $adjustedLatitude = ($latitude + self::LATITUDE_MAX_) % $latPlaceValue;
        $adjustedLongitude = ($longitude + self::LONGITUDE_MAX_) % $lngPlaceValue;
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
        $adjustedLatitude = $latitude + self::LATITUDE_MAX_;
        $adjustedLongitude = $longitude + self::LONGITUDE_MAX_;
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
        return $code;
    }
}



















