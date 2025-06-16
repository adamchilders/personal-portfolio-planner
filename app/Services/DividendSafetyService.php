<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Portfolio;
use App\Models\Holding;
use App\Models\Stock;
use App\Models\Dividend;
use Exception;

class DividendSafetyService
{
    private FinancialModelingPrepService $fmpService;
    private StockDataService $stockDataService;
    
    public function __construct(
        FinancialModelingPrepService $fmpService,
        StockDataService $stockDataService
    ) {
        $this->fmpService = $fmpService;
        $this->stockDataService = $stockDataService;
    }
    
    /**
     * Calculate dividend safety score for a stock (0-100 scale)
     */
    public function calculateDividendSafetyScore(string $symbol): array
    {
        try {
            $financialData = $this->getFinancialData($symbol);
            $dividendData = $this->getDividendHistory($symbol);
            
            if (empty($financialData) || empty($dividendData)) {
                return [
                    'score' => 0,
                    'grade' => 'N/A',
                    'factors' => [],
                    'warnings' => ['Insufficient data for analysis'],
                    'last_updated' => date('Y-m-d H:i:s')
                ];
            }
            
            $factors = [];
            $totalScore = 0;
            $maxScore = 0;
            
            // Factor 1: Payout Ratio (25% weight)
            $payoutRatio = $this->calculatePayoutRatio($financialData, $dividendData);
            $payoutScore = $this->scorePayoutRatio($payoutRatio);
            $factors['payout_ratio'] = [
                'value' => $payoutRatio,
                'score' => $payoutScore,
                'weight' => 25,
                'description' => 'Percentage of earnings paid as dividends'
            ];
            $totalScore += $payoutScore * 0.25;
            $maxScore += 25;
            
            // Factor 2: Free Cash Flow Coverage (25% weight)
            $fcfCoverage = $this->calculateFCFCoverage($financialData, $dividendData);
            $fcfScore = $this->scoreFCFCoverage($fcfCoverage);
            $factors['fcf_coverage'] = [
                'value' => $fcfCoverage,
                'score' => $fcfScore,
                'weight' => 25,
                'description' => 'Free cash flow coverage of dividends'
            ];
            $totalScore += $fcfScore * 0.25;
            $maxScore += 25;
            
            // Factor 3: Debt-to-Equity Impact (20% weight)
            $debtRatio = $this->calculateDebtToEquity($financialData);
            $debtScore = $this->scoreDebtRatio($debtRatio);
            $factors['debt_ratio'] = [
                'value' => $debtRatio,
                'score' => $debtScore,
                'weight' => 20,
                'description' => 'Company leverage impact on dividend sustainability'
            ];
            $totalScore += $debtScore * 0.20;
            $maxScore += 20;
            
            // Factor 4: Dividend Growth Consistency (15% weight)
            $growthConsistency = $this->calculateDividendGrowthConsistency($dividendData);
            $growthScore = $this->scoreGrowthConsistency($growthConsistency);
            $factors['growth_consistency'] = [
                'value' => $growthConsistency,
                'score' => $growthScore,
                'weight' => 15,
                'description' => 'Historical dividend growth stability'
            ];
            $totalScore += $growthScore * 0.15;
            $maxScore += 15;
            
            // Factor 5: Earnings Stability (15% weight)
            $earningsStability = $this->calculateEarningsStability($financialData);
            $stabilityScore = $this->scoreEarningsStability($earningsStability);
            $factors['earnings_stability'] = [
                'value' => $earningsStability,
                'score' => $stabilityScore,
                'weight' => 15,
                'description' => 'Consistency of earnings over time'
            ];
            $totalScore += $stabilityScore * 0.15;
            $maxScore += 15;
            
            // Calculate final score (0-100)
            $finalScore = $maxScore > 0 ? (int)round(($totalScore / $maxScore) * 100) : 0;
            
            // Generate warnings
            $warnings = $this->generateWarnings($factors);
            
            return [
                'score' => $finalScore,
                'grade' => $this->getScoreGrade($finalScore),
                'factors' => $factors,
                'warnings' => $warnings,
                'last_updated' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("Error calculating dividend safety score for {$symbol}: " . $e->getMessage());
            return [
                'score' => 0,
                'grade' => 'Error',
                'factors' => [],
                'warnings' => ['Error calculating safety score: ' . $e->getMessage()],
                'last_updated' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * Get portfolio-wide dividend safety analysis
     */
    public function getPortfolioDividendSafety(Portfolio $portfolio): array
    {
        $holdings = $portfolio->holdings()->where('is_active', true)->get();
        $analysis = [
            'overall_score' => 0,
            'overall_grade' => 'N/A',
            'total_dividend_income' => 0,
            'safe_dividend_income' => 0,
            'at_risk_dividend_income' => 0,
            'holdings_analysis' => [],
            'risk_distribution' => [
                'safe' => 0,      // Score 80+
                'moderate' => 0,  // Score 60-79
                'risky' => 0,     // Score 40-59
                'dangerous' => 0  // Score <40
            ],
            'top_risks' => [],
            'recommendations' => []
        ];
        
        if ($holdings->isEmpty()) {
            return $analysis;
        }
        
        $totalValue = 0;
        $weightedScore = 0;
        
        foreach ($holdings as $holding) {
            $safetyData = $this->calculateDividendSafetyScore($holding->stock_symbol);
            $holdingValue = $holding->quantity * $holding->avg_cost_basis;
            $annualDividend = $this->estimateAnnualDividend($holding->stock_symbol, $holding->quantity);
            
            $analysis['holdings_analysis'][$holding->stock_symbol] = [
                'safety_score' => $safetyData['score'],
                'safety_grade' => $safetyData['grade'],
                'holding_value' => $holdingValue,
                'annual_dividend' => $annualDividend,
                'warnings' => $safetyData['warnings']
            ];
            
            // Weight by holding value
            $weightedScore += $safetyData['score'] * $holdingValue;
            $totalValue += $holdingValue;
            
            // Categorize dividend income by safety
            $analysis['total_dividend_income'] += $annualDividend;
            
            if ($safetyData['score'] >= 70) {
                $analysis['safe_dividend_income'] += $annualDividend;
                $analysis['risk_distribution']['safe']++;
            } elseif ($safetyData['score'] >= 50) {
                $analysis['risk_distribution']['moderate']++;
            } elseif ($safetyData['score'] >= 30) {
                $analysis['at_risk_dividend_income'] += $annualDividend;
                $analysis['risk_distribution']['risky']++;
            } else {
                $analysis['at_risk_dividend_income'] += $annualDividend;
                $analysis['risk_distribution']['dangerous']++;
            }
            
            // Track high-risk holdings
            if ($safetyData['score'] < 50) {
                $analysis['top_risks'][] = [
                    'symbol' => $holding->stock_symbol,
                    'score' => $safetyData['score'],
                    'annual_dividend' => $annualDividend,
                    'warnings' => $safetyData['warnings']
                ];
            }
        }
        
        // Calculate overall portfolio score
        $analysis['overall_score'] = $totalValue > 0 ? (int)round($weightedScore / $totalValue) : 0;
        $analysis['overall_grade'] = $this->getScoreGrade($analysis['overall_score']);
        
        // Sort top risks by dividend amount at risk
        usort($analysis['top_risks'], function($a, $b) {
            return $b['annual_dividend'] <=> $a['annual_dividend'];
        });
        
        // Generate recommendations
        $analysis['recommendations'] = $this->generatePortfolioRecommendations($analysis);
        
        return $analysis;
    }
    
    /**
     * Get financial data from FMP
     */
    private function getFinancialData(string $symbol): array
    {
        try {
            return $this->fmpService->fetchFinancialStatements($symbol, 5);
        } catch (Exception $e) {
            error_log("Error fetching financial data for {$symbol}: " . $e->getMessage());
            return [
                'income_statements' => [],
                'balance_sheets' => [],
                'cash_flow_statements' => [],
                'key_metrics' => []
            ];
        }
    }
    
    /**
     * Get dividend history for analysis
     */
    private function getDividendHistory(string $symbol): array
    {
        return Dividend::where('symbol', $symbol)
            ->orderBy('ex_date', 'desc')
            ->limit(20) // Last 5 years of quarterly dividends
            ->get()
            ->toArray();
    }
    
    /**
     * Calculate payout ratio
     */
    private function calculatePayoutRatio(array $financialData, array $dividendData): float
    {
        if (empty($financialData['income_statements']) || empty($dividendData)) {
            return 0.0;
        }

        // Get most recent year's earnings per share
        $latestIncome = $financialData['income_statements'][0] ?? null;
        if (!$latestIncome || !isset($latestIncome['eps'])) {
            return 0.0;
        }

        $eps = (float)$latestIncome['eps'];
        if ($eps <= 0) {
            return 999.0; // Indicate very high risk if no earnings
        }

        // Calculate annual dividend per share from recent dividends
        $annualDividendPerShare = $this->calculateAnnualDividendPerShare($dividendData);

        return $annualDividendPerShare > 0 ? $annualDividendPerShare / $eps : 0.0;
    }
    
    /**
     * Score payout ratio (0-100)
     */
    private function scorePayoutRatio(float $ratio): int
    {
        if ($ratio <= 0.4) return 100;      // 40% or less - excellent
        if ($ratio <= 0.6) return 80;       // 40-60% - good
        if ($ratio <= 0.8) return 60;       // 60-80% - moderate
        if ($ratio <= 1.0) return 40;       // 80-100% - risky
        return 20;                          // >100% - dangerous
    }
    
    // Additional scoring methods will be implemented...
    
    /**
     * Convert score to letter grade
     */
    private function getScoreGrade(int $score): string
    {
        if ($score >= 90) return 'A+';
        if ($score >= 80) return 'A';
        if ($score >= 70) return 'B';
        if ($score >= 60) return 'C';
        if ($score >= 50) return 'D';
        return 'F';
    }
    
    /**
     * Generate warnings based on factors
     */
    private function generateWarnings(array $factors): array
    {
        $warnings = [];
        
        // Add specific warnings based on factor scores
        foreach ($factors as $key => $factor) {
            if ($factor['score'] < 40) {
                $warnings[] = "Poor {$factor['description']} - {$key} score: {$factor['score']}";
            }
        }
        
        return $warnings;
    }
    
    /**
     * Calculate annual dividend per share from recent dividend data
     */
    private function calculateAnnualDividendPerShare(array $dividendData): float
    {
        if (empty($dividendData)) {
            return 0.0;
        }

        // Get dividends from the last 12 months
        $oneYearAgo = date('Y-m-d', strtotime('-1 year'));
        $recentDividends = array_filter($dividendData, function($dividend) use ($oneYearAgo) {
            return isset($dividend['ex_date']) && $dividend['ex_date'] >= $oneYearAgo;
        });

        return array_sum(array_column($recentDividends, 'amount'));
    }

    /**
     * Calculate free cash flow coverage
     */
    private function calculateFCFCoverage(array $financialData, array $dividendData): float
    {
        if (empty($financialData['cash_flow_statements']) || empty($dividendData)) {
            return 0.0;
        }

        $latestCashFlow = $financialData['cash_flow_statements'][0] ?? null;
        if (!$latestCashFlow || !isset($latestCashFlow['freeCashFlow'])) {
            return 0.0;
        }

        $freeCashFlow = (float)$latestCashFlow['freeCashFlow'];
        $totalDividendsPaid = abs((float)($latestCashFlow['dividendsPaid'] ?? 0));

        return $totalDividendsPaid > 0 ? $freeCashFlow / $totalDividendsPaid : 0.0;
    }

    /**
     * Score FCF coverage
     */
    private function scoreFCFCoverage(float $coverage): int
    {
        if ($coverage >= 2.0) return 100;    // 2x+ coverage - excellent
        if ($coverage >= 1.5) return 80;     // 1.5x coverage - good
        if ($coverage >= 1.2) return 60;     // 1.2x coverage - moderate
        if ($coverage >= 1.0) return 40;     // 1x coverage - risky
        return 20;                           // <1x coverage - dangerous
    }

    /**
     * Calculate debt-to-equity ratio
     */
    private function calculateDebtToEquity(array $financialData): float
    {
        if (empty($financialData['balance_sheets'])) {
            return 0.0;
        }

        $latestBalance = $financialData['balance_sheets'][0] ?? null;
        if (!$latestBalance) {
            return 0.0;
        }

        $totalDebt = (float)($latestBalance['totalDebt'] ?? 0);
        $totalEquity = (float)($latestBalance['totalStockholdersEquity'] ?? 0);

        return $totalEquity > 0 ? $totalDebt / $totalEquity : 999.0;
    }

    /**
     * Score debt ratio
     */
    private function scoreDebtRatio(float $ratio): int
    {
        if ($ratio <= 0.3) return 100;       // 30% or less - excellent
        if ($ratio <= 0.5) return 80;        // 30-50% - good
        if ($ratio <= 0.7) return 60;        // 50-70% - moderate
        if ($ratio <= 1.0) return 40;        // 70-100% - risky
        return 20;                           // >100% - dangerous
    }

    /**
     * Calculate dividend growth consistency
     */
    private function calculateDividendGrowthConsistency(array $dividendData): float
    {
        if (count($dividendData) < 8) { // Need at least 2 years of quarterly data
            return 0.0;
        }

        // Calculate year-over-year growth rates
        $growthRates = [];
        $annualDividends = $this->getAnnualDividends($dividendData);

        for ($i = 1; $i < count($annualDividends); $i++) {
            $current = $annualDividends[$i-1]['total'];
            $previous = $annualDividends[$i]['total'];

            if ($previous > 0) {
                $growthRates[] = ($current - $previous) / $previous;
            }
        }

        if (empty($growthRates)) {
            return 0.0;
        }

        // Calculate consistency (lower standard deviation = higher consistency)
        $mean = array_sum($growthRates) / count($growthRates);
        $variance = array_sum(array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $growthRates)) / count($growthRates);

        $stdDev = sqrt($variance);

        // Convert to 0-1 scale (lower std dev = higher consistency)
        return max(0, 1 - ($stdDev * 2)); // Scale factor of 2
    }

    /**
     * Score growth consistency
     */
    private function scoreGrowthConsistency(float $consistency): int
    {
        return (int)round($consistency * 100);
    }

    /**
     * Calculate earnings stability
     */
    private function calculateEarningsStability(array $financialData): float
    {
        if (empty($financialData['income_statements']) || count($financialData['income_statements']) < 3) {
            return 0.0;
        }

        $earnings = array_map(function($statement) {
            return (float)($statement['netIncome'] ?? 0);
        }, $financialData['income_statements']);

        // Remove any years with negative earnings for stability calculation
        $positiveEarnings = array_filter($earnings, function($e) { return $e > 0; });

        if (count($positiveEarnings) < 2) {
            return 0.0;
        }

        $mean = array_sum($positiveEarnings) / count($positiveEarnings);
        $variance = array_sum(array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $positiveEarnings)) / count($positiveEarnings);

        $coefficientOfVariation = $mean > 0 ? sqrt($variance) / $mean : 999;

        // Convert to 0-1 scale (lower CV = higher stability)
        return max(0, 1 - ($coefficientOfVariation / 2));
    }

    /**
     * Score earnings stability
     */
    private function scoreEarningsStability(float $stability): int
    {
        return (int)round($stability * 100);
    }

    /**
     * Estimate annual dividend for a holding
     */
    private function estimateAnnualDividend(string $symbol, float $shares): float
    {
        $dividendData = $this->getDividendHistory($symbol);
        $annualDividendPerShare = $this->calculateAnnualDividendPerShare($dividendData);

        return $annualDividendPerShare * $shares;
    }

    /**
     * Generate portfolio recommendations
     */
    private function generatePortfolioRecommendations(array $analysis): array
    {
        $recommendations = [];

        if ($analysis['overall_score'] < 60) {
            $recommendations[] = 'Consider reducing exposure to high-risk dividend stocks';
        }

        if ($analysis['at_risk_dividend_income'] > $analysis['total_dividend_income'] * 0.3) {
            $recommendations[] = 'More than 30% of dividend income is at risk - consider diversification';
        }

        if (count($analysis['top_risks']) > 0) {
            $recommendations[] = 'Review top risk holdings: ' .
                implode(', ', array_column(array_slice($analysis['top_risks'], 0, 3), 'symbol'));
        }

        return $recommendations;
    }

    /**
     * Group dividends by year
     */
    private function getAnnualDividends(array $dividendData): array
    {
        $annual = [];

        foreach ($dividendData as $dividend) {
            $year = date('Y', strtotime($dividend['ex_date'] ?? '1970-01-01'));
            if (!isset($annual[$year])) {
                $annual[$year] = ['year' => $year, 'total' => 0];
            }
            $annual[$year]['total'] += (float)($dividend['amount'] ?? 0);
        }

        // Sort by year descending
        uasort($annual, function($a, $b) {
            return $b['year'] <=> $a['year'];
        });

        return array_values($annual);
    }
}
