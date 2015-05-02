<?php

namespace Salavert;

class CodeArea
{
    public $latitudeLo;
    public $longitudeLo;
    public $latitudeHi;
    public $longitudeHi;
    public $codeLength;
    public $latitudeCenter;
    public $longitudeCenter;

    public function __construct($latitudeLo, $longitudeLo, $latitudeHi, $longitudeHi, $codeLength)
    {
        $this->latitudeLo = $latitudeLo;
        $this->longitudeLo = $longitudeLo;
        $this->latitudeHi = $latitudeHi;
        $this->longitudeHi = $longitudeHi;
        $this->codeLength = $codeLength;
        $this->latitudeCenter = min($latitudeLo + ($latitudeHi - $latitudeLo) / 2, OpenLocationCode::LATITUDE_MAX_);
        $this->longitudeCenter = min($longitudeLo + ($longitudeHi - $longitudeLo) / 2, OpenLocationCode::LONGITUDE_MAX_);
    }
}