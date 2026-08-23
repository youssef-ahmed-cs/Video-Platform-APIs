<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ModerationService
{
    public function moderateContent(string $text): array
    {
        try {

            if (empty(trim($text))) {
                return [
                    'error' => 'Text content cannot be empty',
                ];
            }

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . config('services.hackai.token'),
            ])->post('https://ai.hackclub.com/proxy/v1/moderations', [
                'input' => $text,
            ]);

            if ($response->failed()) {
                Log::error('Moderation API request failed', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);

                return [
                    'error' => 'Moderation request failed',
                ];
            }

            $data = $response->json();
            $result = $data['results'][0] ?? null;

            if (!$result) {
                Log::warning('No moderation results returned', ['response' => $data]);
                return [
                    'error' => 'No moderation results returned',
                ];
            }

            $flaggedCategories = array_keys(
                array_filter($result['categories'] ?? []),
                true,
                ARRAY_FILTER_USE_BOTH
            );

            $severity = $this->determineSeverity($result['category_scores'] ?? []);

            return [
                'flagged' => $result['flagged'] ?? false,
                'categories' => $result['categories'] ?? [],
                'category_scores' => $result['category_scores'] ?? [],
                'flagged_categories' => $flaggedCategories,
                'severity' => $severity,
            ];
        } catch (\Exception $e) {
            Log::error('Moderation service exception', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return [
                'error' => 'An error occurred during content moderation',
            ];
        }
    }

    private function determineSeverity(array $scores): string
    {
        if (empty($scores)) {
            return 'low';
        }

        $maxScore = max($scores);

        if ($maxScore >= 0.9) {
            return 'critical';
        } elseif ($maxScore >= 0.7) {
            return 'high';
        } elseif ($maxScore >= 0.5) {
            return 'medium';
        }

        return 'low';
    }

    public function getModerationMessage(array $result): string
    {
        if (isset($result['error'])) {
            return $result['error'];
        }

        if (!($result['flagged'] ?? false)) {
            return 'Content passed moderation checks';
        }

        $flaggedList = implode(', ', $result['flagged_categories'] ?? []);
        $severity = $result['severity'] ?? 'unknown';

        return "Content flagged for: {$flaggedList} (Severity: {$severity})";
    }
}
