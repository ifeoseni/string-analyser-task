<?php

namespace App\Http\Controllers;

use App\Models\AnalyzedString;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class StringController extends Controller
{
    /**
     * POST /strings
     * Create or analyze a string
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'value' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Invalid request body or missing "value" field',
            ], 400);
        }

        $value = trim($request->input('value'));

        if (!is_string($value)) {
            return response()->json([
                'error' => 'Invalid data type for "value" (must be string)',
            ], 422);
        }

        $hash = hash('sha256', $value);

        if (AnalyzedString::where('sha256_hash', $hash)->exists()) {
            return response()->json([
                'error' => 'String already exists in the system',
            ], 409);
        }

        $properties = $this->analyzeString($value, $hash);

        $record = AnalyzedString::create([
            'value' => $value,
            'sha256_hash' => $hash,
            'properties' => json_encode($properties), // Ensure JSON for SQLite
        ]);

        return response()->json([
            'id' => $hash,
            'value' => $value,
            'properties' => $properties,
            'created_at' => $record->created_at->toIso8601String(),
        ], 201);
    }

    /**
     * GET /strings/{string_value}
     * Retrieve a specific string (multi-word supported)
     */
    public function show(string $string_value)
    {
        $value = trim(urldecode($string_value));

        $record = AnalyzedString::where('value', $value)->first();

        if (!$record) {
            return response()->json([
                'error' => 'String does not exist in the system',
            ], 404);
        }

        return response()->json([
            'id' => $record->sha256_hash,
            'value' => $record->value,
            'properties' => is_string($record->properties)
                ? json_decode($record->properties, true)
                : $record->properties,
            'created_at' => $record->created_at->toIso8601String(),
        ], 200);
    }

    /**
     * GET /strings
     * Retrieve all strings with optional filters
     */
    public function getStrings(Request $request)
    {
        // Fetch query parameters
        $isPalindrome = $request->query('is_palindrome');
        $minLength = (int) $request->query('min_length', 0);
        $maxLength = (int) $request->query('max_length', PHP_INT_MAX);
        $wordCount = $request->query('word_count');
        $containsCharacter = $request->query('contains_character');

        // Retrieve all stored strings from the database
        $strings = \App\Models\AnalyzedString::all(); // Replace with your actual model name

        // Apply filters
        $filtered = $strings->filter(function ($string) use ($isPalindrome, $minLength, $maxLength, $wordCount, $containsCharacter) {
            $value = $string->value;

            // Decode only if 'properties' is a string
            $properties = is_string($string->properties)
                ? json_decode($string->properties, true)
                : (array) $string->properties;

            $length = (int) ($properties['length'] ?? strlen($value));
            $isPal = filter_var($properties['is_palindrome'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $wc = (int) ($properties['word_count'] ?? str_word_count($value));

            // Length filter
            if ($length < $minLength || $length > $maxLength) {
                return false;
            }

            // Palindrome filter
            if (!is_null($isPalindrome)) {
                $queryVal = filter_var($isPalindrome, FILTER_VALIDATE_BOOLEAN);
                if ($queryVal !== $isPal) {
                    return false;
                }
            }

            // Word count filter
            if (!is_null($wordCount) && $wc !== (int) $wordCount) {
                return false;
            }

            // Contains character filter (case-insensitive)
            if (!empty($containsCharacter) && stripos($value, $containsCharacter) === false) {
                return false;
            }

            return true;
        });

        // Return the response
        return response()->json([
            'data' => $filtered->values(),
            'count' => $filtered->count(),
            'filters_applied' => $request->query(),
        ]);
    }
    public function destroy($string_value)
    {
        // Retrieve the string entry by its 'value'
        $string = \App\Models\AnalyzedString::where('value', $string_value)->first();

        // Return 404 if not found
        if (!$string) {
            return response()->json([
                'error' => 'String not found in the system.'
            ], 404);
        }

        // Delete the record
        $string->delete();

        // Return 204 No Content (no response body)
        return response()->noContent();
    }




    /**
     * Analyze a string and compute its properties
     */
    private function analyzeString(string $value, string $hash): array
    {
        $normalized = mb_strtolower(preg_replace('/\s+/', '', $value));

        $characterFrequency = [];
        foreach (mb_str_split($value) as $char) {
            $characterFrequency[$char] = ($characterFrequency[$char] ?? 0) + 1;
        }

        return [
            'length' => mb_strlen($value),
            'is_palindrome' => $normalized === mb_strtolower(strrev($normalized)),
            'unique_characters' => count(array_unique(mb_str_split($value))),
            'word_count' => str_word_count($value),
            'sha256_hash' => $hash,
            'character_frequency_map' => $characterFrequency,
        ];
    }
    public function filterByNaturalLanguage(Request $request)
{
    $query = $request->query('query', '');

    if (empty($query)) {
        return response()->json([
            'error' => 'Missing "query" parameter'
        ], 400);
    }

    $queryLower = strtolower($query);
    $filters = [];

    // Simple heuristics
    if (str_contains($queryLower, 'single word')) {
        $filters['word_count'] = 1;
    }

    if (str_contains($queryLower, 'palindromic')) {
        $filters['is_palindrome'] = true;
    }

    if (preg_match('/longer than (\d+)/', $queryLower, $matches)) {
        $filters['min_length'] = (int) $matches[1] + 1;
    }

    if (preg_match('/shorter than (\d+)/', $queryLower, $matches)) {
        $filters['max_length'] = (int) $matches[1] - 1;
    }

    if (preg_match('/containing the letter (\w)/', $queryLower, $matches)) {
        $filters['contains_character'] = $matches[1];
    }

    // Apply filters to the database
    $queryBuilder = AnalyzedString::query();

    if (isset($filters['is_palindrome'])) {
        $queryBuilder->whereRaw("json_extract(properties, '$.is_palindrome') = ?", [$filters['is_palindrome'] ? '1' : '0']);
    }

    if (isset($filters['min_length'])) {
        $queryBuilder->whereRaw('LENGTH(value) >= ?', [$filters['min_length']]);
    }

    if (isset($filters['max_length'])) {
        $queryBuilder->whereRaw('LENGTH(value) <= ?', [$filters['max_length']]);
    }

    if (isset($filters['word_count'])) {
        $queryBuilder->whereRaw('(LENGTH(value) - LENGTH(REPLACE(value, " ", "")) + 1) = ?', [$filters['word_count']]);
    }

    if (isset($filters['contains_character'])) {
        $queryBuilder->where('value', 'like', '%' . $filters['contains_character'] . '%');
    }

    $results = $queryBuilder->get();

    return response()->json([
        'data' => $results->map(function ($item) {
            return [
                'id' => $item->sha256_hash,
                'value' => $item->value,
                'properties' => is_string($item->properties)
                    ? json_decode($item->properties, true)
                    : $item->properties,
                'created_at' => $item->created_at->toIso8601String(),
            ];
        }),
        'count' => $results->count(),
        'interpreted_query' => [
            'original' => $request->query('query'),
            'parsed_filters' => $filters,
        ],
    ], 200);
}


    private function parseNaturalLanguageQuery(string $text): ?array
    {
        $text = strtolower(trim($text));
        $filters = [];

        // Word count: "single word", "two word"
        if (preg_match('/single word/', $text)) {
            $filters['word_count'] = 1;
        } elseif (preg_match('/two word/', $text)) {
            $filters['word_count'] = 2;
        }

        // Palindrome detection
        if (preg_match('/palindromic|palindrome/', $text)) {
            $filters['is_palindrome'] = true;
        }

        // String length: "longer than X", "shorter than Y"
        if (preg_match('/longer than (\d+)/', $text, $matches)) {
            $filters['min_length'] = (int)$matches[1] + 1;
        }

        if (preg_match('/shorter than (\d+)/', $text, $matches)) {
            $filters['max_length'] = (int)$matches[1] - 1;
        }

        // Contains character
        if (preg_match('/containing the letter ([a-z])/', $text, $matches)
            || preg_match('/contain the letter ([a-z])/', $text, $matches)) {
            $filters['contains_character'] = $matches[1];
        }

        // First vowel heuristic
        if (preg_match('/first vowel/', $text)) {
            $filters['contains_character'] = 'a';
        }

        // If no filters matched, return null
        if (empty($filters)) {
            return null;
        }

        // Conflict detection example
        if (isset($filters['min_length'], $filters['max_length'])
            && $filters['min_length'] > $filters['max_length']) {
            $filters['conflict'] = true;
        }

        return $filters;
    }


}

