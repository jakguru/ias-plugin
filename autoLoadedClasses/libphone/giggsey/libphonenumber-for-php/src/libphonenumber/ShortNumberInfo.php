<?php
/**
 * Methods for getting information about short phone numbers, such as short codes and emergency
 * numbers. Note that most commercial short numbers are not handled here, but by the
 * {@link PhoneNumberUtil}.
 *
 * @author Shaopeng Jia
 * @author David Yonge-Mallo
 * @since 5.8
 */

namespace libphonenumber;


class ShortNumberInfo
{
    const META_DATA_FILE_PREFIX = 'ShortNumberMetadata';
    /**
     * @var ShortNumberInfo
     */
    private static $instance = null;
    /**
     * @var PhoneNumberUtil
     */
    private $phoneUtil;
    private $currentFilePrefix;
    private $regionToMetadataMap = array();
    private $countryCodeToNonGeographicalMetadataMap = array();
    private static $regionsWhereEmergencyNumbersMustBeExact = array(
        'BR',
        'CL',
        'NI',
    );

    private function __construct(PhoneNumberUtil $phoneNumberUtil = null)
    {
        if ($phoneNumberUtil === null) {
            $this->phoneUtil = PhoneNumberUtil::getInstance();
        } else {
            $this->phoneUtil = $phoneNumberUtil;
        }
        $this->currentFilePrefix = dirname(__FILE__) . '/data/' . self::META_DATA_FILE_PREFIX;
    }

    /**
     * Returns the singleton instance of ShortNumberInfo
     *
     * @param PhoneNumberUtil $phoneNumberUtil Optional instance of PhoneNumber Util
     * @return \libphonenumber\ShortNumberInfo
     */
    public static function getInstance(PhoneNumberUtil $phoneNumberUtil = null)
    {
        if (null === self::$instance) {
            self::$instance = new self($phoneNumberUtil);
        }

        return self::$instance;
    }

    public static function resetInstance()
    {
        self::$instance = null;
    }

    public function getSupportedRegions()
    {
        return ShortNumbersRegionCodeSet::$shortNumbersRegionCodeSet;
    }

    /**
     * Gets a valid short number for the specified region.
     *
     * @param $regionCode String the region for which an example short number is needed
     * @return string a valid short number for the specified region. Returns an empty string when the
     *      metadata does not contain such information.
     */
    public function getExampleShortNumber($regionCode)
    {
        $phoneMetadata = $this->getMetadataForRegion($regionCode);
        if ($phoneMetadata === null) {
            return "";
        }

        /** @var PhoneNumberDesc $desc */
        $desc = $phoneMetadata->getShortCode();
        if ($desc !== null && $desc->hasExampleNumber()) {
            return $desc->getExampleNumber();
        }
        return "";
    }

    /**
     * @param $regionCode
     * @return PhoneMetadata|null
     */
    public function getMetadataForRegion($regionCode)
    {
        if (!in_array($regionCode, ShortNumbersRegionCodeSet::$shortNumbersRegionCodeSet)) {
            return null;
        }

        if (!isset($this->regionToMetadataMap[$regionCode])) {
            // The regionCode here will be valid and won't be '001', so we don't need to worry about
            // what to pass in for the country calling code.
            $this->loadMetadataFromFile($this->currentFilePrefix, $regionCode, 0);
        }

        return isset($this->regionToMetadataMap[$regionCode]) ? $this->regionToMetadataMap[$regionCode] : null;
    }

    private function loadMetadataFromFile($filePrefix, $regionCode, $countryCallingCode)
    {
        $isNonGeoRegion = PhoneNumberUtil::REGION_CODE_FOR_NON_GEO_ENTITY === $regionCode;
        $fileName = $filePrefix . '_' . ($isNonGeoRegion ? $countryCallingCode : $regionCode) . '.php';
        if (!is_readable($fileName)) {
            throw new \Exception('missing metadata: ' . $fileName);
        } else {
            $data = include $fileName;
            $metadata = new PhoneMetadata();
            $metadata->fromArray($data);
            if ($isNonGeoRegion) {
                $this->countryCodeToNonGeographicalMetadataMap[$countryCallingCode] = $metadata;
            } else {
                $this->regionToMetadataMap[$regionCode] = $metadata;
            }
        }
    }

    /**
     *  Gets a valid short number for the specified cost category.
     *
     * @param string $regionCode the region for which an example short number is needed
     * @param int $cost the cost category of number that is needed
     * @return string a valid short number for the specified region and cost category. Returns an empty string
     * when the metadata does not contain such information, or the cost is UNKNOWN_COST.
     */
    public function getExampleShortNumberForCost($regionCode, $cost)
    {
        $phoneMetadata = $this->getMetadataForRegion($regionCode);
        if ($phoneMetadata === null) {
            return "";
        }

        /** @var PhoneNumberDesc $desc */
        $desc = null;
        switch ($cost) {
            case ShortNumberCost::TOLL_FREE:
                $desc = $phoneMetadata->getTollFree();
                break;
            case ShortNumberCost::STANDARD_RATE:
                $desc = $phoneMetadata->getStandardRate();
                break;
            case ShortNumberCost::PREMIUM_RATE:
                $desc = $phoneMetadata->getPremiumRate();
                break;
            default:
                // UNKNOWN_COST numbers are computed by the process of elimination from the other cost categories
                break;
        }

        if ($desc !== null && $desc->hasExampleNumber()) {
            return $desc->getExampleNumber();
        }

        return "";
    }

    /**
     * Returns true if the number might be used to connect to an emergency service in the given region
     *
     * This method takes into account cases where the number might contain formatting, or might have
     * additional digits appended (when it is okay to do that in the region specified).
     *
     * @param string $number the phone number to test
     * @param string $regionCode the region where the phone number if being dialled
     * @return boolean whether the number might be used to connect to an emergency service in the given region
     */
    public function connectsToEmergencyNumber($number, $regionCode)
    {
        return $this->matchesEmergencyNumberHelper($number, $regionCode, true /* allows prefix match */);
    }

    private function matchesEmergencyNumberHelper($number, $regionCode, $allowPrefixMatch)
    {
        $number = PhoneNumberUtil::extractPossibleNumber($number);
        $matcher = new Matcher(PhoneNumberUtil::$PLUS_CHARS_PATTERN, $number);
        if ($matcher->lookingAt()) {
            // Returns false if the number starts with a plus sign. WE don't believe dialling the country
            // code before emergency numbers (e.g. +1911) works, but later, if that proves to work, we can
            // add additional logic here to handle it.
            return false;
        }

        $metadata = $this->getMetadataForRegion($regionCode);
        if ($metadata === null || !$metadata->hasEmergency()) {
            return false;
        }

        $emergencyNumberPattern = $metadata->getEmergency()->getNationalNumberPattern();
        $normalizedNumber = PhoneNumberUtil::normalizeDigitsOnly($number);

        $emergencyMatcher = new Matcher($emergencyNumberPattern, $normalizedNumber);

        return (!$allowPrefixMatch || in_array($regionCode, self::$regionsWhereEmergencyNumbersMustBeExact))
            ? $emergencyMatcher->matches()
            : $emergencyMatcher->lookingAt();
    }

    /**
     * Given a valid short number, determines whether it is carrier-specific (however, nothing is
     * implied about its validity). If it is important that the number is valid, then its validity
     * must first be checked using {@link isValidShortNumber} or
     * {@link #isValidShortNumberForRegion}.
     *
     * @param PhoneNumber $number the valid short number to check
     * @return boolean whether the short number is carrier-specific (assuming the input was a valid short
     *     number).
     */
    public function isCarrierSpecific(PhoneNumber $number)
    {
        $regionCodes = $this->phoneUtil->getRegionCodesForCountryCode($number->getCountryCode());
        $regionCode = $this->getRegionCodeForShortNumberFromRegionList($number, $regionCodes);
        $nationalNumber = $this->phoneUtil->getNationalSignificantNumber($number);
        $phoneMetadata = $this->getMetadataForRegion($regionCode);

        return ($phoneMetadata != null) && ($this->phoneUtil->isNumberMatchingDesc(
            $nationalNumber,
            $phoneMetadata->getCarrierSpecific()
        ));
    }

    /**
     * Helper method to get the region code for a given phone number, from a list of possible region
     * codes. If the list contains more than one region, the first region for which the number is
     * valid is returned.
     *
     * @param PhoneNumber $number
     * @param $regionCodes
     * @return String|null Region Code (or null if none are found)
     */
    private function getRegionCodeForShortNumberFromRegionList(PhoneNumber $number, $regionCodes)
    {
        if (count($regionCodes) == 0) {
            return null;
        } elseif (count($regionCodes) == 1) {
            return $regionCodes[0];
        }

        $nationalNumber = $this->phoneUtil->getNationalSignificantNumber($number);

        foreach ($regionCodes as $regionCode) {
            $phoneMetadata = $this->getMetadataForRegion($regionCode);
            if ($phoneMetadata != null && $this->phoneUtil->isNumberMatchingDesc(
                    $nationalNumber,
                    $phoneMetadata->getShortCode()
                )
            ) {
                // The number is valid for this region.
                return $regionCode;
            }
        }
        return null;
    }

    /**
     * Check whether a short number is a possible number. If a country calling code is shared by
     * multiple regions, this returns true if it's possible in any of them. This provides a more
     * lenient check than {@link #isValidShortNumber}. See {@link
     * #IsPossibleShortNumberForRegion(String, String)} for details.
     *
     * @param $number PhoneNumber the short number to check
     * @return boolean whether the number is a possible short number
     */
    public function isPossibleShortNumber(PhoneNumber $number)
    {
        $regionCodes = $this->phoneUtil->getRegionCodesForCountryCode($number->getCountryCode());
        $shortNumber = $this->phoneUtil->getNationalSignificantNumber($number);

        foreach ($regionCodes as $region) {
            $phoneMetadata = $this->getMetadataForRegion($region);
            if ($this->phoneUtil->isNumberPossibleForDesc($shortNumber, $phoneMetadata->getGeneralDesc())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether a short number is a possible number when dialled from a region, given the number
     * in the form of a string, and the region where the number is dialled from. This provides a more
     * lenient check than {@link #isValidShortNumber}.
     *
     * @param $shortNumber String The short number to check
     * @param $regionDialingFrom String Region dialing From
     * @return boolean whether the number is a possible short number
     */
    public function isPossibleShortNumberForRegion($shortNumber, $regionDialingFrom)
    {
        $phoneMetadata = $this->getMetadataForRegion($regionDialingFrom);

        if ($phoneMetadata === null) {
            return false;
        }

        $generalDesc = $phoneMetadata->getGeneralDesc();

        return $this->phoneUtil->isNumberPossibleForDesc($shortNumber, $generalDesc);
    }

    /**
     * Tests whether a short number matches a valid pattern. If a country calling code is shared by
     * multiple regions, this returns true if it's valid in any of them. Note that this doesn't verify
     * the number is actually in use, which is impossible to tell by just looking at the number
     * itself. See {@link #isValidShortNumberForRegion(String, String)} for details.
     *
     * @param $number PhoneNumber the short number for which we want to test the validity
     * @return boolean whether the short number matches a valid pattern
     */
    public function isValidShortNumber(PhoneNumber $number)
    {
        $regionCodes = $this->phoneUtil->getRegionCodesForCountryCode($number->getCountryCode());
        $shortNumber = $this->phoneUtil->getNationalSignificantNumber($number);
        $regionCode = $this->getRegionCodeForShortNumberFromRegionList($number, $regionCodes);
        if (count($regionCodes) > 1 && $regionCode !== null) {
            // If a matching region had been found for the phone number from among two or more regions,
            // then we have already implicitly verified its validity for that region.
            return true;
        }

        return $this->isValidShortNumberForRegion($shortNumber, $regionCode);
    }

    /**
     * Tests whether a short number matches a valid pattern in a region. Note that this doesn't verify
     * the number is actually in use, which is impossible to tell by just looking at the number
     * itself.
     *
     * @param $shortNumber
     * @param $regionDialingFrom
     * @return bool
     */
    public function isValidShortNumberForRegion($shortNumber, $regionDialingFrom)
    {
        $phoneMetadata = $this->getMetadataForRegion($regionDialingFrom);

        if ($phoneMetadata === null) {
            return false;
        }

        $generalDesc = $phoneMetadata->getGeneralDesc();

        if (!$generalDesc->hasNationalNumberPattern() || !$this->phoneUtil->isNumberMatchingDesc(
                $shortNumber,
                $generalDesc
            )
        ) {
            return false;
        }

        $shortNumberDesc = $phoneMetadata->getShortCode();
        if (!$shortNumberDesc->hasNationalNumberPattern()) {
            // No short code national number pattern found for region
            return false;
        }

        return $this->phoneUtil->isNumberMatchingDesc($shortNumber, $shortNumberDesc);
    }

    /**
     * Gets the expected cost category of a short number  when dialled from a region (however, nothing is
     * implied about its validity). If it is important that the number is valid, then its validity
     * must first be checked using {@link isValidShortNumberForRegion}. Note that emergency numbers
     * are always considered toll-free.
     * Example usage:
     * <pre>{@code
     * $shortInfo = ShortNumberInfo::getInstance();
     * $shortNumber = "110";
     * $regionCode = "FR";
     * if ($shortInfo->isValidShortNumberForRegion($shortNumber, $regionCode)) {
     *     $cost = $shortInfo->getExpectedCostForRegion($shortNumber, $regionCode);
     *    // Do something with the cost information here.
     * }}</pre>
     *
     * @param $shortNumber String the short number for which we want to know the expected cost category,
     *     as a string
     * @param $regionDialingFrom String the region from which the number is dialed
     * @return int the expected cost category for that region of the short number. Returns UNKNOWN_COST if
     *     the number does not match a cost category. Note that an invalid number may match any cost
     *     category.
     */
    public function getExpectedCostForRegion($shortNumber, $regionDialingFrom)
    {
        // Note that regionDialingFrom may be null, in which case phoneMetadata will also be null.
        $phoneMetadata = $this->getMetadataForRegion($regionDialingFrom);
        if ($phoneMetadata === null) {
            return ShortNumberCost::UNKNOWN_COST;
        }

        // The cost categories are tested in order of decreasing expense, since if for some reason the
        // patterns overlap the most expensive matching cost category should be returned.
        if ($this->phoneUtil->isNumberMatchingDesc($shortNumber, $phoneMetadata->getPremiumRate())) {
            return ShortNumberCost::PREMIUM_RATE;
        }

        if ($this->phoneUtil->isNumberMatchingDesc($shortNumber, $phoneMetadata->getStandardRate())) {
            return ShortNumberCost::STANDARD_RATE;
        }

        if ($this->phoneUtil->isNumberMatchingDesc($shortNumber, $phoneMetadata->getTollFree())) {
            return ShortNumberCost::TOLL_FREE;
        }

        if ($this->isEmergencyNumber($shortNumber, $regionDialingFrom)) {
            // Emergency numbers are implicitly toll-free.
            return ShortNumberCost::TOLL_FREE;
        }

        return ShortNumberCost::UNKNOWN_COST;
    }

    /**
     * Gets the expected cost category of a short number (however, nothing is implied about its
     * validity). If the country calling code is unique to a region, this method behaves exactly the
     * same as {@link #getExpectedCostForRegion(String, String)}. However, if the country calling
     * code is shared by multiple regions, then it returns the highest cost in the sequence
     * PREMIUM_RATE, UNKNOWN_COST, STANDARD_RATE, TOLL_FREE. The reason for the position of
     * UNKNOWN_COST in this order is that if a number is UNKNOWN_COST in one region but STANDARD_RATE
     * or TOLL_FREE in another, its expected cost cannot be estimated as one of the latter since it
     * might be a PREMIUM_RATE number.
     *
     * For example, if a number is STANDARD_RATE in the US, but TOLL_FREE in Canada, the expected cost
     * returned by this method will be STANDARD_RATE, since the NANPA countries share the same country
     * calling code.
     *
     * Note: If the region from which the number is dialed is known, it is highly preferable to call
     * {@link #getExpectedCostForRegion(String, String)} instead.
     *
     * @param $number PhoneNumber the short number for which we want to know the expected cost category
     * @return int the highest expected cost category of the short number in the region(s) with the given
     *     country calling code
     */
    public function getExpectedCost(PhoneNumber $number)
    {
        $regionCodes = $this->phoneUtil->getRegionCodesForCountryCode($number->getCountryCode());
        if (count($regionCodes) == 0) {
            return ShortNumberCost::UNKNOWN_COST;
        }
        $shortNumber = $this->phoneUtil->getNationalSignificantNumber($number);
        if (count($regionCodes) == 1) {
            return $this->getExpectedCostForRegion($shortNumber, $regionCodes[0]);
        }
        $cost = ShortNumberCost::TOLL_FREE;
        foreach ($regionCodes as $regionCode) {
            $costForRegion = $this->getExpectedCostForRegion($shortNumber, $regionCode);
            switch ($costForRegion) {
                case ShortNumberCost::PREMIUM_RATE:
                    return ShortNumberCost::PREMIUM_RATE;

                case ShortNumberCost::UNKNOWN_COST:
                    $cost = ShortNumberCost::UNKNOWN_COST;
                    break;

                case ShortNumberCost::STANDARD_RATE:
                    if ($cost != ShortNumberCost::UNKNOWN_COST) {
                        $cost = ShortNumberCost::STANDARD_RATE;
                    }
                    break;
                case ShortNumberCost::TOLL_FREE:
                    // Do nothing
                    break;
            }
        }
        return $cost;
    }

    /**
     * Returns true if the number exactly matches an emergency service number in the given region.
     *
     * This method takes into account cases where the number might contain formatting, but doesn't
     * allow additional digits to be appended.
     *
     * @param string $number the phone number to test
     * @param string $regionCode the region where the phone number is being dialled
     * @return boolean whether the number exactly matches an emergency services number in the given region
     */
    public function isEmergencyNumber($number, $regionCode)
    {
        return $this->matchesEmergencyNumberHelper($number, $regionCode, false /* doesn't allow prefix match */);
    }


}

/* EOF */ 