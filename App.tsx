import React, { useState, useCallback } from 'react';
import type { ValuationInputs, CalculationResults, PeriodResult } from './types';
import { calculateValuation } from './services/valuationService';

// --- UTILITY FUNCTIONS ---
const formatCurrency = (value: number | string): string => {
  const num = typeof value === 'string' ? parseFloat(value) : value;
  if (isNaN(num)) return '';
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(num);
};

const parseCurrency = (value: string): number => {
  const num = parseFloat(value.replace(/[^0-9,-]+/g, '').replace(',', '.'));
  return isNaN(num) ? 0 : num;
};

// --- INPUT FIELD COMPONENTS ---

interface InputFieldProps {
  label: string;
  name: keyof ValuationInputs;
  value: number;
  onChange: (name: keyof ValuationInputs, value: number) => void;
  type: 'currency' | 'percentage' | 'number';
  placeholder?: string;
  error?: string;
}

const InputField: React.FC<InputFieldProps> = ({ label, name, value, onChange, type, placeholder, error }) => {
  const [displayValue, setDisplayValue] = useState('');

  React.useEffect(() => {
    if (document.activeElement?.getAttribute('name') === name) return;

    if (type === 'currency' && value !== 0) {
      setDisplayValue(formatCurrency(value));
    } else if (type === 'percentage' && value !== 0) {
      setDisplayValue(`${value}%`);
    } else if (type === 'number' && value !== 0) {
      setDisplayValue(String(value));
    } else {
        setDisplayValue('');
    }
  }, [value, type, name]);
  
  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const rawValue = e.target.value;
    setDisplayValue(rawValue);
    let numericValue = 0;
    if (type === 'currency') {
      numericValue = parseCurrency(rawValue);
    } else if (type === 'percentage') {
      numericValue = parseFloat(rawValue.replace('%', ''));
    } else {
      numericValue = parseInt(rawValue, 10);
    }
    onChange(name, isNaN(numericValue) ? 0 : numericValue);
  };

  const handleBlur = () => {
    if (type === 'currency') {
      setDisplayValue(formatCurrency(value));
    } else if (type === 'percentage') {
        setDisplayValue(value > 0 ? `${value}%` : '');
    } else {
        setDisplayValue(value > 0 ? String(value) : '');
    }
  };


  return (
    <div className="flex flex-col">
      <label htmlFor={name} className="mb-1 text-sm font-medium text-gray-400">{label}</label>
      <input
        id={name}
        name={name}
        type="text"
        value={displayValue}
        onChange={handleInputChange}
        onBlur={handleBlur}
        placeholder={placeholder}
        className={`bg-gray-700/50 border rounded-md px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:ring-2 transition duration-200 ${
          error 
          ? 'border-red-500 focus:ring-red-500' 
          : 'border-gray-600 focus:ring-[#0057FF]'
        }`}
        aria-invalid={!!error}
        aria-describedby={error ? `${name}-error` : undefined}
      />
      {error && <p id={`${name}-error`} className="mt-1 text-xs text-red-400">{error}</p>}
    </div>
  );
};


// --- FORM COMPONENT ---

interface ValuationFormProps {
  inputs: ValuationInputs;
  onInputChange: (name: keyof ValuationInputs, value: number) => void;
  onSubmit: () => void;
  onClear: () => void;
  errors: Record<string, string>;
}

const ValuationForm: React.FC<ValuationFormProps> = ({ inputs, onInputChange, onSubmit, onClear, errors }) => (
  <div className="bg-gray-800/50 p-8 rounded-lg shadow-2xl border border-gray-700">
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
      <InputField label="Receita Líquida Anual (R$)" name="netRevenue" value={inputs.netRevenue} onChange={onInputChange} type="currency" placeholder="R$ 1.000.000,00" error={errors.netRevenue}/>
      <InputField label="Custos Variáveis Anuais (R$)" name="variableCosts" value={inputs.variableCosts} onChange={onInputChange} type="currency" placeholder="R$ 400.000,00" error={errors.variableCosts}/>
      <InputField label="Custos Fixos Anuais (R$)" name="fixedCosts" value={inputs.fixedCosts} onChange={onInputChange} type="currency" placeholder="R$ 200.000,00" error={errors.fixedCosts}/>
      <InputField label="Crescimento Esperado (%)" name="growthRate" value={inputs.growthRate} onChange={onInputChange} type="percentage" placeholder="5%" error={errors.growthRate}/>
      <InputField label="Alíquota de Imposto (%)" name="taxRate" value={inputs.taxRate} onChange={onInputChange} type="percentage" placeholder="25%" error={errors.taxRate}/>
      <InputField label="Taxa de Desconto (WACC %)" name="discountRate" value={inputs.discountRate} onChange={onInputChange} type="percentage" placeholder="10%" error={errors.discountRate}/>
      <InputField label="Capital de Giro Líquido (R$)" name="netWorkingCapital" value={inputs.netWorkingCapital} onChange={onInputChange} type="currency" placeholder="R$ 50.000,00" error={errors.netWorkingCapital}/>
      <InputField label="Investimento em CAPEX (R$)" name="capex" value={inputs.capex} onChange={onInputChange} type="currency" placeholder="R$ 80.000,00" error={errors.capex}/>
      <InputField label="Períodos para Projeção (anos)" name="projectionPeriods" value={inputs.projectionPeriods} onChange={onInputChange} type="number" placeholder="5" error={errors.projectionPeriods}/>
      <InputField label="Crescimento na Perpetuidade (%)" name="perpetuityGrowthRate" value={inputs.perpetuityGrowthRate} onChange={onInputChange} type="percentage" placeholder="2%" error={errors.perpetuityGrowthRate}/>
    </div>
    <div className="mt-8 flex items-center justify-end space-x-4">
      <button onClick={onClear} className="px-6 py-2 text-gray-300 border border-gray-600 hover:bg-gray-700 rounded-md transition duration-200">Limpar Campos</button>
      <button onClick={onSubmit} className="px-8 py-2 bg-[#0057FF] hover:bg-blue-600 text-white font-bold rounded-md shadow-lg transition duration-200 transform hover:scale-105">Calcular Valuation</button>
    </div>
  </div>
);


// --- RESULTS COMPONENTS ---

interface ResultsDisplayProps {
  results: CalculationResults;
}

const ResultCard: React.FC<{title: string; value: string; isTotal?: boolean}> = ({title, value, isTotal = false}) => (
    <div className={`bg-gray-800/60 p-4 rounded-lg shadow-lg flex flex-col justify-center items-center border border-gray-700 ${isTotal ? 'col-span-1 md:col-span-3 bg-[#0057FF]/20 border-[#0057FF]/50' : ''}`}>
        <h3 className="text-sm text-gray-400 mb-1">{title}</h3>
        <p className={`font-bold ${isTotal ? 'text-3xl text-[#60A5FA]' : 'text-xl text-white'}`}>{value}</p>
    </div>
);

const ResultsTable: React.FC<{ periods: PeriodResult[] }> = ({ periods }) => (
    <div className="mt-4 overflow-x-auto">
        <table className="w-full text-left">
            <thead className="bg-gray-700/50">
                <tr>
                    <th className="p-3">Ano</th>
                    <th className="p-3">Fluxo de Caixa Livre</th>
                    <th className="p-3">Valor Presente do FCL</th>
                </tr>
            </thead>
            <tbody>
                {periods.map(p => (
                    <tr key={p.year} className="border-b border-gray-700 hover:bg-gray-800/50">
                        <td className="p-3">{p.year}</td>
                        <td className="p-3">{formatCurrency(p.freeCashFlow)}</td>
                        <td className="p-3">{formatCurrency(p.presentValue)}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    </div>
);


const ResultsDisplay: React.FC<ResultsDisplayProps> = ({ results }) => (
    <div className="bg-gray-800/50 p-8 rounded-lg shadow-2xl mt-8 border border-gray-700">
        <h2 className="text-2xl font-bold mb-6 text-center text-white">Resultados do Valuation</h2>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <ResultCard title="Valor da Perpetuidade" value={formatCurrency(results.perpetuityValue)} />
            <ResultCard title="VP da Perpetuidade" value={formatCurrency(results.presentValuePerpetuity)} />
            <ResultCard title="Valuation Total" value={formatCurrency(results.totalValuation)} isTotal={true}/>
        </div>
        <h3 className="text-xl font-bold mb-4 mt-8">Projeção do Fluxo de Caixa</h3>
        <ResultsTable periods={results.periodResults} />
    </div>
);


// --- MAIN APP COMPONENT ---

const initialInputs: ValuationInputs = {
  netRevenue: 0,
  variableCosts: 0,
  fixedCosts: 0,
  growthRate: 0,
  taxRate: 0,
  discountRate: 0,
  netWorkingCapital: 0,
  capex: 0,
  projectionPeriods: 5,
  perpetuityGrowthRate: 0,
};

function App() {
  const [inputs, setInputs] = useState<ValuationInputs>(initialInputs);
  const [results, setResults] = useState<CalculationResults | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const handleInputChange = useCallback((name: keyof ValuationInputs, value: number) => {
    setInputs(prev => ({ ...prev, [name]: value }));
     // Clear error for the field being edited
    if (errors[name]) {
        setErrors(prevErrors => {
            const newErrors = { ...prevErrors };
            delete newErrors[name];
            return newErrors;
        });
    }
  }, [errors]);

  const validateInputs = (): boolean => {
    const newErrors: Record<string, string> = {};

    if (inputs.netRevenue <= 0) {
      newErrors.netRevenue = "Receita Líquida deve ser um valor positivo.";
    }
    if (inputs.variableCosts < 0) {
      newErrors.variableCosts = "Custos Variáveis não podem ser negativos.";
    }
    if (inputs.fixedCosts < 0) {
      newErrors.fixedCosts = "Custos Fixos não podem ser negativos.";
    }
    if (inputs.taxRate < 0 || inputs.taxRate > 100) {
      newErrors.taxRate = "Alíquota de Imposto deve ser entre 0% e 100%.";
    }
    if (inputs.discountRate <= 0) {
      newErrors.discountRate = "Taxa de Desconto (WACC) deve ser positiva.";
    }
     if (inputs.discountRate <= inputs.perpetuityGrowthRate) {
      newErrors.discountRate = "A Taxa de Desconto deve ser maior que o Crescimento na Perpetuidade.";
      newErrors.perpetuityGrowthRate = "O Crescimento na Perpetuidade deve ser menor que a Taxa de Desconto.";
    }
    if (inputs.capex < 0) {
      newErrors.capex = "Investimento em CAPEX não pode ser negativo.";
    }
    if (inputs.projectionPeriods <= 0 || !Number.isInteger(inputs.projectionPeriods)) {
      newErrors.projectionPeriods = "Períodos de Projeção deve ser um inteiro positivo.";
    }
    
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };
  
  const handleSubmit = () => {
    if (validateInputs()) {
      const calculatedResults = calculateValuation(inputs);
      setResults(calculatedResults);
    } else {
        setResults(null);
    }
  };

  const handleClear = () => {
    setInputs(initialInputs);
    setResults(null);
    setErrors({});
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-[#1E293B] to-[#0F172A] text-gray-200">
      <div className="container mx-auto p-4 sm:p-8">
        <header className="text-center mb-10">
            <h1 className="text-4xl font-bold text-white mb-2">Calculadora de Valuation</h1>
            <p className="text-lg text-gray-400">Estime o valor da sua empresa com o método FCD.</p>
        </header>

        <main>
          <ValuationForm 
            inputs={inputs} 
            onInputChange={handleInputChange} 
            onSubmit={handleSubmit}
            onClear={handleClear}
            errors={errors}
          />
          {results && (
            <div className="mt-8 animate-fade-in">
              <ResultsDisplay results={results} />
            </div>
          )}
        </main>

        <footer className="text-center mt-12 text-gray-500 text-sm">
          <p>&copy; {new Date().getFullYear()} Grupo Optimize. Todos os direitos reservados.</p>
          <p className="mt-1">Esta é uma ferramenta para fins de estimativa e não constitui aconselhamento financeiro.</p>
        </footer>
      </div>
    </div>
  );
}

export default App;
