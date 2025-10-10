
import type { ValuationInputs, CalculationResults, PeriodResult } from '../types';

export const calculateValuation = (inputs: ValuationInputs): CalculationResults => {
  const {
    netRevenue,
    variableCosts,
    fixedCosts,
    growthRate,
    taxRate,
    discountRate,
    netWorkingCapital,
    capex,
    projectionPeriods,
    perpetuityGrowthRate,
  } = inputs;

  const g = growthRate / 100;
  const t = taxRate / 100;
  const w = discountRate / 100;
  const gp = perpetuityGrowthRate / 100;

  const periodResults: PeriodResult[] = [];
  let cumulativePV = 0;

  let lastNWC = netWorkingCapital;

  for (let i = 1; i <= projectionPeriods; i++) {
    const currentGrowthFactor = Math.pow(1 + g, i);
    const prevGrowthFactor = Math.pow(1 + g, i - 1);

    const projectedRevenue = netRevenue * currentGrowthFactor;
    const projectedVariableCosts = variableCosts * currentGrowthFactor;
    
    // Fixed costs remain constant
    const ebit = projectedRevenue - projectedVariableCosts - fixedCosts;
    const nopat = ebit * (1 - t);

    const projectedNWC = netWorkingCapital * currentGrowthFactor;
    const changeInNWC = projectedNWC - (netWorkingCapital * prevGrowthFactor);

    const projectedCapex = capex * currentGrowthFactor;

    const freeCashFlow = nopat - projectedCapex - changeInNWC;
    const presentValue = freeCashFlow / Math.pow(1 + w, i);

    periodResults.push({
      year: i,
      freeCashFlow,
      presentValue,
    });

    cumulativePV += presentValue;
  }

  const finalYearFCF = periodResults[periodResults.length - 1]?.freeCashFlow || 0;
  const terminalYearFCF = finalYearFCF * (1 + gp);
  
  const perpetuityValue = terminalYearFCF / (w - gp);
  const presentValuePerpetuity = perpetuityValue / Math.pow(1 + w, projectionPeriods);

  const totalValuation = cumulativePV + presentValuePerpetuity;

  return {
    periodResults,
    perpetuityValue,
    presentValuePerpetuity,
    totalValuation,
  };
};
