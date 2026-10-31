<?php

namespace App\Enums;

/**
 * What kind of place a number reaches. The dossier shows the mix so the
 * contestant can see when every number comes from the same type of site.
 */
enum PhoneKind: string
{
    case Corporate = 'corporate';
    case CustomerService = 'customer_service';
    case Store = 'store';
    case SmallFormatStore = 'small_format_store';
    case DistributionCentre = 'distribution_centre';
    case RepairService = 'repair_service';
    case RegionalOffice = 'regional_office';
    case Department = 'department';
    case Other = 'other';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $k) => $k->value, self::cases());
    }
}
