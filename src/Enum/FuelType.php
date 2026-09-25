<?php

declare(strict_types=1);

namespace App\Enum;

enum FuelType: string
{
    case GASOLINE = 'gasoline';
    case DIESEL = 'diesel';
    /* GPL */
    case LPG = 'lpg';
    /* METANO */
    case CNG = 'cng';
    case ELECTRIC = 'electric';
    case HYBRID = 'hybrid';
    case HYDROGEN = 'hydrogen';
    case ETHANOL = 'ethanol';
    case BIODIESEL = 'biodiesel';
}
