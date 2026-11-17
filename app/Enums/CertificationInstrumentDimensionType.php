<?php

namespace App\Enums;

/** A choose-one dimension takes one option per submission; a sections list takes many, each optionally scored. */
enum CertificationInstrumentDimensionType: string
{
    case ChooseOne = 'choose_one';
    case Sections = 'sections';
}
