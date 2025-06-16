<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Portfolio;
use App\Models\Holding;
use App\Models\Stock;
use App\Models\Dividend;
use App\Models\DividendSafetyCache;
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
     * Uses cached data if available and fresh (< 24 hours old)
     */
    public function calculateDividendSafetyScore(string $symbol): array
    {
        $symbol = strtoupper($symbol);

        // Check cache first
        $cached = DividendSafetyCache::findBySymbol($symbol);
        if ($cached && $cached->isFresh()) {
            return $cached->toSafetyData();
        }

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

            // Determine if we used real data
            $usedRealFinancialData = !$this->isUsingMockFinancialData($financialData);
            $usedRealDividendData = !$this->isUsingMockDividendData($dividendData);

            $safetyData = [
                'score' => $finalScore,
                'grade' => $this->getScoreGrade($finalScore),
                'factors' => $factors,
                'warnings' => $warnings,
                'last_updated' => date('Y-m-d H:i:s'),
                'data_source' => $usedRealFinancialData ? 'FMP API' : 'Demo Data',
                'is_real_data' => $usedRealFinancialData,
                'financial_data_source' => $usedRealFinancialData ? 'FMP API' : 'Mock Data',
                'dividend_data_source' => $usedRealDividendData ? 'Database' : 'Mock Data',
                'fmp_available' => $this->fmpService->isAvailable()
            ];

            // Save to cache
            DividendSafetyCache::updateSafetyData($symbol, $safetyData);

            return $safetyData;
            
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
     * Bulk update dividend safety data for multiple symbols
     * Only updates symbols that need refreshing (stale or missing data)
     */
    public function bulkUpdateSafetyData(array $symbols): array
    {
        $symbols = array_map('strtoupper', $symbols);
        $symbolsNeedingUpdate = DividendSafetyCache::getSymbolsNeedingUpdate($symbols);

        $results = [];
        foreach ($symbolsNeedingUpdate as $symbol) {
            try {
                // Force fresh calculation (bypass cache check)
                $safetyData = $this->calculateFreshSafetyScore($symbol);
                $results[$symbol] = $safetyData;
            } catch (Exception $e) {
                error_log("Error updating safety data for {$symbol}: " . $e->getMessage());
                $results[$symbol] = [
                    'score' => 0,
                    'grade' => 'Error',
                    'factors' => [],
                    'warnings' => ['Error updating safety data: ' . $e->getMessage()],
                    'last_updated' => date('Y-m-d H:i:s')
                ];
            }
        }

        return $results;
    }

    /**
     * Calculate fresh safety score (bypasses cache)
     */
    private function calculateFreshSafetyScore(string $symbol): array
    {
        $symbol = strtoupper($symbol);

        $financialData = $this->getFinancialData($symbol);
        $dividendData = $this->getDividendHistory($symbol);

        if (empty($financialData) || empty($dividendData)) {
            $safetyData = [
                'score' => 0,
                'grade' => 'N/A',
                'factors' => [],
                'warnings' => ['Insufficient data for analysis'],
                'last_updated' => date('Y-m-d H:i:s')
            ];

            // Save to cache even if no data
            DividendSafetyCache::updateSafetyData($symbol, $safetyData);
            return $safetyData;
        }

        // Same calculation logic as calculateDividendSafetyScore but without cache check
        $factors = [];
        $totalScore = 0;
        $maxScore = 0;

        // Factor calculations (same as main method)
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

        $finalScore = $maxScore > 0 ? (int)round(($totalScore / $maxScore) * 100) : 0;
        $warnings = $this->generateWarnings($factors);

        $safetyData = [
            'score' => $finalScore,
            'grade' => $this->getScoreGrade($finalScore),
            'factors' => $factors,
            'warnings' => $warnings,
            'last_updated' => date('Y-m-d H:i:s')
        ];

        // Save to cache
        DividendSafetyCache::updateSafetyData($symbol, $safetyData);

        return $safetyData;
    }

    /**
     * Get portfolio-wide dividend safety analysis
     */
    public function getPortfolioDividendSafety(Portfolio $portfolio): array
    {
        try {
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
                $analysis['recommendations'] = ['Add dividend-paying stocks to your portfolio to begin safety analysis'];
                return $analysis;
            }
        } catch (Exception $e) {
            error_log("Error in getPortfolioDividendSafety: " . $e->getMessage());
            // Return empty analysis with error message
            return [
                'overall_score' => 0,
                'overall_grade' => 'N/A',
                'total_dividend_income' => 0,
                'safe_dividend_income' => 0,
                'at_risk_dividend_income' => 0,
                'holdings_analysis' => [],
                'risk_distribution' => ['safe' => 0, 'moderate' => 0, 'risky' => 0, 'dangerous' => 0],
                'top_risks' => [],
                'recommendations' => ['Error analyzing portfolio: ' . $e->getMessage()]
            ];
        }
        
        $totalValue = 0;
        $weightedScore = 0;

        // Get all symbols in portfolio
        $symbols = $holdings->pluck('stock_symbol')->unique()->toArray();

        // Bulk update any stale safety data
        $this->bulkUpdateSafetyData($symbols);

        foreach ($holdings as $holding) {
            try {
                $safetyData = $this->calculateDividendSafetyScore($holding->stock_symbol);
                $holdingValue = (float)$holding->quantity * (float)$holding->avg_cost_basis;
                $annualDividend = $this->estimateAnnualDividend($holding->stock_symbol, (float)$holding->quantity);
            
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
            } catch (Exception $e) {
                error_log("Error analyzing holding {$holding->stock_symbol}: " . $e->getMessage());
                // Add a placeholder entry for failed analysis
                $analysis['holdings_analysis'][$holding->stock_symbol] = [
                    'safety_score' => 0,
                    'safety_grade' => 'N/A',
                    'holding_value' => 0,
                    'annual_dividend' => 0,
                    'warnings' => ['Analysis failed: ' . $e->getMessage()]
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
     * Get financial data from FMP (with fallback to mock data)
     */
    private function getFinancialData(string $symbol): array
    {
        // Check if FMP is available first
        if (!$this->fmpService->isAvailable()) {
            error_log("FMP API not available for {$symbol} - using mock data");
            return $this->getMockFinancialData($symbol);
        }

        try {
            // Try to fetch from FMP first
            error_log("Attempting to fetch financial data for {$symbol} from FMP API");
            $data = $this->fmpService->fetchFinancialStatements($symbol, 5);

            // Log what we got back
            error_log("FMP API response for {$symbol}: " . json_encode([
                'income_statements_count' => count($data['income_statements'] ?? []),
                'balance_sheets_count' => count($data['balance_sheets'] ?? []),
                'cash_flow_statements_count' => count($data['cash_flow_statements'] ?? []),
                'has_data' => !empty($data['income_statements']) || !empty($data['balance_sheets']) || !empty($data['cash_flow_statements'])
            ]));

            // If we get empty data, use mock data for demonstration
            if (empty($data['income_statements']) && empty($data['balance_sheets']) && empty($data['cash_flow_statements'])) {
                error_log("FMP API returned empty financial data for {$symbol} - falling back to mock data");
                return $this->getMockFinancialData($symbol);
            }

            error_log("Successfully fetched real financial data for {$symbol} from FMP API");
            return $data;
        } catch (Exception $e) {
            error_log("Error fetching financial data for {$symbol}: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            // Return mock data for demonstration purposes
            return $this->getMockFinancialData($symbol);
        }
    }

    /**
     * Get mock financial data for demonstration
     */
    private function getMockFinancialData(string $symbol): array
    {
        // Mock data based on typical large-cap dividend stocks
        $mockData = [
            'AAPL' => [
                'eps' => 6.05,
                'freeCashFlow' => 99584000000,
                'totalDebt' => 123930000000,
                'totalStockholdersEquity' => 59278000000,
                'netIncome' => 94321000000,
                'dividendsPaid' => -14467000000
            ],
            'MSFT' => [
                'eps' => 9.65,
                'freeCashFlow' => 65149000000,
                'totalDebt' => 47032000000,
                'totalStockholdersEquity' => 206223000000,
                'netIncome' => 72361000000,
                'dividendsPaid' => -18135000000
            ],
            'JNJ' => [
                'eps' => 6.04,
                'freeCashFlow' => 18910000000,
                'totalDebt' => 31895000000,
                'totalStockholdersEquity' => 71892000000,
                'netIncome' => 15895000000,
                'dividendsPaid' => -11156000000
            ]
        ];

        $data = $mockData[$symbol] ?? $mockData['AAPL']; // Default to AAPL if symbol not found

        return [
            'income_statements' => [
                [
                    'eps' => $data['eps'],
                    'netIncome' => $data['netIncome']
                ],
                [
                    'eps' => $data['eps'] * 0.95,
                    'netIncome' => $data['netIncome'] * 0.95
                ],
                [
                    'eps' => $data['eps'] * 0.90,
                    'netIncome' => $data['netIncome'] * 0.90
                ]
            ],
            'balance_sheets' => [
                [
                    'totalDebt' => $data['totalDebt'],
                    'totalStockholdersEquity' => $data['totalStockholdersEquity']
                ]
            ],
            'cash_flow_statements' => [
                [
                    'freeCashFlow' => $data['freeCashFlow'],
                    'dividendsPaid' => $data['dividendsPaid']
                ]
            ],
            'key_metrics' => []
        ];
    }
    
    /**
     * Get dividend history for analysis (with fallback to mock data)
     */
    private function getDividendHistory(string $symbol): array
    {
        $dividends = Dividend::where('symbol', $symbol)
            ->orderBy('ex_date', 'desc')
            ->limit(20) // Last 5 years of quarterly dividends
            ->get()
            ->toArray();

        error_log("Dividend history for {$symbol}: found " . count($dividends) . " dividend records in database");

        // If no dividend data found, use mock data for demonstration
        if (empty($dividends)) {
            error_log("No dividend data found for {$symbol} in database - using mock data");
            return $this->getMockDividendHistory($symbol);
        }

        error_log("Using real dividend data for {$symbol} from database");
        return $dividends;
    }

    /**
     * Get mock dividend history for demonstration
     */
    private function getMockDividendHistory(string $symbol): array
    {
        $mockDividends = [
            'AAPL' => 0.24,  // Quarterly dividend
            'MSFT' => 0.68,  // Quarterly dividend
            'JNJ' => 1.13    // Quarterly dividend
        ];

        $quarterlyAmount = $mockDividends[$symbol] ?? 0.24;
        $dividends = [];

        // Generate 8 quarters of mock dividend data
        for ($i = 0; $i < 8; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} months", strtotime('2024-12-15')));
            $dividends[] = [
                'symbol' => $symbol,
                'ex_date' => $date,
                'amount' => $quarterlyAmount,
                'payment_date' => date('Y-m-d', strtotime('+30 days', strtotime($date)))
            ];
        }

        return $dividends;
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

    /**
     * Clean up old cached safety data
     */
    public function cleanupOldCache(): int
    {
        return DividendSafetyCache::cleanupOldEntries();
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        $total = DividendSafetyCache::count();
        $fresh = DividendSafetyCache::where('last_updated', '>=', date('Y-m-d H:i:s', strtotime('-24 hours')))->count();
        $stale = $total - $fresh;

        return [
            'total_cached' => $total,
            'fresh_entries' => $fresh,
            'stale_entries' => $stale,
            'cache_hit_rate' => $total > 0 ? round(($fresh / $total) * 100, 1) : 0
        ];
    }

    /**
     * Get diagnostic information about data sources
     */
    public function getDiagnosticInfo(): array
    {
        $fmpAvailable = $this->fmpService->isAvailable();
        $fmpStats = $this->fmpService->getUsageStats();

        return [
            'fmp_api' => [
                'available' => $fmpAvailable,
                'stats' => $fmpStats
            ],
            'data_sources' => [
                'financial_data' => $fmpAvailable ? 'FMP API' : 'Mock Data (Demo Mode)',
                'dividend_data' => 'Database + FMP API fallback'
            ],
            'cache_stats' => $this->getCacheStats(),
            'recommendations' => $this->getDataSourceRecommendations($fmpAvailable)
        ];
    }

    /**
     * Get recommendations for improving data sources
     */
    private function getDataSourceRecommendations(bool $fmpAvailable): array
    {
        $recommendations = [];

        if (!$fmpAvailable) {
            $recommendations[] = 'Configure Financial Modeling Prep API key for real financial data';
            $recommendations[] = 'Visit Admin panel to add FMP API key';
            $recommendations[] = 'Currently using demonstration data for safety analysis';
        }

        $cacheStats = $this->getCacheStats();
        if ($cacheStats['total_cached'] === 0) {
            $recommendations[] = 'No cached safety data - first analysis will be slower';
        } elseif ($cacheStats['stale_entries'] > 0) {
            $recommendations[] = "Update {$cacheStats['stale_entries']} stale cache entries for better performance";
        }

        return $recommendations;
    }

    /**
     * Check if financial data is mock data
     */
    private function isUsingMockFinancialData(array $financialData): bool
    {
        // Check if this looks like our mock data structure
        if (empty($financialData['income_statements'])) {
            return true;
        }

        $firstStatement = $financialData['income_statements'][0] ?? [];

        // Check for specific mock data values we use
        $mockEpsValues = [6.05, 9.65, 6.04]; // AAPL, MSFT, JNJ mock EPS
        $eps = $firstStatement['eps'] ?? 0;

        return in_array($eps, $mockEpsValues);
    }

    /**
     * Check if dividend data is mock data
     */
    private function isUsingMockDividendData(array $dividendData): bool
    {
        if (empty($dividendData)) {
            return true;
        }

        // Check if all dividends have the same amount (characteristic of mock data)
        $amounts = array_column($dividendData, 'amount');
        $uniqueAmounts = array_unique($amounts);

        // Mock data typically has the same amount for all quarters
        // Real data would have varying amounts
        return count($uniqueAmounts) <= 1;
    }

    /**
     * Get mock portfolio analysis for demonstration
     */
    private function getMockPortfolioAnalysis(): array
    {
        return [
            'overall_score' => 75,
            'overall_grade' => 'B',
            'total_dividend_income' => 2450.00,
            'safe_dividend_income' => 1680.00,
            'at_risk_dividend_income' => 770.00,
            'holdings_analysis' => [
                'AAPL' => [
                    'safety_score' => 68,
                    'safety_grade' => 'C',
                    'holding_value' => 15000.00,
                    'annual_dividend' => 360.00,
                    'warnings' => ['High debt ratio may impact dividend sustainability']
                ],
                'MSFT' => [
                    'safety_score' => 83,
                    'safety_grade' => 'A',
                    'holding_value' => 12000.00,
                    'annual_dividend' => 816.00,
                    'warnings' => []
                ],
                'JNJ' => [
                    'safety_score' => 89,
                    'safety_grade' => 'A',
                    'holding_value' => 10000.00,
                    'annual_dividend' => 904.00,
                    'warnings' => []
                ],
                'T' => [
                    'safety_score' => 45,
                    'safety_grade' => 'D',
                    'holding_value' => 8000.00,
                    'annual_dividend' => 370.00,
                    'warnings' => ['High payout ratio', 'Declining earnings stability']
                ]
            ],
            'risk_distribution' => [
                'safe' => 2,      // MSFT, JNJ
                'moderate' => 1,  // AAPL
                'risky' => 0,
                'dangerous' => 1  // T
            ],
            'top_risks' => [
                [
                    'symbol' => 'T',
                    'score' => 45,
                    'annual_dividend' => 370.00,
                    'warnings' => ['High payout ratio', 'Declining earnings stability']
                ]
            ],
            'recommendations' => [
                'Consider reducing exposure to AT&T (T) due to low safety score',
                'Portfolio has good diversification with 68% of dividend income from safe sources',
                'Consider adding more dividend aristocrats to improve overall safety'
            ]
        ];
    }
}
