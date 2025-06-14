<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ApiKey;
use App\Models\DataProviderConfig;
use App\Models\User;
use App\Services\FinancialModelingPrepService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ApiKeyController extends BaseController
{
    /**
     * List all API keys
     */
    public function index(Request $request, Response $response): Response
    {
        try {
            $apiKeys = ApiKey::orderBy('provider')->get();
            
            // Get usage stats for each provider
            $fmpService = new FinancialModelingPrepService();
            $usageStats = [
                'financial_modeling_prep' => $fmpService->getUsageStats()
            ];
            
            return $this->jsonResponse($response, [
                'success' => true,
                'api_keys' => $apiKeys->map(function ($key) {
                    return [
                        'id' => $key->id,
                        'provider' => $key->provider,
                        'is_active' => $key->is_active,
                        'has_key' => !empty($key->api_key),
                        'rate_limit_per_minute' => $key->rate_limit_per_minute,
                        'rate_limit_per_day' => $key->rate_limit_per_day,
                        'usage_count_today' => $key->usage_count_today,
                        'usage_percentage' => $key->getUsagePercentage(),
                        'remaining_requests' => $key->getRemainingDailyRequests(),
                        'last_used' => $key->last_used?->toISOString(),
                        'notes' => $key->notes,
                        'created_at' => $key->created_at->toISOString(),
                        'updated_at' => $key->updated_at->toISOString()
                    ];
                }),
                'usage_stats' => $usageStats
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Failed to fetch API keys: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get specific API key
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $apiKey = ApiKey::findOrFail($args['id']);
            
            return $this->jsonResponse($response, [
                'success' => true,
                'api_key' => [
                    'id' => $apiKey->id,
                    'provider' => $apiKey->provider,
                    'api_key' => $apiKey->api_key, // Only show in admin interface
                    'is_active' => $apiKey->is_active,
                    'rate_limit_per_minute' => $apiKey->rate_limit_per_minute,
                    'rate_limit_per_day' => $apiKey->rate_limit_per_day,
                    'usage_count_today' => $apiKey->usage_count_today,
                    'usage_percentage' => $apiKey->getUsagePercentage(),
                    'remaining_requests' => $apiKey->getRemainingDailyRequests(),
                    'last_used' => $apiKey->last_used?->toISOString(),
                    'notes' => $apiKey->notes,
                    'created_at' => $apiKey->created_at->toISOString(),
                    'updated_at' => $apiKey->updated_at->toISOString()
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'API key not found'
            ], 404);
        }
    }
    
    /**
     * Update API key
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $apiKey = ApiKey::findOrFail($args['id']);
            $data = $request->getParsedBody();
            
            // Validate input
            $allowedFields = ['api_key', 'is_active', 'rate_limit_per_minute', 'rate_limit_per_day', 'notes'];
            $updateData = [];
            
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }
            
            // Validate API key if provided
            if (isset($updateData['api_key']) && !empty($updateData['api_key'])) {
                if ($apiKey->provider === 'financial_modeling_prep') {
                    // Test the FMP API key
                    $testResult = $this->testFmpApiKey($updateData['api_key']);
                    if (!$testResult['valid']) {
                        return $this->jsonResponse($response, [
                            'success' => false,
                            'message' => 'Invalid API key: ' . $testResult['error']
                        ], 400);
                    }
                }
            }
            
            $apiKey->update($updateData);
            
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'API key updated successfully',
                'api_key' => [
                    'id' => $apiKey->id,
                    'provider' => $apiKey->provider,
                    'is_active' => $apiKey->is_active,
                    'has_key' => !empty($apiKey->api_key),
                    'rate_limit_per_minute' => $apiKey->rate_limit_per_minute,
                    'rate_limit_per_day' => $apiKey->rate_limit_per_day,
                    'notes' => $apiKey->notes
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Failed to update API key: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Test API key connectivity
     */
    public function test(Request $request, Response $response, array $args): Response
    {
        try {
            $apiKey = ApiKey::findOrFail($args['id']);
            
            if ($apiKey->provider === 'financial_modeling_prep') {
                $testResult = $this->testFmpApiKey($apiKey->api_key);
                
                return $this->jsonResponse($response, [
                    'success' => $testResult['valid'],
                    'message' => $testResult['valid'] ? 'API key is working correctly' : 'API key test failed',
                    'error' => $testResult['valid'] ? null : $testResult['error'],
                    'test_data' => $testResult['data'] ?? null
                ]);
            }
            
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Testing not supported for this provider'
            ], 400);
            
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Failed to test API key: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get data provider configurations
     */
    public function getDataProviderConfig(Request $request, Response $response): Response
    {
        try {
            $configs = DataProviderConfig::orderBy('data_type')->get();
            $availableProviders = DataProviderConfig::getAvailableProviders();
            $dataTypes = DataProviderConfig::getAvailableDataTypes();

            return $this->jsonResponse($response, [
                'success' => true,
                'configurations' => $configs->map(function ($config) {
                    return [
                        'id' => $config->id,
                        'data_type' => $config->data_type,
                        'data_type_label' => DataProviderConfig::getAvailableDataTypes()[$config->data_type] ?? $config->data_type,
                        'primary_provider' => $config->primary_provider,
                        'fallback_provider' => $config->fallback_provider,
                        'is_active' => $config->is_active,
                        'config_options' => $config->config_options,
                        'notes' => $config->notes,
                        'created_at' => $config->created_at->toISOString(),
                        'updated_at' => $config->updated_at->toISOString()
                    ];
                }),
                'available_providers' => $availableProviders,
                'data_types' => $dataTypes
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Failed to fetch data provider configurations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update data provider configuration
     */
    public function updateDataProviderConfig(Request $request, Response $response, array $args): Response
    {
        try {
            $config = DataProviderConfig::findOrFail($args['id']);
            $data = $request->getParsedBody();

            // Validate input
            $allowedFields = ['primary_provider', 'fallback_provider', 'is_active', 'config_options', 'notes'];
            $updateData = [];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }

            $config->update($updateData);

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Data provider configuration updated successfully',
                'configuration' => [
                    'id' => $config->id,
                    'data_type' => $config->data_type,
                    'data_type_label' => DataProviderConfig::getAvailableDataTypes()[$config->data_type] ?? $config->data_type,
                    'primary_provider' => $config->primary_provider,
                    'fallback_provider' => $config->fallback_provider,
                    'is_active' => $config->is_active,
                    'notes' => $config->notes
                ]
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Failed to update data provider configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all users (admin only)
     */
    public function getUsers(Request $request, Response $response): Response
    {
        try {
            $users = User::orderBy('created_at', 'desc')->get();

            return $this->jsonResponse($response, [
                'success' => true,
                'users' => $users->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'username' => $user->username,
                        'email' => $user->email,
                        'role' => $user->role,
                        'is_active' => $user->is_active,
                        'email_verified_at' => $user->email_verified_at?->toISOString(),
                        'last_login' => $user->last_login?->toISOString(),
                        'created_at' => $user->created_at->toISOString()
                    ];
                })
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Failed to fetch users: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user role
     */
    public function updateUserRole(Request $request, Response $response, array $args): Response
    {
        try {
            $user = User::findOrFail($args['id']);
            $data = $request->getParsedBody();

            if (!isset($data['role']) || !in_array($data['role'], ['admin', 'user'])) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Invalid role. Must be "admin" or "user".'
                ], 400);
            }

            $user->role = $data['role'];
            $user->save();

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => "User {$user->username} role updated to {$data['role']}",
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role
                ]
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Failed to update user role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test Financial Modeling Prep API key
     */
    private function testFmpApiKey(string $apiKey): array
    {
        try {
            // Test with a simple API call
            $url = "https://financialmodelingprep.com/api/v3/profile/AAPL?apikey=" . urlencode($apiKey);

            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: Mozilla/5.0 (compatible; PortfolioTracker/1.0)',
                        'Accept: application/json'
                    ],
                    'timeout' => 10
                ]
            ]);

            $response = file_get_contents($url, false, $context);

            if ($response === false) {
                return ['valid' => false, 'error' => 'Failed to connect to FMP API'];
            }

            $data = json_decode($response, true);

            if (isset($data['Error Message'])) {
                return ['valid' => false, 'error' => $data['Error Message']];
            }

            if (empty($data) || !is_array($data)) {
                return ['valid' => false, 'error' => 'Invalid response from FMP API'];
            }

            return ['valid' => true, 'data' => $data[0] ?? $data];

        } catch (\Exception $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }
}
