<?php

namespace App\Enums;

enum InvestmentSignalType: string
{
    case CostRecovered = 'cost_recovered';
    case NearBreakEven = 'near_break_even';
    case LowUtilization = 'low_utilization';
    case HighMaintenanceBurden = 'high_maintenance_burden';
    case RetirementReview = 'retirement_review';
    case HighUtilization = 'high_utilization';
    case HighSubhireDependency = 'high_subhire_dependency';
    case RepeatedShortage = 'repeated_shortage';
    case ExpansionReview = 'expansion_review';
    case ExcessCapacity = 'excess_capacity';

    public function label(): string
    {
        return match ($this) {
            self::CostRecovered => 'Cost Recovered',
            self::NearBreakEven => 'Near Break-even',
            self::LowUtilization => 'Low Utilization',
            self::HighMaintenanceBurden => 'High Maintenance Burden',
            self::RetirementReview => 'Retirement Review',
            self::HighUtilization => 'High Utilization',
            self::HighSubhireDependency => 'High Sub-hire Dependency',
            self::RepeatedShortage => 'Repeated Shortage',
            self::ExpansionReview => 'Expansion Review',
            self::ExcessCapacity => 'Excess Capacity',
        };
    }
}
