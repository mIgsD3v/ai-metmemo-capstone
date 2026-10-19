<?php

namespace App\Enums;

enum ContactSource: string
{
    case Manual = 'manual';
    case LeadForm = 'lead_form';
    case Scan = 'scan';
}
