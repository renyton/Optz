
export interface ValuationInputs {
  netRevenue: number;
  variableCosts: number;
  fixedCosts: number;
  growthRate: number;
  taxRate: number;
  discountRate: number;
  netWorkingCapital: number;
  capex: number;
  projectionPeriods: number;
  perpetuityGrowthRate: number;
}

export interface PeriodResult {
  year: number;
  freeCashFlow: number;
  presentValue: number;
}

export interface CalculationResults {
  periodResults: PeriodResult[];
  perpetuityValue: number;
  presentValuePerpetuity: number;
  totalValuation: number;
}
